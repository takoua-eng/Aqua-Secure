<?php

namespace Tests\Feature;

use App\Services\PrioriteIncident;
use App\Services\StatistiquesIncidents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\DonneesIncidents;
use Tests\TestCase;

// Priorisation et statistiques calculées en base (binôme 2). Base gestion_eau_test uniquement.
class PrioriteStatistiquesTest extends TestCase
{
    use RefreshDatabase, DonneesIncidents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerDonnees();
    }

    // ---------- Priorisation ----------

    public function test_le_classement_exclut_les_resolus_et_trie_par_score(): void
    {
        $faible = $this->creerIncident(['gravite' => 'faible']);
        $critique = $this->creerIncident(['gravite' => 'critique', 'type' => 'contamination']);
        $resolu = $this->creerIncident(['gravite' => 'critique']);
        $this->creerIntervention($resolu, 'terminee');

        $classement = PrioriteIncident::classement();

        $this->assertEquals([$critique->id, $faible->id], $classement->pluck('incident.id')->all());
        $this->assertGreaterThan($classement[1]['priorite']['score'], $classement[0]['priorite']['score']);
    }

    public function test_l_etat_reel_du_reseau_compte_dans_le_score(): void
    {
        $surReseauBon = $this->creerIncident();
        $surReseauDegrade = $this->creerIncident(['reseau_id' => $this->autreReseau()->id]);   // état "degrade"

        $scores = PrioriteIncident::classement()->mapWithKeys(fn ($l) => [$l['incident']->id => $l['priorite']['score']]);

        $this->assertSame(3, $scores[$surReseauDegrade->id] - $scores[$surReseauBon->id]);
    }

    public function test_reseaux_a_incidents_recurrents(): void
    {
        foreach (range(1, PrioriteIncident::SEUIL_RECURRENCE) as $i) {
            $this->creerIncident();
        }
        $this->creerIncident(['reseau_id' => $this->autreReseau()->id]);

        $recurrents = PrioriteIncident::reseauxRecurrents();

        $this->assertCount(1, $recurrents);
        $this->assertSame($this->reseau->id, (int) $recurrents->first()->reseau_id);
        $this->assertSame(PrioriteIncident::SEUIL_RECURRENCE, (int) $recurrents->first()->nombre);
    }

    // ---------- Statistiques ----------

    public function test_statistiques_sans_aucune_donnee(): void
    {
        $stats = StatistiquesIncidents::calculer();

        $this->assertSame(0, $stats['total']);
        $this->assertNull($stats['tauxResolution']);
        $this->assertNull($stats['delaiPriseEnCharge']);
        $this->assertNull($stats['dureeResolution']);
        $this->assertCount(12, $stats['evolution']);
    }

    public function test_statistiques_calculees_a_partir_de_la_base(): void
    {
        // Résolu : signalé il y a 10 jours, terminé il y a 4 jours => 6 jours (prise en charge et résolution)
        $resolu = $this->creerIncident(['type' => 'fuite', 'gravite' => 'faible', 'date_signalement' => today()->subDays(10)->toDateString()]);
        $this->creerIntervention($resolu, 'terminee', today()->subDays(4)->toDateString());
        // En traitement : signalé il y a 5 jours, commencé il y a 3 jours => 2 jours de prise en charge
        $enCours = $this->creerIncident(['type' => 'coupure', 'gravite' => 'moyenne', 'date_signalement' => today()->subDays(5)->toDateString()]);
        $this->creerIntervention($enCours, 'en_cours', today()->subDays(3)->toDateString());
        // Non traité, critique, signalé il y a 2 jours ; et une intervention planifiée en retard sur un autre incident
        $this->creerIncident(['type' => 'contamination', 'gravite' => 'critique', 'date_signalement' => today()->subDays(2)->toDateString()]);
        $retard = $this->creerIncident(['date_signalement' => today()->subDays(8)->toDateString()]);
        $this->creerIntervention($retard, 'planifiee', today()->subDay()->toDateString());

        $stats = StatistiquesIncidents::calculer();
        $nombres = fn (string $cle) => collect($stats[$cle])->pluck('nombre', 'cle')->all();

        $this->assertSame(4, $stats['total']);
        $this->assertSame(['fuite' => 2, 'contamination' => 1, 'coupure' => 1], $nombres('parType'));
        $this->assertSame(['faible' => 1, 'moyenne' => 2, 'critique' => 1], $nombres('parGravite'));
        $this->assertSame(['non_traite' => 1, 'en_traitement' => 2, 'resolu' => 1], $nombres('parStatut'));
        $this->assertEquals(25.0, $stats['tauxResolution']);
        $this->assertEquals(['jours' => 4.0, 'base' => 2], $stats['delaiPriseEnCharge']);   // (6 + 2) / 2
        $this->assertEquals(['jours' => 6.0, 'base' => 1], $stats['dureeResolution']);
        $this->assertSame(1, $stats['interventionsEnRetard']);
        $this->assertSame(1, $stats['critiquesSansIntervention']);
        $this->assertSame([['libelle' => 'Centre-ville', 'nombre' => 4]], $stats['parZone']);
        $this->assertSame(4, array_sum(array_column($stats['evolution'], 'total')));
    }

    // ---------- Filtres des statistiques ----------

    public function test_statistiques_filtrees_par_periode_et_par_type(): void
    {
        $ancienneFuite = $this->creerIncident(['type' => 'fuite', 'date_signalement' => today()->subDays(40)->toDateString()]);
        $coupure = $this->creerIncident(['type' => 'coupure', 'date_signalement' => today()->subDays(5)->toDateString()]);
        $this->creerIntervention($coupure, 'en_cours');
        $this->creerIncident(['type' => 'fuite', 'date_signalement' => today()->subDays(3)->toDateString()]);
        $this->creerIntervention($ancienneFuite, 'terminee', today()->subDays(30)->toDateString());

        $recents = StatistiquesIncidents::calculer(['du' => today()->subDays(10)->toDateString()]);
        $this->assertSame(2, $recents['total']);
        // Les interventions comptées sont celles des incidents filtrés : la fuite résolue il y a 30 jours est exclue
        $this->assertSame(['planifiee' => 0, 'en_cours' => 1, 'terminee' => 0], collect($recents['interventions'])->pluck('nombre', 'cle')->all());

        $fuites = StatistiquesIncidents::calculer(['type' => 'fuite']);
        $this->assertSame(2, $fuites['total']);
        $this->assertSame(['fuite' => 2, 'contamination' => 0, 'coupure' => 0], collect($fuites['parType'])->pluck('nombre', 'cle')->all());
        $this->assertEquals(50.0, $fuites['tauxResolution']);

        $combine = StatistiquesIncidents::calculer(['type' => 'fuite', 'du' => today()->subDays(10)->toDateString()]);
        $this->assertSame(1, $combine['total']);
        $this->assertSame(['du' => today()->subDays(10)->toDateString(), 'au' => null, 'type' => 'fuite'], $combine['filtres']);
    }

    public function test_un_filtre_inconnu_est_ignore(): void
    {
        $this->creerIncident();

        $stats = StatistiquesIncidents::calculer(['type' => 'inondation', 'gravite' => 'critique']);

        $this->assertSame(1, $stats['total']);
        $this->assertSame(['du' => null, 'au' => null, 'type' => null], $stats['filtres']);
    }

    public function test_l_evolution_suit_la_periode_choisie_et_reste_plafonnee(): void
    {
        $this->creerIncident(['date_signalement' => today()->subDays(40)->toDateString()]);

        $periode = StatistiquesIncidents::evolution(12, ['du' => today()->subDays(40)->toDateString()]);
        $moisAttendus = (int) today()->subDays(40)->startOfMonth()->diffInMonths(today()->startOfMonth()) + 1;
        $this->assertCount($moisAttendus, $periode);
        $this->assertSame(1, array_sum(array_column($periode, 'total')));

        $tresLongue = StatistiquesIncidents::evolution(12, ['du' => today()->subYears(5)->toDateString()]);
        $this->assertCount(StatistiquesIncidents::MOIS_MAX, $tresLongue);
    }

    public function test_page_et_json_des_statistiques_filtrees(): void
    {
        $this->exigerRoutesDuModule();
        $this->creerIncident(['type' => 'coupure']);
        $this->actingAs($this->gestionnaire);

        $this->getJson(route('admin.incidents.statistiques.donnees', ['type' => 'coupure']))->assertOk()->assertJsonPath('total', 1);
        $this->getJson(route('admin.incidents.statistiques.donnees', ['type' => 'fuite']))->assertOk()->assertJsonPath('total', 0);

        // Période inversée : refusée en JSON, ignorée avec un message sur la page
        $inversee = ['du' => today()->toDateString(), 'au' => today()->subDays(5)->toDateString()];
        $this->getJson(route('admin.incidents.statistiques.donnees', $inversee))->assertStatus(422);
        $this->get(route('admin.incidents.statistiques', $inversee))->assertOk()->assertSee('la période a été ignorée');

        // Aucun résultat pour ces filtres : état vide propre
        $this->get(route('admin.incidents.statistiques', ['type' => 'fuite']))->assertOk()->assertSee('Aucun incident pour ces filtres');
    }

    // ---------- Pages ----------

    public function test_pages_priorites_et_statistiques(): void
    {
        $this->exigerRoutesDuModule();
        $this->actingAs($this->gestionnaire);

        // Sans données : états vides, aucun faux chiffre
        $this->get(route('admin.incidents.statistiques'))->assertOk()->assertSee('Pas encore de statistiques');
        $this->get(route('admin.incidents.priorites'))->assertOk()->assertSee('Aucun incident à traiter');

        $incident = $this->creerIncident(['gravite' => 'critique', 'date_signalement' => today()->subDays(3)->toDateString()]);

        $this->get(route('admin.incidents.statistiques'))->assertOk()->assertSee('Incidents par type');
        $this->get(route('admin.incidents.priorites'))->assertOk()->assertSee('#' . $incident->id)->assertSee('Critiques non pris en charge');
        $this->getJson(route('admin.incidents.statistiques.donnees'))->assertOk()->assertJsonPath('total', 1);
        $this->getJson(route('admin.incidents.priorites'))->assertOk()->assertJsonPath('data.0.incident_id', $incident->id);
    }

    public function test_un_citoyen_n_a_pas_acces_aux_statistiques(): void
    {
        $this->exigerRoutesDuModule();

        $this->actingAs($this->citoyen)->get(route('admin.incidents.statistiques'))->assertForbidden();
        $this->actingAs($this->citoyen)->getJson(route('admin.incidents.statistiques.donnees'))->assertForbidden();
    }
}
