<?php

namespace Tests\Feature;

use App\IA\Incidents\AnalyseAnomalies;
use App\IA\Incidents\PreparationDonnees;
use App\Models\Incident;
use App\Models\ReseauEau;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\DonneesIncidents;
use Tests\TestCase;

// Analyse IA des incidents (binôme 2). Base gestion_eau_test uniquement ; storage/ simulé (Storage::fake).
// Les incidents créés ici sont des données de TEST construites à la main, pas des données réelles.
class AnalyseIncidentsIATest extends TestCase
{
    use RefreshDatabase, DonneesIncidents;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->preparerDonnees();
    }

    /*
     * 4 réseaux, 1 fuite faible par réseau et par mois pendant 10 mois (comportement régulier),
     * puis un mois inhabituel injecté sur un réseau : 6 incidents de plus, dont critiques et contaminations.
     */
    private function historiqueAvecMoisInhabituel(): ReseauEau
    {
        $reseaux = collect([$this->reseau]);
        foreach (['RES-TUN-002', 'STA-TUN-003', 'CAN-TUN-004'] as $code) {
            $reseaux->push($this->zone->reseaux()->create([
                'code' => $code, 'type' => 'canalisation', 'materiau' => 'pvc', 'date_mise_en_service' => '2000-01-01', 'etat' => 'bon',
            ]));
        }

        foreach ($reseaux as $reseau) {
            for ($k = 1; $k <= 10; $k++) {
                $this->creerIncident([
                    'reseau_id' => $reseau->id, 'type' => 'fuite', 'gravite' => 'faible',
                    'date_signalement' => today()->startOfMonth()->subMonths($k)->addDays(9)->toDateString(),
                ]);
            }
        }

        $cible = $reseaux[2];
        foreach (['contamination', 'contamination', 'contamination', 'fuite', 'coupure', 'coupure'] as $i => $type) {
            $this->creerIncident([
                'reseau_id' => $cible->id, 'type' => $type, 'gravite' => $i < 3 ? 'critique' : 'moyenne',
                'date_signalement' => today()->startOfMonth()->subMonths(2)->addDays(3 + $i)->toDateString(),
            ]);
        }

        return $cible;
    }

    public function test_diagnostic_des_donnees(): void
    {
        $this->creerIncident(['type' => 'contamination', 'date_signalement' => today()->subDays(40)->toDateString()]);
        $this->creerIntervention($this->creerIncident(), 'en_cours');

        $d = PreparationDonnees::diagnostic();

        $this->assertSame(2, $d['incidents']);
        $this->assertSame(1, $d['interventions']);
        $this->assertSame(1, $d['reseaux_avec_incidents']);
        $this->assertSame(1, $d['incidents_avec_delai']);
        $this->assertSame(1, $d['incidents_sans_intervention']);
        $this->assertSame(1, collect($d['par_type'])->firstWhere('cle', 'contamination')['nombre']);
        $this->assertGreaterThanOrEqual(1, $d['mois_couverts']);
    }

    public function test_observations_reseau_par_mois(): void
    {
        $this->creerIncident(['gravite' => 'critique', 'date_signalement' => today()->startOfMonth()->subMonth()->addDays(2)->toDateString()]);

        $preparation = PreparationDonnees::observations();

        // 1 réseau × (mois précédent + mois en cours)
        $this->assertCount(2, $preparation['lignes']);
        $ligne = $preparation['lignes'][0];
        $this->assertSame(array_keys(PreparationDonnees::VARIABLES), array_keys($ligne['valeurs']));
        $this->assertSame(1, $ligne['valeurs']['incidents']);
        $this->assertSame(1, $ligne['valeurs']['critiques']);
        $this->assertTrue($ligne['delai_impute']);
    }

    public function test_pas_de_modele_si_les_donnees_sont_insuffisantes(): void
    {
        $this->creerIncident();

        $resultat = AnalyseAnomalies::executer();

        $this->assertSame('donnees_insuffisantes', $resultat['statut']);
        $this->assertSame([], $resultat['anomalies']);
        $this->assertNull($resultat['stabilite']);
        $this->assertTrue(collect($resultat['criteres'])->contains('ok', false));
        Storage::disk('local')->assertExists(AnalyseAnomalies::FICHIER);
    }

    public function test_le_mois_injecte_est_signale_en_premier_avec_une_explication(): void
    {
        $cible = $this->historiqueAvecMoisInhabituel();
        $moisInjecte = today()->startOfMonth()->subMonths(2)->format('Y-m');

        $resultat = AnalyseAnomalies::executer();

        $this->assertSame('termine', $resultat['statut']);
        $this->assertNotEmpty($resultat['anomalies']);
        $premiere = $resultat['anomalies'][0];
        $this->assertSame($cible->code, $premiere['reseau']);
        $this->assertSame($moisInjecte, $premiere['mois']);
        $this->assertGreaterThanOrEqual(AnalyseAnomalies::SEUIL, $premiere['score']);
        // L'explication cite au moins une des variables réellement modifiées par l'injection
        $this->assertNotEmpty(array_intersect(
            ['incidents', 'hausse', 'critiques', 'part_contamination'],
            collect($premiere['explications'])->pluck('variable')->all()
        ));
        $this->assertNotEmpty($premiere['recommandations']);

        // Stabilité mesurée sur 5 autres graines, valeurs entre 0 et 1
        $this->assertCount(count(AnalyseAnomalies::GRAINES_STABILITE), $resultat['stabilite']['jaccard']);
        foreach ($resultat['stabilite']['jaccard'] as $jaccard) {
            $this->assertGreaterThanOrEqual(0, $jaccard);
            $this->assertLessThanOrEqual(1, $jaccard);
        }

        // La répartition couvre toutes les observations ; l'analyse est relue à l'identique
        $this->assertSame($resultat['observations'], array_sum(array_column($resultat['distribution'], 'nombre')));
        $this->assertSame($resultat['anomalies'], AnalyseAnomalies::charger()['anomalies']);
    }

    public function test_l_analyse_ne_modifie_aucune_donnee(): void
    {
        $this->historiqueAvecMoisInhabituel();
        $avant = Incident::orderBy('id')->get()->toArray();

        AnalyseAnomalies::executer();

        $this->assertSame($avant, Incident::orderBy('id')->get()->toArray());
    }

    // ---------- Page ----------

    public function test_page_sans_analyse_puis_lancement(): void
    {
        $this->exigerRoutesDuModule();
        $this->actingAs($this->gestionnaire);

        $this->get(route('admin.incidents.ia'))->assertOk()->assertSee("Aucune analyse n'a encore été lancée", false);

        $this->post(route('admin.incidents.ia.lancer'))->assertRedirect(route('admin.incidents.ia'));
        Storage::disk('local')->assertExists(AnalyseAnomalies::FICHIER);

        $this->get(route('admin.incidents.ia'))->assertOk()->assertSee('Données insuffisantes');
    }

    public function test_page_avec_resultats_et_filtres(): void
    {
        $this->exigerRoutesDuModule();
        $cible = $this->historiqueAvecMoisInhabituel();
        AnalyseAnomalies::executer();
        $this->actingAs($this->gestionnaire);

        $this->get(route('admin.incidents.ia'))->assertOk()->assertSee('Observations inhabituelles')->assertSee($cible->code);
        $this->get(route('admin.incidents.ia', ['reseau' => $cible->code, 'niveau' => 'forte']))->assertOk();
        $this->getJson(route('admin.incidents.ia'))->assertOk()->assertJsonPath('statut', 'termine');
    }

    public function test_un_citoyen_n_a_pas_acces_a_l_analyse(): void
    {
        $this->exigerRoutesDuModule();

        $this->actingAs($this->citoyen)->get(route('admin.incidents.ia'))->assertForbidden();
        $this->actingAs($this->citoyen)->post(route('admin.incidents.ia.lancer'))->assertForbidden();
        Storage::disk('local')->assertMissing(AnalyseAnomalies::FICHIER);
    }
}
