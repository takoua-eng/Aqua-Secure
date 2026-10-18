<?php

namespace Tests\Feature;

use App\Exceptions\RegleIncidentException;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\ReseauEau;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Modèles, relations et règles métier du module Incidents / Interventions (binôme 2).
// Base gestion_eau_test uniquement (phpunit.xml) : RefreshDatabase la vide à chaque test.
class IncidentInterventionModeleTest extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;
    private ReseauEau $reseau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestionnaire = User::factory()->create(['role' => 'gestionnaire']);
        $zone = Zone::create(['nom' => 'Centre-ville', 'commune' => 'Tunis', 'population' => 42000]);
        $this->reseau = $zone->reseaux()->create([
            'code' => 'CAN-TUN-001', 'type' => 'canalisation', 'materiau' => 'fonte',
            'date_mise_en_service' => '1990-01-01', 'etat' => 'bon',
        ]);
    }

    // ---------- Outils ----------

    private function creerIncident(array $valeurs = []): Incident
    {
        return Incident::create($valeurs + [
            'reseau_id' => $this->reseau->id, 'type' => 'fuite', 'gravite' => 'moyenne',
            'date_signalement' => today()->subDays(3)->toDateString(),
        ]);
    }

    private function creerIntervention(Incident $incident, string $statut = 'planifiee', ?string $date = null): Intervention
    {
        return Intervention::create([
            'incident_id' => $incident->id, 'statut' => $statut,
            'date_intervention' => $date ?? today()->toDateString(),
        ]);
    }

    // ---------- Relations ----------

    public function test_un_incident_appartient_a_un_reseau_et_possede_des_interventions(): void
    {
        $incident = $this->creerIncident();
        $intervention = $this->creerIntervention($incident);
        $this->creerIntervention($incident, 'en_cours');

        $this->assertTrue($incident->reseau->is($this->reseau));
        $this->assertCount(2, $incident->interventions);
        $this->assertTrue($intervention->incident->is($incident));
    }

    // ---------- Statut calculé ----------

    public function test_le_statut_d_un_incident_suit_ses_interventions(): void
    {
        $incident = $this->creerIncident();
        $this->assertSame('non_traite', $incident->statutActuel());

        $intervention = $this->creerIntervention($incident);
        $this->assertSame('en_traitement', $incident->statutActuel());

        $intervention->passerA('terminee');
        $this->assertSame('resolu', $incident->statutActuel());
        $this->assertTrue($incident->estResolu());
    }

    // Décision R1
    public function test_une_nouvelle_intervention_rouvre_un_incident_resolu(): void
    {
        $incident = $this->creerIncident();
        $this->creerIntervention($incident, 'terminee');
        $this->assertSame('resolu', $incident->statutActuel());

        $this->creerIntervention($incident, 'planifiee', today()->addDay()->toDateString());

        $this->assertSame('en_traitement', $incident->statutActuel());
    }

    public function test_les_filtres_de_statut_correspondent_au_statut_calcule(): void
    {
        $nonTraite = $this->creerIncident();
        $enTraitement = $this->creerIncident();
        $this->creerIntervention($enTraitement, 'terminee');
        $this->creerIntervention($enTraitement, 'en_cours');
        $resolu = $this->creerIncident();
        $this->creerIntervention($resolu, 'terminee');

        $this->assertEquals([$nonTraite->id], Incident::avecStatut('non_traite')->pluck('id')->all());
        $this->assertEquals([$enTraitement->id], Incident::avecStatut('en_traitement')->pluck('id')->all());
        $this->assertEquals([$resolu->id], Incident::avecStatut('resolu')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$nonTraite->id, $enTraitement->id], Incident::nonResolus()->pluck('id')->all());
        // Valeur inconnue : le filtre est ignoré
        $this->assertSame(3, Incident::avecStatut('nimporte_quoi')->count());
    }

    // ---------- Historique des statuts ----------

    public function test_la_creation_et_chaque_changement_de_statut_sont_historises(): void
    {
        $this->actingAs($this->gestionnaire);
        $incident = $this->creerIncident();
        $intervention = $this->creerIntervention($incident);

        $intervention->passerA('en_cours');
        $intervention->passerA('terminee');

        $historique = $intervention->historiques()->get()->reverse()->values();
        $this->assertSame(
            [[null, 'planifiee'], ['planifiee', 'en_cours'], ['en_cours', 'terminee']],
            $historique->map(fn ($h) => [$h->ancien_statut, $h->nouveau_statut])->all()
        );
        $this->assertTrue($historique->every(fn ($h) => (int) $h->user_id === $this->gestionnaire->id));
    }

    public function test_un_changement_de_date_seul_n_est_pas_historise(): void
    {
        $intervention = $this->creerIntervention($this->creerIncident(), 'planifiee', today()->addDays(2)->toDateString());

        $intervention->update(['date_intervention' => today()->addDays(5)->toDateString()]);

        $this->assertSame(1, $intervention->historiques()->count());
    }

    // ---------- Transitions (T5, T6, T7) ----------

    public function test_un_retour_en_arriere_est_refuse_et_rien_n_est_modifie(): void
    {
        $intervention = $this->creerIntervention($this->creerIncident(), 'en_cours');

        try {
            $intervention->passerA('planifiee');
            $this->fail('La transition en_cours → planifiee aurait dû être refusée.');
        } catch (RegleIncidentException) {
        }

        $this->assertSame('en_cours', $intervention->fresh()->statut);
        $this->assertSame(1, $intervention->historiques()->count());
    }

    public function test_une_modification_directe_du_statut_respecte_aussi_les_transitions(): void
    {
        $intervention = $this->creerIntervention($this->creerIncident(), 'en_cours');

        $this->expectException(RegleIncidentException::class);
        $intervention->update(['statut' => 'planifiee']);
    }

    public function test_demarrer_une_intervention_prevue_dans_le_futur_prend_la_date_du_jour(): void
    {
        $intervention = $this->creerIntervention($this->creerIncident(), 'planifiee', today()->addDays(5)->toDateString());

        $intervention->passerA('en_cours');

        $this->assertTrue($intervention->fresh()->date_intervention->isSameDay(today()));
    }

    public function test_demarrer_une_intervention_en_retard_garde_sa_date(): void
    {
        $prevue = today()->subDays(2);
        $intervention = $this->creerIntervention($this->creerIncident(), 'planifiee', $prevue->toDateString());

        $intervention->passerA('en_cours');

        $this->assertTrue($intervention->fresh()->date_intervention->isSameDay($prevue));
    }

    public function test_une_intervention_terminee_ne_peut_plus_etre_modifiee(): void
    {
        $intervention = $this->creerIntervention($this->creerIncident(), 'terminee');
        $this->assertFalse($intervention->estModifiable());

        $this->expectException(RegleIncidentException::class);
        $intervention->update(['date_intervention' => today()->subDay()->toDateString()]);
    }

    // ---------- Verrous (T1, I3) ----------

    public function test_l_incident_d_une_intervention_ne_peut_pas_changer(): void
    {
        $intervention = $this->creerIntervention($this->creerIncident());
        $autre = $this->creerIncident(['type' => 'coupure']);

        $this->expectException(RegleIncidentException::class);
        $intervention->update(['incident_id' => $autre->id]);
    }

    public function test_le_reseau_d_un_incident_est_verrouille_des_qu_une_intervention_a_commence(): void
    {
        $autreReseau = $this->reseau->zone->reseaux()->create([
            'code' => 'RES-TUN-002', 'type' => 'reservoir', 'materiau' => 'beton',
            'date_mise_en_service' => '2005-01-01', 'etat' => 'bon',
        ]);

        // Sans intervention : on peut corriger le réseau
        $incident = $this->creerIncident();
        $this->assertTrue($incident->peutChangerDeReseau());
        $incident->update(['reseau_id' => $autreReseau->id]);
        $this->assertSame($autreReseau->id, (int) $incident->fresh()->reseau_id);

        // Intervention seulement planifiée : encore modifiable
        $intervention = $this->creerIntervention($incident, 'planifiee', today()->addDay()->toDateString());
        $this->assertTrue($incident->peutChangerDeReseau());
        $incident->update(['reseau_id' => $this->reseau->id]);

        // Intervention commencée : refusé
        $intervention->passerA('en_cours');
        $this->assertFalse($incident->peutChangerDeReseau());
        $this->expectException(RegleIncidentException::class);
        $incident->update(['reseau_id' => $autreReseau->id]);
    }

    public function test_le_type_et_la_gravite_restent_modifiables_apres_une_intervention(): void
    {
        $incident = $this->creerIncident();
        $this->creerIntervention($incident);

        $incident->update(['type' => 'contamination', 'gravite' => 'critique']);

        $this->assertSame('critique', $incident->fresh()->gravite);
    }

    // ---------- Suppressions (I5, T8) et clés étrangères ----------

    public function test_un_incident_sans_intervention_peut_etre_supprime(): void
    {
        $incident = $this->creerIncident();
        $this->assertTrue($incident->peutEtreSupprime());

        $incident->delete();

        $this->assertModelMissing($incident);
    }

    public function test_un_incident_avec_interventions_ne_peut_pas_etre_supprime(): void
    {
        $incident = $this->creerIncident();
        $this->creerIntervention($incident, 'terminee');
        $this->assertFalse($incident->peutEtreSupprime());

        $this->expectException(RegleIncidentException::class);
        $incident->delete();
    }

    public function test_la_base_refuse_aussi_de_supprimer_un_incident_qui_a_des_interventions(): void
    {
        $incident = $this->creerIncident();
        $this->creerIntervention($incident);

        // Sans passer par le modèle : c'est la clé étrangère restrictOnDelete qui protège
        $this->expectException(QueryException::class);
        DB::table('incidents')->where('id', $incident->id)->delete();
    }

    public function test_seule_une_intervention_planifiee_peut_etre_supprimee(): void
    {
        $incident = $this->creerIncident();
        $planifiee = $this->creerIntervention($incident, 'planifiee', today()->addDay()->toDateString());
        $enCours = $this->creerIntervention($incident, 'en_cours');

        $planifiee->delete();
        $this->assertModelMissing($planifiee);
        $this->assertSame(0, DB::table('historique_interventions')->where('intervention_id', $planifiee->id)->count());

        $this->expectException(RegleIncidentException::class);
        $enCours->delete();
    }

    public function test_la_base_refuse_de_supprimer_un_reseau_qui_a_des_incidents(): void
    {
        $this->creerIncident();

        $this->expectException(QueryException::class);
        $this->reseau->delete();
    }

    // ---------- Détections (I6, I7) et tris ----------

    public function test_detection_des_incidents_critiques_sans_intervention(): void
    {
        $alerte = $this->creerIncident(['gravite' => 'critique', 'date_signalement' => today()->subDays(2)->toDateString()]);
        $this->creerIncident(['gravite' => 'critique', 'date_signalement' => today()->toDateString()]);  // signalé aujourd'hui
        $pris = $this->creerIncident(['gravite' => 'critique', 'date_signalement' => today()->subDays(2)->toDateString()]);
        $this->creerIntervention($pris);
        $this->creerIncident(['gravite' => 'moyenne', 'date_signalement' => today()->subDays(5)->toDateString()]);

        $this->assertEquals([$alerte->id], Incident::critiquesSansIntervention()->pluck('id')->all());
        $this->assertTrue($alerte->estEnAlerteCritique());
        $this->assertFalse($pris->estEnAlerteCritique());
    }

    public function test_detection_des_doublons_potentiels(): void
    {
        $existant = $this->creerIncident(['type' => 'fuite', 'date_signalement' => today()->subDays(3)->toDateString()]);
        $this->creerIncident(['type' => 'fuite', 'date_signalement' => today()->subDays(20)->toDateString()]);  // trop ancien

        $aujourdhui = today()->toDateString();
        $this->assertEquals([$existant->id], Incident::doublonsPotentiels($this->reseau->id, 'fuite', $aujourdhui)->pluck('id')->all());
        $this->assertSame(0, Incident::doublonsPotentiels($this->reseau->id, 'coupure', $aujourdhui)->count());
        $this->assertSame(0, Incident::doublonsPotentiels($this->reseau->id, 'fuite', $aujourdhui, $existant->id)->count());

        // Un incident résolu n'est plus un doublon
        $this->creerIntervention($existant, 'terminee');
        $this->assertSame(0, Incident::doublonsPotentiels($this->reseau->id, 'fuite', $aujourdhui)->count());
    }

    public function test_tri_par_niveau_de_gravite(): void
    {
        $this->creerIncident(['gravite' => 'faible']);
        $this->creerIncident(['gravite' => 'critique']);
        $this->creerIncident(['gravite' => 'moyenne']);

        $this->assertSame(['critique', 'moyenne', 'faible'], Incident::ordreGravite()->pluck('gravite')->all());
        $this->assertSame(['faible', 'moyenne', 'critique'], Incident::ordreGravite('asc')->pluck('gravite')->all());
    }

    public function test_detection_des_interventions_en_retard(): void
    {
        $incident = $this->creerIncident(['date_signalement' => today()->subDays(10)->toDateString()]);
        $enRetard = $this->creerIntervention($incident, 'planifiee', today()->subDays(2)->toDateString());
        $this->creerIntervention($incident, 'planifiee', today()->addDays(2)->toDateString());
        $this->creerIntervention($incident, 'en_cours', today()->subDays(2)->toDateString());

        $this->assertEquals([$enRetard->id], Intervention::enRetard()->pluck('id')->all());
        $this->assertTrue($enRetard->estEnRetard());
    }

    // ---------- Fabriques ----------

    public function test_les_fabriques_produisent_des_donnees_valides(): void
    {
        $incident = Incident::factory()->pourReseau($this->reseau)->critique()->create();
        $planifiee = Intervention::factory()->pourIncident($incident)->create();
        $enCours = Intervention::factory()->pourIncident($incident)->enCours()->create();

        $this->assertArrayHasKey($incident->type, Incident::TYPES);
        $this->assertSame('critique', $incident->gravite);
        $this->assertTrue($incident->date_signalement->lte(today()));
        $this->assertTrue($planifiee->date_intervention->gt(today()));
        $this->assertFalse($enCours->date_intervention->lt($incident->date_signalement));
    }
}
