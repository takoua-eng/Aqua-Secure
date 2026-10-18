<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\DonneesIncidents;
use Tests\TestCase;

// Pages et API des incidents (binôme 2). Base gestion_eau_test uniquement (phpunit.xml).
class IncidentHttpTest extends TestCase
{
    use RefreshDatabase, DonneesIncidents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exigerRoutesDuModule();
        $this->preparerDonnees();
    }

    private function donnees(array $valeurs = []): array
    {
        return $valeurs + [
            'reseau_id' => $this->reseau->id, 'type' => 'fuite', 'gravite' => 'moyenne',
            'date_signalement' => today()->subDay()->toDateString(),
        ];
    }

    // ---------- Accès et rôles ----------

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('admin.incidents.index'))->assertRedirect(route('login'));
        $this->post(route('admin.incidents.store'), $this->donnees())->assertRedirect(route('login'));
    }

    public function test_un_citoyen_n_a_pas_acces_au_module(): void
    {
        $incident = $this->creerIncident();

        $this->actingAs($this->citoyen)->get(route('admin.incidents.index'))->assertForbidden();
        $this->actingAs($this->citoyen)->get(route('admin.incidents.show', $incident))->assertForbidden();
        $this->actingAs($this->citoyen)->post(route('admin.incidents.store'), $this->donnees())->assertForbidden();
        $this->assertSame(1, Incident::count());
    }

    public function test_gestionnaire_et_admin_accedent_aux_pages(): void
    {
        $incident = $this->creerIncident();

        foreach ([$this->gestionnaire, $this->admin] as $utilisateur) {
            $this->actingAs($utilisateur);
            $this->get(route('admin.incidents.index'))->assertOk()->assertSee('#' . $incident->id);
            $this->get(route('admin.incidents.create'))->assertOk();
            $this->get(route('admin.incidents.show', $incident))->assertOk()->assertSee('Priorité de traitement');
            $this->get(route('admin.incidents.edit', $incident))->assertOk();
        }
    }

    public function test_liste_vide_affiche_un_etat_vide_avec_bouton(): void
    {
        $this->actingAs($this->gestionnaire)->get(route('admin.incidents.index'))
            ->assertOk()
            ->assertSee('Aucun incident enregistré')
            ->assertSee('Ajouter un incident');
    }

    // ---------- Création et validation ----------

    public function test_creation_d_un_incident(): void
    {
        $reponse = $this->actingAs($this->gestionnaire)->post(route('admin.incidents.store'), $this->donnees(['type' => 'contamination']));

        $incident = Incident::sole();
        $reponse->assertRedirect(route('admin.incidents.show', $incident))->assertSessionHas('success');
        $this->assertSame('contamination', $incident->type);
        $this->assertSame('non_traite', $incident->statutActuel());
    }

    public function test_champs_obligatoires_et_valeurs_autorisees(): void
    {
        $this->actingAs($this->gestionnaire)->post(route('admin.incidents.store'), [])
            ->assertSessionHasErrors(['reseau_id', 'type', 'gravite', 'date_signalement']);

        $this->actingAs($this->gestionnaire)->post(route('admin.incidents.store'), $this->donnees([
            'type' => 'inondation', 'gravite' => 'extreme', 'reseau_id' => 999999,
        ]))->assertSessionHasErrors(['type', 'gravite', 'reseau_id']);

        $this->assertSame(0, Incident::count());
    }

    public function test_la_date_de_signalement_ne_peut_pas_etre_dans_le_futur(): void
    {
        $this->actingAs($this->gestionnaire)
            ->post(route('admin.incidents.store'), $this->donnees(['date_signalement' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors('date_signalement');
    }

    public function test_la_date_de_signalement_ne_peut_pas_preceder_la_mise_en_service(): void
    {
        $this->actingAs($this->gestionnaire)
            ->post(route('admin.incidents.store'), $this->donnees(['date_signalement' => '1989-12-31']))
            ->assertSessionHasErrors('date_signalement');
    }

    public function test_avertissement_si_un_incident_similaire_non_resolu_existe(): void
    {
        $existant = $this->creerIncident(['type' => 'fuite', 'date_signalement' => today()->subDays(2)->toDateString()]);

        $this->actingAs($this->gestionnaire)->post(route('admin.incidents.store'), $this->donnees(['type' => 'fuite']))
            ->assertSessionHas('avertissement', fn ($message) => str_contains($message, '' . $existant->id));

        // L'incident est quand même enregistré : simple avertissement
        $this->assertSame(2, Incident::count());
    }

    // ---------- Modification ----------

    public function test_modification_d_un_incident(): void
    {
        $incident = $this->creerIncident();

        $this->actingAs($this->gestionnaire)
            ->put(route('admin.incidents.update', $incident), $this->donnees(['gravite' => 'critique']))
            ->assertRedirect(route('admin.incidents.show', $incident));

        $this->assertSame('critique', $incident->fresh()->gravite);
    }

    public function test_le_reseau_ne_change_plus_quand_une_intervention_a_commence(): void
    {
        $incident = $this->creerIncident();
        $this->creerIntervention($incident, 'en_cours');
        $autre = $this->autreReseau();

        $this->actingAs($this->gestionnaire)
            ->put(route('admin.incidents.update', $incident), $this->donnees(['reseau_id' => $autre->id]))
            ->assertSessionHasErrors('reseau_id');

        $this->assertSame($this->reseau->id, (int) $incident->fresh()->reseau_id);
    }

    // ---------- Suppression ----------

    public function test_seul_l_administrateur_peut_supprimer(): void
    {
        $incident = $this->creerIncident();

        $this->actingAs($this->gestionnaire)->delete(route('admin.incidents.destroy', $incident))->assertForbidden();
        $this->assertModelExists($incident);

        $this->actingAs($this->admin)->delete(route('admin.incidents.destroy', $incident))
            ->assertRedirect(route('admin.incidents.index'));
        $this->assertModelMissing($incident);
    }

    public function test_un_incident_avec_interventions_n_est_pas_supprime(): void
    {
        $incident = $this->creerIncident();
        $this->creerIntervention($incident);

        $this->actingAs($this->admin)->from(route('admin.incidents.show', $incident))
            ->delete(route('admin.incidents.destroy', $incident))
            ->assertRedirect(route('admin.incidents.show', $incident))
            ->assertSessionHasErrors('incident');

        $this->assertModelExists($incident);
    }

    // ---------- Recherche, filtres, tri, pagination (vérifiés en base, via l'API JSON) ----------

    private function idsListe(array $parametres): array
    {
        return collect($this->actingAs($this->gestionnaire)
            ->getJson(route('admin.incidents.index', $parametres))
            ->assertOk()
            ->json('data'))->pluck('id')->all();
    }

    public function test_filtres_par_type_gravite_reseau_et_periode(): void
    {
        $autre = $this->autreReseau();
        $fuite = $this->creerIncident(['type' => 'fuite', 'gravite' => 'faible', 'date_signalement' => today()->subDays(40)->toDateString()]);
        $coupure = $this->creerIncident(['type' => 'coupure', 'gravite' => 'critique', 'reseau_id' => $autre->id]);

        $this->assertEquals([$fuite->id], $this->idsListe(['type' => 'fuite']));
        $this->assertEquals([$coupure->id], $this->idsListe(['gravite' => 'critique']));
        $this->assertEquals([$coupure->id], $this->idsListe(['reseau' => $autre->id]));
        $this->assertEquals([$coupure->id], $this->idsListe(['du' => today()->subDays(10)->toDateString()]));
        $this->assertEquals([$fuite->id], $this->idsListe(['au' => today()->subDays(10)->toDateString()]));
    }

    public function test_filtre_par_statut_calcule(): void
    {
        $nonTraite = $this->creerIncident();
        $enTraitement = $this->creerIncident();
        $this->creerIntervention($enTraitement);
        $resolu = $this->creerIncident();
        $this->creerIntervention($resolu, 'terminee');

        $this->assertEquals([$nonTraite->id], $this->idsListe(['statut' => 'non_traite']));
        $this->assertEquals([$enTraitement->id], $this->idsListe(['statut' => 'en_traitement']));
        $this->assertEquals([$resolu->id], $this->idsListe(['statut' => 'resolu']));
    }

    public function test_recherche_par_code_reseau_par_zone_et_par_numero(): void
    {
        $autreZone = Zone::create(['nom' => 'Sahloul', 'commune' => 'Sousse', 'population' => 31000]);
        $reseauSousse = $autreZone->reseaux()->create([
            'code' => 'STA-SOU-001', 'type' => 'station', 'materiau' => 'acier', 'date_mise_en_service' => '2001-07-22', 'etat' => 'bon',
        ]);
        $tunis = $this->creerIncident();
        $sousse = $this->creerIncident(['reseau_id' => $reseauSousse->id]);

        $this->assertEquals([$sousse->id], $this->idsListe(['q' => 'STA-SOU']));
        $this->assertEquals([$sousse->id], $this->idsListe(['q' => 'Sahloul']));
        $this->assertEquals([$tunis->id], $this->idsListe(['q' => 'Tunis']));
        $this->assertEquals([$tunis->id], $this->idsListe(['zone' => $this->zone->id]));
        $this->assertEquals([$sousse->id], $this->idsListe(['q' => '#' . $sousse->id]));
    }

    public function test_filtre_des_critiques_sans_intervention(): void
    {
        $alerte = $this->creerIncident(['gravite' => 'critique', 'date_signalement' => today()->subDays(2)->toDateString()]);
        $this->creerIncident(['gravite' => 'moyenne']);

        $this->assertEquals([$alerte->id], $this->idsListe(['critiques' => 1]));
    }

    public function test_tri_par_gravite_et_par_priorite(): void
    {
        $faible = $this->creerIncident(['gravite' => 'faible']);
        $critique = $this->creerIncident(['gravite' => 'critique', 'type' => 'contamination']);
        $moyenne = $this->creerIncident(['gravite' => 'moyenne']);

        $this->assertEquals([$critique->id, $moyenne->id, $faible->id], $this->idsListe(['tri' => 'gravite']));
        $this->assertEquals([$faible->id, $moyenne->id, $critique->id], $this->idsListe(['tri' => 'gravite', 'ordre' => 'asc']));
        $this->assertSame($critique->id, $this->idsListe(['tri' => 'priorite'])[0]);
    }

    public function test_pagination_par_15(): void
    {
        foreach (range(1, 17) as $i) {
            $this->creerIncident();
        }

        $page1 = $this->actingAs($this->gestionnaire)->getJson(route('admin.incidents.index'))->assertOk();
        $this->assertCount(15, $page1->json('data'));
        $this->assertSame(17, $page1->json('meta.total'));
        $this->assertCount(2, $this->idsListe(['page' => 2]));

        // Même pagination pour le tri par priorité (calculé en PHP)
        $this->assertCount(2, $this->idsListe(['tri' => 'priorite', 'page' => 2]));
    }

    public function test_des_filtres_invalides_sont_ignores_sans_erreur(): void
    {
        $this->creerIncident();

        $this->actingAs($this->gestionnaire)
            ->get(route('admin.incidents.index') . '?type[]=x&gravite=extreme&du=pas-une-date&reseau=abc&tri=hack&ordre=1')
            ->assertOk()
            ->assertSee('#');
    }

    public function test_la_fiche_json_contient_le_statut_et_la_priorite(): void
    {
        $incident = $this->creerIncident(['gravite' => 'critique']);
        $this->creerIntervention($incident, 'en_cours');

        $this->actingAs($this->gestionnaire)->getJson(route('admin.incidents.show', $incident))
            ->assertOk()
            ->assertJsonPath('data.statut.valeur', 'en_traitement')
            ->assertJsonPath('data.gravite.valeur', 'critique')
            ->assertJsonStructure(['data' => ['priorite' => ['score', 'niveau', 'facteurs', 'action'], 'interventions']]);
    }
}
