<?php

namespace Tests\Feature;

use App\Models\AnalyseQualite;
use App\Models\PointPrelevement;
use App\Models\ReseauEau;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\InfrastructureSeeder;
use Database\Seeders\QualiteEauSeeder;
use App\IA\ArbreDecision;
use App\Services\DetectionContamination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QualiteEauTest extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;
    private ReseauEau $reseau;

    protected function setUp(): void
    {
        parent::setUp();

        // Le modèle IA est enregistré sur un disque de test, jamais dans storage/
        Storage::fake('local');
        DetectionContamination::oublier();

        $this->gestionnaire = User::factory()->create(['role' => 'gestionnaire']);
        $zone = Zone::create(['nom' => 'Centre-ville', 'commune' => 'Tunis', 'population' => 42000]);
        $this->reseau = $zone->reseaux()->create([
            'code' => 'CAN-TUN-001', 'type' => 'canalisation', 'materiau' => 'fonte',
            'date_mise_en_service' => '1990-01-01', 'etat' => 'bon',
        ]);
    }

    // ---------- Outils ----------

    private function creerPoint(array $valeurs = []): PointPrelevement
    {
        return PointPrelevement::create($valeurs + [
            'reseau_id' => $this->reseau->id, 'code' => 'PP-TN-012', 'nom' => 'Fontaine Bab Saadoun',
            'adresse' => 'Place Bab Saadoun, Tunis', 'type_point' => 'robinet_public',
        ]);
    }

    // Mesures toutes dans la norme
    private function mesuresConformes(array $valeurs = []): array
    {
        return $valeurs + [
            'ph' => 7.2, 'chlore_residuel' => 0.4, 'turbidite' => 1.2, 'nitrates' => 12, 'bacteries_coliformes' => 0,
        ];
    }

    private function creerAnalyse(PointPrelevement $point, array $valeurs = []): AnalyseQualite
    {
        return AnalyseQualite::create($this->mesuresConformes($valeurs) + [
            'point_id' => $point->id, 'date_prelevement' => now()->subDays(3)->toDateString(), 'laboratoire' => 'SONEDE Tunis',
        ]);
    }

    private function donneesAnalyse(PointPrelevement $point, array $valeurs = []): array
    {
        return $valeurs + $this->mesuresConformes() + [
            'point_id' => $point->id, 'date_prelevement' => now()->subDay()->toDateString(), 'laboratoire' => 'SONEDE Tunis',
        ];
    }

    // ---------- Accès ----------

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('admin.prelevements.index'))->assertRedirect(route('login'));
        $this->get(route('admin.analyses.index'))->assertRedirect(route('login'));
    }

    public function test_un_citoyen_n_a_pas_acces_au_module(): void
    {
        $citoyen = User::factory()->create(['role' => 'citoyen']);
        $point = $this->creerPoint();
        $analyse = $this->creerAnalyse($point);

        $this->actingAs($citoyen);
        $this->get(route('admin.prelevements.index'))->assertForbidden();
        $this->get(route('admin.prelevements.show', $point))->assertForbidden();
        $this->get(route('admin.analyses.index'))->assertForbidden();
        $this->get(route('admin.analyses.create'))->assertForbidden();
        $this->get(route('admin.analyses.pdf', $analyse))->assertForbidden();
        $this->post(route('admin.analyses.store'), $this->donneesAnalyse($point))->assertForbidden();
        $this->delete(route('admin.prelevements.destroy', $point))->assertForbidden();

        $this->assertSame(1, AnalyseQualite::count());
    }

    public function test_un_admin_a_acces_au_module(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.prelevements.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.analyses.index'))->assertOk();
    }

    // ---------- Affichage des pages ----------

    public function test_les_pages_des_points_s_affichent(): void
    {
        $point = $this->creerPoint(['latitude' => 36.8, 'longitude' => 10.17]);
        $this->creerAnalyse($point);
        $this->creerAnalyse($point, ['bacteries_coliformes' => 4]);

        $this->actingAs($this->gestionnaire);
        $this->get(route('admin.prelevements.index'))->assertOk()->assertSee('PP-TN-012');
        $this->get(route('admin.prelevements.create'))->assertOk()->assertSee('carte-position');
        $this->get(route('admin.prelevements.edit', $point))->assertOk();
        $this->get(route('admin.prelevements.show', $point))
            ->assertOk()
            ->assertSee('Fontaine Bab Saadoun')
            ->assertSee('graphique-parametre')
            ->assertSee('50,0 %')                       // 1 analyse conforme sur 2
            ->assertViewHas('taux', 50.0);
    }

    public function test_la_liste_des_points_se_filtre(): void
    {
        $this->creerPoint();
        $this->creerPoint(['code' => 'PP-TN-020', 'nom' => 'Réservoir Nord', 'type_point' => 'reservoir', 'actif' => false]);

        $this->actingAs($this->gestionnaire);
        $this->get(route('admin.prelevements.index', ['q' => 'Nord']))->assertSee('PP-TN-020')->assertDontSee('PP-TN-012');
        $this->get(route('admin.prelevements.index', ['type' => 'robinet_public']))->assertSee('PP-TN-012')->assertDontSee('PP-TN-020');
        $this->get(route('admin.prelevements.index', ['actif' => '0']))->assertSee('PP-TN-020')->assertDontSee('PP-TN-012');
    }

    public function test_les_pages_des_analyses_s_affichent(): void
    {
        $point = $this->creerPoint();
        $analyse = $this->creerAnalyse($point, ['nitrates' => 62.5]);

        $this->actingAs($this->gestionnaire);
        $this->get(route('admin.analyses.index'))->assertOk()->assertSee('PP-TN-012')->assertSee('text-red fw-bold', false);
        $this->get(route('admin.analyses.create', ['point' => $point->id]))->assertOk()->assertSee('verdict');
        $this->get(route('admin.analyses.edit', $analyse))->assertOk();
        $this->get(route('admin.analyses.show', $analyse))
            ->assertOk()
            ->assertSee('Eau non conforme')
            ->assertSee('Nitrates trop élevés');
    }

    public function test_la_liste_des_analyses_se_filtre_et_calcule_les_indicateurs(): void
    {
        $point = $this->creerPoint();
        $autre = $this->creerPoint(['code' => 'PP-TN-020']);
        $this->creerAnalyse($point);
        $this->creerAnalyse($point, ['ph' => 9.1]);
        $this->creerAnalyse($point, ['date_prelevement' => now()->subYear()->toDateString()]);
        $this->creerAnalyse($autre);

        $this->actingAs($this->gestionnaire);

        $this->get(route('admin.analyses.index'))
            ->assertViewHas('indicateurs', ['total' => 4, 'conformes' => 3, 'nonConformes' => 1, 'taux' => 75.0]);

        $this->get(route('admin.analyses.index', ['point' => $point->id, 'du' => now()->subMonth()->toDateString()]))
            ->assertViewHas('indicateurs', fn ($i) => $i['total'] === 2 && $i['nonConformes'] === 1);

        $this->get(route('admin.analyses.index', ['conforme' => '0']))
            ->assertViewHas('analyses', fn ($analyses) => $analyses->total() === 1 && ! $analyses->first()->conforme);
    }

    // ---------- Points de prélèvement : création et validation ----------

    public function test_creer_un_point_de_prelevement(): void
    {
        $this->actingAs($this->gestionnaire)
            ->post(route('admin.prelevements.store'), [
                'reseau_id' => $this->reseau->id, 'code' => ' pp-tn-012 ', 'nom' => 'Fontaine Bab Saadoun',
                'adresse' => 'Place Bab Saadoun', 'type_point' => 'robinet_public',
                'latitude' => '36,80650', 'longitude' => '10,1815', 'actif' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $point = PointPrelevement::firstOrFail();
        $this->assertSame('PP-TN-012', $point->code);   // enregistré en majuscules
        $this->assertSame(36.8065, $point->latitude);   // virgule décimale acceptée
        $this->assertTrue($point->actif);
        $this->assertTrue($this->reseau->pointsPrelevement()->exists());
    }

    public function test_mettre_un_point_hors_service(): void
    {
        $point = $this->creerPoint();

        $this->actingAs($this->gestionnaire)
            ->put(route('admin.prelevements.update', $point), [
                'reseau_id' => $this->reseau->id, 'code' => 'PP-TN-012', 'nom' => 'Fontaine Bab Saadoun',
                'adresse' => 'Place Bab Saadoun', 'type_point' => 'robinet_public', 'actif' => '0',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($point->fresh()->actif);
    }

    public function test_validation_d_un_point_de_prelevement(): void
    {
        $this->creerPoint();

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.prelevements.store'), [
                'reseau_id' => 999, 'code' => 'pp-tn-012', 'nom' => '', 'adresse' => '',
                'type_point' => 'piscine', 'latitude' => '95', 'longitude' => '200',
            ])
            ->assertSessionHasErrors([
                'reseau_id' => "Le réseau choisi n'existe pas.",
                'code'      => 'Ce code est déjà utilisé par un autre point de prélèvement.',
                'nom'       => 'Le nom du point est obligatoire.',
                'adresse'   => "L'adresse est obligatoire.",
                'type_point' => 'Le type doit être robinet public, réservoir ou sortie de station.',
                'latitude'  => 'La latitude doit être comprise entre -90 et 90.',
                'longitude' => 'La longitude doit être comprise entre -180 et 180.',
            ]);

        $this->assertSame(1, PointPrelevement::count());
    }

    public function test_la_latitude_et_la_longitude_vont_ensemble(): void
    {
        $this->actingAs($this->gestionnaire)
            ->post(route('admin.prelevements.store'), [
                'reseau_id' => $this->reseau->id, 'code' => 'PP-TN-012', 'nom' => 'Fontaine',
                'adresse' => 'Place Bab Saadoun', 'type_point' => 'robinet_public', 'latitude' => '36.8',
            ])
            ->assertSessionHasErrors(['longitude']);
    }

    // ---------- Analyses : création et validation ----------

    public function test_creer_une_analyse_avec_la_virgule_decimale(): void
    {
        $point = $this->creerPoint();

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.analyses.store'), $this->donneesAnalyse($point, [
                'ph' => '7,5', 'chlore_residuel' => '0,35', 'turbidite' => '1,8', 'nitrates' => '12,5',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $analyse = AnalyseQualite::firstOrFail();
        $this->assertSame(7.5, $analyse->ph);
        $this->assertSame(0.35, $analyse->chlore_residuel);
        $this->assertTrue($analyse->conforme);
    }

    public function test_la_conformite_ne_peut_pas_etre_saisie(): void
    {
        $point = $this->creerPoint();

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.analyses.store'), $this->donneesAnalyse($point, [
                'bacteries_coliformes' => 5, 'conforme' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(AnalyseQualite::firstOrFail()->conforme);
    }

    public function test_validation_d_une_analyse(): void
    {
        $point = $this->creerPoint();

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.analyses.store'), [
                'point_id' => $point->id,
                'date_prelevement' => now()->addDay()->toDateString(),
                'ph' => '15', 'chlore_residuel' => '-0,1', 'turbidite' => 'abc', 'nitrates' => '-3',
                'bacteries_coliformes' => '1,5', 'laboratoire' => '',
            ])
            ->assertSessionHasErrors([
                'date_prelevement'     => 'La date de prélèvement ne peut pas être dans le futur.',
                'ph'                   => 'Le pH doit être compris entre 0 et 14.',
                'chlore_residuel'      => 'Le chlore résiduel ne peut pas être négatif.',
                'turbidite'            => 'La turbidité doit être un nombre (ex : 1,8).',
                'nitrates'             => 'Le taux de nitrates ne peut pas être négatif.',
                'bacteries_coliformes' => 'Le nombre de bactéries coliformes est un nombre entier (UFC/100 mL).',
                'laboratoire'          => 'Le laboratoire est obligatoire.',
            ]);

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.analyses.store'), [])
            ->assertSessionHasErrors(['point_id', 'date_prelevement', 'ph', 'chlore_residuel', 'turbidite', 'nitrates', 'bacteries_coliformes', 'laboratoire']);

        $this->assertSame(0, AnalyseQualite::count());
    }

    public function test_pas_de_nouvelle_analyse_sur_un_point_hors_service(): void
    {
        $point = $this->creerPoint(['actif' => false]);

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.analyses.store'), $this->donneesAnalyse($point))
            ->assertSessionHasErrors([
                'point_id' => "Le point PP-TN-012 est hors service : impossible d'y enregistrer une nouvelle analyse.",
            ]);

        $this->assertSame(0, AnalyseQualite::count());
    }

    public function test_une_analyse_existante_reste_modifiable_si_son_point_est_hors_service(): void
    {
        $point = $this->creerPoint();
        $analyse = $this->creerAnalyse($point);
        $point->update(['actif' => false]);

        $this->actingAs($this->gestionnaire)
            ->put(route('admin.analyses.update', $analyse), $this->donneesAnalyse($point, ['turbidite' => '6,2']))
            ->assertSessionHasNoErrors();

        $analyse->refresh();
        $this->assertSame(6.2, $analyse->turbidite);
        $this->assertFalse($analyse->conforme);   // recalculée à la modification
    }

    // ---------- Calcul de la conformité ----------

    public function test_une_eau_dans_les_normes_est_conforme(): void
    {
        $analyse = $this->creerAnalyse($this->creerPoint());

        $this->assertTrue($analyse->conforme);
        $this->assertSame([], $analyse->parametresHorsNorme());
    }

    public function test_les_limites_de_la_norme_sont_incluses(): void
    {
        $point = $this->creerPoint();

        foreach ([
            ['ph' => 6.5], ['ph' => 8.5], ['chlore_residuel' => 0.1], ['chlore_residuel' => 1.0],
            ['turbidite' => 5.0], ['nitrates' => 50.0], ['bacteries_coliformes' => 0],
        ] as $valeur) {
            $this->assertTrue($this->creerAnalyse($point, $valeur)->conforme, 'Devrait être conforme : ' . json_encode($valeur));
        }
    }

    public function test_chaque_parametre_hors_norme_rend_l_eau_non_conforme(): void
    {
        $point = $this->creerPoint();

        foreach ([
            'ph'                   => [6.49, 'pH trop acide'],
            'chlore_residuel'      => [0.09, 'Chlore résiduel insuffisant'],
            'turbidite'            => [5.01, 'Turbidité trop élevée'],
            'nitrates'             => [50.01, 'Nitrates trop élevés'],
            'bacteries_coliformes' => [1, 'Bactéries coliformes présentes'],
        ] as $parametre => [$valeur, $debutMessage]) {
            $analyse = $this->creerAnalyse($point, [$parametre => $valeur]);

            $this->assertFalse($analyse->conforme, "{$parametre} = {$valeur} devrait être non conforme");
            $this->assertSame([$parametre], array_keys($analyse->parametresHorsNorme()));
            $this->assertStringStartsWith($debutMessage, $analyse->parametresHorsNorme()[$parametre]);
            $this->assertTrue($analyse->estHorsNorme($parametre));
        }

        // pH trop haut et chlore trop élevé
        $analyse = $this->creerAnalyse($point, ['ph' => 8.6, 'chlore_residuel' => 1.2]);
        $this->assertSame(['ph', 'chlore_residuel'], array_keys($analyse->parametresHorsNorme()));
        $this->assertSame('pH trop basique : 8,60 (norme : entre 6,5 et 8,5)', $analyse->parametresHorsNorme()['ph']);
    }

    public function test_la_conformite_est_recalculee_a_chaque_enregistrement(): void
    {
        $analyse = $this->creerAnalyse($this->creerPoint());
        $this->assertTrue($analyse->conforme);

        $analyse->update(['bacteries_coliformes' => 12]);
        $this->assertFalse($analyse->fresh()->conforme);

        $analyse->update(['bacteries_coliformes' => 0]);
        $this->assertTrue($analyse->fresh()->conforme);
    }

    // ---------- Suppressions protégées ----------

    public function test_un_point_sans_analyse_peut_etre_supprime(): void
    {
        $point = $this->creerPoint();

        $this->actingAs($this->gestionnaire)
            ->delete(route('admin.prelevements.destroy', $point))
            ->assertRedirect(route('admin.prelevements.index'));

        $this->assertModelMissing($point);
    }

    public function test_un_point_qui_a_des_analyses_ne_peut_pas_etre_supprime(): void
    {
        $point = $this->creerPoint();
        $this->creerAnalyse($point);

        $this->actingAs($this->gestionnaire)
            ->from(route('admin.prelevements.index'))
            ->delete(route('admin.prelevements.destroy', $point))
            ->assertRedirect(route('admin.prelevements.index'))
            ->assertSessionHasErrors('point')
            ->assertSessionHas('point_a_desactiver', $point->id);

        $this->assertModelExists($point);
        $this->assertStringContainsString('hors service', session('errors')->first('point'));
    }

    public function test_un_reseau_qui_a_des_points_de_prelevement_ne_peut_pas_etre_supprime(): void
    {
        $this->creerPoint();

        $this->actingAs($this->gestionnaire)
            ->from(route('admin.reseaux.index'))
            ->delete(route('admin.reseaux.destroy', $this->reseau))
            ->assertSessionHasErrors('reseau');

        $this->assertModelExists($this->reseau);
    }

    public function test_une_analyse_peut_etre_supprimee(): void
    {
        $analyse = $this->creerAnalyse($this->creerPoint());

        $this->actingAs($this->gestionnaire)
            ->delete(route('admin.analyses.destroy', $analyse))
            ->assertRedirect(route('admin.analyses.index'));

        $this->assertModelMissing($analyse);
    }

    // ---------- PDF ----------

    public function test_le_bulletin_d_analyse_s_exporte_en_pdf(): void
    {
        $analyse = $this->creerAnalyse($this->creerPoint(), ['turbidite' => 7.4]);

        $reponse = $this->actingAs($this->gestionnaire)->get(route('admin.analyses.pdf', $analyse));

        $reponse->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('bulletin-analyse-PP-TN-012-', $reponse->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    // ---------- Carte, retards, export, tableau de bord, zones et réseaux ----------

    public function test_la_carte_colore_les_points_selon_leur_derniere_analyse(): void
    {
        $sain = $this->creerPoint(['latitude' => 36.80, 'longitude' => 10.18]);
        $contamine = $this->creerPoint(['code' => 'PP-TN-020', 'latitude' => 36.81, 'longitude' => 10.19]);
        $this->creerPoint(['code' => 'PP-TN-030', 'latitude' => 36.82, 'longitude' => 10.20]);   // jamais analysé
        $this->creerPoint(['code' => 'PP-TN-040']);                                                 // sans position
        $this->creerAnalyse($sain);
        $this->creerAnalyse($contamine, ['bacteries_coliformes' => 3]);

        $this->actingAs($this->gestionnaire)
            ->get(route('admin.prelevements.carte'))
            ->assertOk()
            ->assertSee('PP-TN-040')   // signalé comme "sans position"
            ->assertViewHas('carte', function ($carte) {
                $etats = collect($carte)->pluck('etat', 'code');

                return count($carte) === 3
                    && $etats['PP-TN-012'] === 'conforme'
                    && $etats['PP-TN-020'] === 'non_conforme'
                    && $etats['PP-TN-030'] === 'inconnu'
                    && collect($carte)->firstWhere('code', 'PP-TN-020')['problemes'] !== [];
            });
    }

    public function test_un_point_sans_analyse_recente_est_en_retard(): void
    {
        $aJour = $this->creerPoint();
        $enRetard = $this->creerPoint(['code' => 'PP-TN-020']);
        $jamais = $this->creerPoint(['code' => 'PP-TN-030']);
        $horsService = $this->creerPoint(['code' => 'PP-TN-040', 'actif' => false]);
        $this->creerAnalyse($aJour, ['date_prelevement' => now()->subDays(10)->toDateString()]);
        $this->creerAnalyse($enRetard, ['date_prelevement' => now()->subDays(45)->toDateString()]);
        $this->creerAnalyse($horsService, ['date_prelevement' => now()->subYear()->toDateString()]);

        $this->assertNull(PointPrelevement::retardAnalyse(true, now()->subDays(30)));
        $this->assertSame('Jamais analysé', PointPrelevement::retardAnalyse(true, null));
        $this->assertNull(PointPrelevement::retardAnalyse(false, null));   // hors service : jamais en retard
        $this->assertEqualsCanonicalizing(['PP-TN-020', 'PP-TN-030'], PointPrelevement::enRetard()->pluck('code')->all());

        $this->actingAs($this->gestionnaire);
        $this->get(route('admin.prelevements.index'))
            ->assertSee("Pas d'analyse depuis plus de 30 jours")
            ->assertSee('Jamais analysé');
        $this->get(route('admin.prelevements.index', ['retard' => 1]))
            ->assertViewHas('points', fn ($points) => $points->pluck('code')->sort()->values()->all() === ['PP-TN-020', 'PP-TN-030']);
        $this->get(route('admin.prelevements.show', $enRetard))->assertSee('un nouveau prélèvement est à programmer');
        $this->get(route('admin.prelevements.show', $aJour))->assertDontSee('un nouveau prélèvement est à programmer');
    }

    public function test_les_analyses_filtrees_s_exportent_en_csv(): void
    {
        $point = $this->creerPoint();
        $this->creerAnalyse($point, ['ph' => 7.25]);
        $this->creerAnalyse($point, ['nitrates' => 61.5, 'date_prelevement' => now()->subDays(5)->toDateString()]);

        $reponse = $this->actingAs($this->gestionnaire)->get(route('admin.analyses.export', ['conforme' => '0']));

        $reponse->assertOk();
        $this->assertStringContainsString('text/csv', $reponse->headers->get('content-type'));
        $this->assertStringContainsString('analyses-qualite-', $reponse->headers->get('content-disposition'));

        $lignes = explode("\n", trim($reponse->streamedContent()));
        $this->assertStringStartsWith("\xEF\xBB\xBFDate;", $lignes[0]);                  // BOM + en-têtes
        $this->assertCount(2, $lignes);                                                // filtre : 1 seule analyse non conforme
        $this->assertStringContainsString('61,50', $lignes[1]);                        // virgule décimale
        $this->assertStringContainsString('Non conforme', $lignes[1]);
        $this->assertStringContainsString('Nitrates trop élevés', $lignes[1]);
    }

    public function test_le_tableau_de_bord_affiche_la_qualite_de_l_eau(): void
    {
        $sain = $this->creerPoint();
        $contamine = $this->creerPoint(['code' => 'PP-TN-020', 'nom' => 'Fontaine contaminée']);
        $this->creerAnalyse($sain, ['date_prelevement' => now()->toDateString()]);
        $this->creerAnalyse($contamine, ['bacteries_coliformes' => 9, 'date_prelevement' => now()->toDateString()]);

        $qualite = \App\Services\QualiteEau::tableauDeBord();
        $this->assertSame(['mois' => 2, 'conformes' => 1, 'nonConformes' => 1, 'taux' => 50.0],
            array_intersect_key($qualite, array_flip(['mois', 'conformes', 'nonConformes', 'taux'])));
        $this->assertSame(['PP-TN-020'], $qualite['aSurveiller']->pluck('point.code')->all());

        $this->actingAs($this->gestionnaire)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Points à surveiller')
            ->assertSee('Fontaine contaminée')
            ->assertSee('Bactéries coliformes présentes');
    }

    public function test_la_qualite_est_affichee_par_zone_et_par_reseau(): void
    {
        $point = $this->creerPoint();
        $this->creerAnalyse($point);
        $this->creerAnalyse($point, ['turbidite' => 8]);

        $this->assertSame([$this->reseau->zone_id => ['total' => 2, 'taux' => 50.0]], \App\Services\QualiteEau::parZone());

        $this->actingAs($this->gestionnaire);
        $this->get(route('admin.zones.index'))->assertOk()->assertSee('50,0 % conforme');
        $this->get(route('admin.reseaux.edit', $this->reseau))
            ->assertOk()
            ->assertSee("Qualité de l'eau", false)
            ->assertSee('PP-TN-012');
    }

    public function test_un_citoyen_n_a_pas_acces_a_la_carte_ni_a_l_export(): void
    {
        $citoyen = User::factory()->create(['role' => 'citoyen']);

        $this->actingAs($citoyen)->get(route('admin.prelevements.carte'))->assertForbidden();
        $this->actingAs($citoyen)->get(route('admin.analyses.export'))->assertForbidden();
    }

    // ---------- Modèles IA ----------

    // Historique où la contamination suit une règle nette : chlore bas + eau trouble => coliformes
    private function creerHistoriqueContamination(): PointPrelevement
    {
        $point = $this->creerPoint();
        for ($i = 0; $i < 60; $i++) {
            $contamine = $i % 3 === 0;
            $this->creerAnalyse($point, [
                'date_prelevement'     => now()->subDays($i * 7)->toDateString(),
                'chlore_residuel'      => $contamine ? 0.05 : 0.45 + ($i % 5) / 100,
                'turbidite'            => $contamine ? 4.2 : 0.9,
                'bacteries_coliformes' => $contamine ? 12 : 0,
            ]);
        }

        return $point;
    }

    public function test_l_arbre_de_decision_apprend_une_regle_simple(): void
    {
        // Oui si x > 5
        $exemples = array_map(fn ($x) => [$x, $x % 2], range(0, 19));
        $cibles = array_map(fn ($x) => $x > 5 ? 1 : 0, range(0, 19));

        $arbre = (new ArbreDecision(profondeurMax: 3, minParFeuille: 2))->entrainer($exemples, $cibles);

        $this->assertGreaterThan(0.8, $arbre->probabilite([12, 0]));
        $this->assertLessThan(0.2, $arbre->probabilite([2, 0]));
        $this->assertEqualsWithDelta(5.5, $arbre->chemin([12, 0])[0]['seuil'], 0.001);   // la bonne question est trouvée
        $this->assertEqualsWithDelta(1.0, array_sum($arbre->importances()), 0.0001);
        $this->assertSame(0.0, $arbre->importances()[1]);                                // la variable inutile est ignorée

        // Sauvegarde puis rechargement : mêmes prédictions
        $copie = ArbreDecision::depuisTableau(json_decode(json_encode($arbre->versTableau()), true));
        $this->assertSame($arbre->probabilite([12, 0]), $copie->probabilite([12, 0]));
    }

    public function test_le_modele_de_detection_s_entraine_et_predit(): void
    {
        $this->creerHistoriqueContamination();

        $modele = DetectionContamination::entrainer();

        Storage::disk('local')->assertExists(DetectionContamination::FICHIER);
        $this->assertSame(60, $modele['donnees']['total']);
        $this->assertSame(20, $modele['donnees']['positifs']);
        $this->assertSame(12, $modele['donnees']['test']);
        $this->assertSame(1.0, $modele['metriques']['rappel']);   // règle nette : toutes les contaminations détectées

        $risque = DetectionContamination::predire(['ph' => 7.2, 'chlore_residuel' => 0.05, 'turbidite' => 4.5, 'nitrates' => 12], 'robinet_public', now());
        $sain = DetectionContamination::predire(['ph' => 7.2, 'chlore_residuel' => 0.5, 'turbidite' => 0.8, 'nitrates' => 12], 'robinet_public', now());

        $this->assertGreaterThan(0.8, $risque['probabilite']);
        $this->assertSame('Risque élevé', $risque['niveau']['libelle']);
        $this->assertLessThan(0.15, $sain['probabilite']);
        $this->assertNotEmpty($risque['raisons']);
        $this->assertMatchesRegularExpression('/^(Chlore résiduel|Turbidité) (≤|>) /', $risque['raisons'][0]);
    }

    public function test_le_modele_refuse_de_s_entrainer_sans_assez_de_donnees(): void
    {
        $this->creerAnalyse($this->creerPoint());

        $this->assertNull(DetectionContamination::predire(['ph' => 7, 'chlore_residuel' => 0.3, 'turbidite' => 1, 'nitrates' => 10], 'reservoir', now()));
        $this->artisan('qualite:entrainer')->assertFailed();

        $this->expectException(\RuntimeException::class);
        DetectionContamination::entrainer();
    }

    public function test_la_commande_d_entrainement(): void
    {
        $this->creerHistoriqueContamination();

        $this->artisan('qualite:entrainer')
            ->expectsOutputToContain('Modèle entraîné')
            ->assertSuccessful();
    }

    public function test_la_page_detection_de_contamination(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get(route('admin.detection.index'))
            ->assertOk()
            ->assertSee("Le modèle n'a pas encore été entraîné", false);

        $this->creerHistoriqueContamination();

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.detection.entrainer'))
            ->assertRedirect(route('admin.detection.index'))
            ->assertSessionHas('success');

        $this->actingAs($this->gestionnaire)
            ->get(route('admin.detection.index'))
            ->assertOk()
            ->assertSee('Matrice de confusion')
            ->assertSee('Règles apprises par le modèle')
            ->assertSee('Chlore résiduel');
    }

    public function test_un_citoyen_n_a_pas_acces_a_la_detection_de_contamination(): void
    {
        $citoyen = User::factory()->create(['role' => 'citoyen']);

        $this->actingAs($citoyen)->get(route('admin.detection.index'))->assertForbidden();
        $this->actingAs($citoyen)->post(route('admin.detection.entrainer'))->assertForbidden();
    }

    public function test_la_prediction_ia_apparait_dans_le_formulaire_et_le_bulletin(): void
    {
        $point = $this->creerHistoriqueContamination();
        DetectionContamination::entrainer();
        $analyse = $point->analyses()->where('bacteries_coliformes', '>', 0)->first();

        $this->actingAs($this->gestionnaire);
        $this->get(route('admin.analyses.create'))
            ->assertOk()
            ->assertSee('prediction-ia-resultat')
            ->assertViewHas('modeleIA', fn ($m) => isset($m['arbre']['variable']))
            ->assertViewHas('typesPoints', [$point->id => 'robinet_public']);
        $this->get(route('admin.analyses.show', $analyse))
            ->assertOk()
            ->assertSee('Détection de contamination (IA)')
            ->assertSee('Prédiction juste');
    }

    // ---------- Front office : consultation par les citoyens ----------

    public function test_un_visiteur_doit_se_connecter_pour_voir_la_qualite_de_l_eau(): void
    {
        $this->get(route('qualite.index'))->assertRedirect(route('login'));
    }

    public function test_un_citoyen_consulte_la_qualite_de_l_eau(): void
    {
        $citoyen = User::factory()->create(['role' => 'citoyen']);
        $bon = $this->creerPoint(['nom' => 'Fontaine saine', 'latitude' => 36.8, 'longitude' => 10.18]);
        $mauvais = $this->creerPoint(['code' => 'PP-TN-020', 'nom' => 'Fontaine contaminée']);
        $this->creerPoint(['code' => 'PP-TN-030', 'nom' => 'Ancienne fontaine', 'actif' => false]);
        $this->creerAnalyse($bon);
        $this->creerAnalyse($mauvais, ['date_prelevement' => now()->subMonths(2)->toDateString()]);
        $this->creerAnalyse($mauvais, ['bacteries_coliformes' => 8]);   // la plus récente : non conforme

        $this->actingAs($citoyen)
            ->get(route('qualite.index'))
            ->assertOk()
            ->assertSee('Fontaine saine')
            ->assertSee('Fontaine contaminée')
            ->assertDontSee('Ancienne fontaine')                   // point hors service caché
            ->assertDontSee(route('admin.analyses.create'))        // aucune action de gestion
            ->assertViewHas('indicateurs', fn ($i) => $i['points'] === 2 && $i['bonnes'] === 1 && $i['mauvaises'] === 1)
            ->assertViewHas('carte', fn ($carte) => count($carte) === 1 && $carte[0]['etat'] === 'conforme');

        $this->actingAs($citoyen)
            ->get(route('qualite.show', $mauvais))
            ->assertOk()
            ->assertSee('Eau non conforme')
            ->assertSee('Bactéries coliformes présentes')
            ->assertSee('faites bouillir', false);
    }

    public function test_la_qualite_de_l_eau_se_filtre_par_commune(): void
    {
        $citoyen = User::factory()->create(['role' => 'citoyen']);
        $this->creerPoint(['nom' => 'Fontaine de Tunis']);
        $sousse = Zone::create(['nom' => 'Sahloul', 'commune' => 'Sousse', 'population' => 31000]);
        $reseauSousse = $sousse->reseaux()->create([
            'code' => 'STA-SOU-001', 'type' => 'station', 'materiau' => 'acier',
            'date_mise_en_service' => '2001-01-01', 'etat' => 'bon',
        ]);
        $this->creerPoint(['code' => 'PP-SOU-001', 'nom' => 'Sortie station Sahloul', 'reseau_id' => $reseauSousse->id]);

        $this->actingAs($citoyen)
            ->get(route('qualite.index', ['commune' => 'Sousse']))
            ->assertSee('Sortie station Sahloul')
            ->assertDontSee('Fontaine de Tunis')
            ->assertViewHas('communes', ['Sousse', 'Tunis']);
    }

    public function test_un_point_hors_service_n_est_pas_visible_des_citoyens(): void
    {
        $citoyen = User::factory()->create(['role' => 'citoyen']);
        $point = $this->creerPoint(['actif' => false]);

        $this->actingAs($citoyen)->get(route('qualite.show', $point))->assertNotFound();
    }

    public function test_le_menu_du_site_public_mene_a_la_qualite_de_l_eau(): void
    {
        $citoyen = User::factory()->create(['role' => 'citoyen']);

        $this->actingAs($citoyen)->get(route('home'))->assertSee(route('qualite.index'));
        auth()->logout();
        $this->get(route('home'))->assertDontSee(route('qualite.index'));
    }

    // ---------- Données de démonstration ----------

    public function test_le_seeder_peut_etre_relance_sans_doublons(): void
    {
        $this->seed(InfrastructureSeeder::class);
        $this->seed(QualiteEauSeeder::class);

        $nbPoints = PointPrelevement::count();
        $nbAnalyses = AnalyseQualite::count();
        $this->assertGreaterThanOrEqual(10, $nbPoints);
        $this->assertGreaterThanOrEqual(70, $nbAnalyses);
        $this->assertTrue(AnalyseQualite::where('conforme', false)->exists());
        $this->assertTrue(AnalyseQualite::where('conforme', true)->exists());

        $this->seed(QualiteEauSeeder::class);

        $this->assertSame($nbPoints, PointPrelevement::count());
        $this->assertSame($nbAnalyses, AnalyseQualite::count());
    }
}
