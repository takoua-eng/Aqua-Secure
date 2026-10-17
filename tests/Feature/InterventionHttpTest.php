<?php

namespace Tests\Feature;

use App\Models\HistoriqueIntervention;
use App\Models\Intervention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\DonneesIncidents;
use Tests\TestCase;

// Pages et actions des interventions (binôme 2). Base gestion_eau_test uniquement.
class InterventionHttpTest extends TestCase
{
    use RefreshDatabase, DonneesIncidents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exigerRoutesDuModule();
        $this->preparerDonnees();
    }

    // ---------- Accès ----------

    public function test_un_citoyen_n_a_pas_acces_aux_interventions(): void
    {
        $intervention = $this->creerIntervention($this->creerIncident());

        $this->actingAs($this->citoyen)->get(route('admin.interventions.index'))->assertForbidden();
        $this->actingAs($this->citoyen)->patch(route('admin.interventions.statut', $intervention), ['statut' => 'en_cours'])->assertForbidden();
        $this->assertSame('planifiee', $intervention->fresh()->statut);
    }

    public function test_les_pages_s_affichent(): void
    {
        $incident = $this->creerIncident();
        $intervention = $this->creerIntervention($incident);
        $this->actingAs($this->gestionnaire);

        $this->get(route('admin.interventions.index'))->assertOk()->assertSee('#' . $intervention->id);
        $this->get(route('admin.incidents.interventions.create', $incident))->assertOk();
        $this->get(route('admin.interventions.show', $intervention))->assertOk()->assertSee('Changer le statut');
        $this->get(route('admin.interventions.edit', $intervention))->assertOk();
        $this->get(route('admin.interventions.historique'))->assertOk()->assertSee('Création');
    }

    // ---------- Création ----------

    public function test_creation_depuis_un_incident_et_historique(): void
    {
        $incident = $this->creerIncident();

        $reponse = $this->actingAs($this->gestionnaire)->post(route('admin.incidents.interventions.store', $incident), [
            'statut' => 'planifiee', 'date_intervention' => today()->addDays(2)->toDateString(),
        ]);

        $intervention = Intervention::sole();
        $reponse->assertRedirect(route('admin.interventions.show', $intervention));
        $this->assertSame($incident->id, (int) $intervention->incident_id);
        $this->assertSame('en_traitement', $incident->statutActuel());

        $ligne = HistoriqueIntervention::sole();
        $this->assertNull($ligne->ancien_statut);
        $this->assertSame('planifiee', $ligne->nouveau_statut);
        $this->assertSame($this->gestionnaire->id, (int) $ligne->user_id);
    }

    public function test_regles_de_date_a_la_creation(): void
    {
        $incident = $this->creerIncident(['date_signalement' => today()->subDays(5)->toDateString()]);
        $this->actingAs($this->gestionnaire);
        $poster = fn (string $statut, $date) => $this->post(route('admin.incidents.interventions.store', $incident), [
            'statut' => $statut, 'date_intervention' => $date->toDateString(),
        ]);

        // T3 : planifiée dans le passé
        $poster('planifiee', today()->subDay())->assertSessionHasErrors('date_intervention');
        // T4 : en cours / terminée dans le futur
        $poster('en_cours', today()->addDay())->assertSessionHasErrors('date_intervention');
        $poster('terminee', today()->addDay())->assertSessionHasErrors('date_intervention');
        // T2 : avant le signalement
        $poster('terminee', today()->subDays(6))->assertSessionHasErrors('date_intervention');
        // Statut inconnu
        $poster('annulee', today())->assertSessionHasErrors('statut');

        $this->assertSame(0, Intervention::count());

        // Saisie a posteriori d'une intervention terminée : acceptée
        $poster('terminee', today()->subDays(2))->assertSessionHasNoErrors();
        $this->assertSame(1, Intervention::count());
    }

    public function test_une_intervention_rouvre_un_incident_resolu(): void
    {
        $incident = $this->creerIncident();
        $this->creerIntervention($incident, 'terminee');
        $this->assertTrue($incident->estResolu());

        $this->actingAs($this->gestionnaire)->post(route('admin.incidents.interventions.store', $incident), [
            'statut' => 'planifiee', 'date_intervention' => today()->addDay()->toDateString(),
        ])->assertSessionHas('success', fn ($message) => str_contains($message, 'repasse'));

        $this->assertSame('en_traitement', $incident->statutActuel());
    }

    // ---------- Modification ----------

    public function test_seule_la_date_se_modifie_dans_le_formulaire(): void
    {
        $intervention = $this->creerIntervention($this->creerIncident());
        $nouvelleDate = today()->addDays(5)->toDateString();

        $this->actingAs($this->gestionnaire)
            ->put(route('admin.interventions.update', $intervention), ['date_intervention' => $nouvelleDate, 'statut' => 'terminee'])
            ->assertSessionHasErrors('statut');

        $this->actingAs($this->gestionnaire)
            ->put(route('admin.interventions.update', $intervention), ['date_intervention' => $nouvelleDate])
            ->assertRedirect(route('admin.interventions.show', $intervention));

        $intervention->refresh();
        $this->assertSame('planifiee', $intervention->statut);
        $this->assertSame($nouvelleDate, $intervention->date_intervention->toDateString());
    }

    public function test_une_intervention_terminee_ne_se_modifie_plus(): void
    {
        $intervention = $this->creerIntervention($this->creerIncident(), 'terminee');

        $this->actingAs($this->gestionnaire)->get(route('admin.interventions.edit', $intervention))
            ->assertRedirect(route('admin.interventions.show', $intervention))
            ->assertSessionHasErrors('intervention');

        $this->actingAs($this->gestionnaire)
            ->put(route('admin.interventions.update', $intervention), ['date_intervention' => today()->toDateString()])
            ->assertSessionHasErrors();
    }

    // ---------- Changement de statut ----------

    public function test_transitions_autorisees_et_historique(): void
    {
        $intervention = $this->creerIntervention($this->creerIncident());
        $this->actingAs($this->gestionnaire);

        $this->patch(route('admin.interventions.statut', $intervention), ['statut' => 'en_cours'])
            ->assertRedirect(route('admin.interventions.show', $intervention));
        $this->patch(route('admin.interventions.statut', $intervention), ['statut' => 'terminee'])
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'résolu'));

        $this->assertSame('terminee', $intervention->fresh()->statut);
        $this->assertSame(
            [[null, 'planifiee'], ['planifiee', 'en_cours'], ['en_cours', 'terminee']],
            $intervention->historiques()->get()->reverse()->map(fn ($h) => [$h->ancien_statut, $h->nouveau_statut])->values()->all()
        );
    }

    public function test_les_retours_en_arriere_sont_refuses(): void
    {
        $enCours = $this->creerIntervention($this->creerIncident(), 'en_cours');
        $terminee = $this->creerIntervention($this->creerIncident(), 'terminee');
        $this->actingAs($this->gestionnaire);

        $this->patch(route('admin.interventions.statut', $enCours), ['statut' => 'planifiee'])->assertSessionHasErrors('statut');
        $this->patch(route('admin.interventions.statut', $terminee), ['statut' => 'en_cours'])->assertSessionHasErrors('statut');

        $this->assertSame('en_cours', $enCours->fresh()->statut);
        $this->assertSame('terminee', $terminee->fresh()->statut);
    }

    public function test_planifiee_peut_etre_directement_terminee(): void
    {
        $intervention = $this->creerIntervention($this->creerIncident());

        $this->actingAs($this->gestionnaire)->patch(route('admin.interventions.statut', $intervention), ['statut' => 'terminee'])
            ->assertSessionHasNoErrors();

        // La date prévue (demain) devient la date réelle d'exécution (aujourd'hui) : règle T6
        $this->assertTrue($intervention->fresh()->date_intervention->isSameDay(today()));
    }

    // ---------- Suppression ----------

    public function test_suppression_reservee_a_l_admin_et_aux_interventions_planifiees(): void
    {
        $incident = $this->creerIncident();
        $planifiee = $this->creerIntervention($incident);
        $enCours = $this->creerIntervention($incident, 'en_cours');

        $this->actingAs($this->gestionnaire)->delete(route('admin.interventions.destroy', $planifiee))->assertForbidden();
        $this->assertModelExists($planifiee);

        $this->actingAs($this->admin)->delete(route('admin.interventions.destroy', $enCours))->assertSessionHasErrors('intervention');
        $this->assertModelExists($enCours);

        $this->actingAs($this->admin)->delete(route('admin.interventions.destroy', $planifiee))
            ->assertRedirect(route('admin.incidents.show', $incident));
        $this->assertModelMissing($planifiee);
    }

    // ---------- Filtres ----------

    public function test_filtres_statut_retard_et_incident(): void
    {
        $incident = $this->creerIncident(['date_signalement' => today()->subDays(10)->toDateString()]);
        $enRetard = $this->creerIntervention($incident, 'planifiee', today()->subDays(2)->toDateString());
        $enCours = $this->creerIntervention($incident, 'en_cours');
        $autre = $this->creerIntervention($this->creerIncident(['type' => 'coupure']));
        $ids = fn (array $p) => collect($this->actingAs($this->gestionnaire)->getJson(route('admin.interventions.index', $p))->assertOk()->json('data'))->pluck('id')->all();

        $this->assertEquals([$enRetard->id], $ids(['retard' => 1]));
        $this->assertEquals([$enCours->id], $ids(['statut' => 'en_cours']));
        $this->assertEqualsCanonicalizing([$enRetard->id, $enCours->id], $ids(['incident' => $incident->id]));
        $this->assertEquals([$autre->id], $ids(['type' => 'coupure']));
    }
}
