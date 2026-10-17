<?php

namespace Tests\Feature;

use App\Models\Incident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\DonneesIncidents;
use Tests\TestCase;

// Pages citoyennes « Incidents et travaux » (binôme 2). Base gestion_eau_test uniquement.
class IncidentPublicTest extends TestCase
{
    use RefreshDatabase, DonneesIncidents;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('incidents.index')) {
            $this->markTestSkipped('Routes publiques du module non chargées (voir docs/binome-2-incidents.md, § Site citoyen).');
        }

        $this->preparerDonnees();
    }

    public function test_un_visiteur_doit_se_connecter(): void
    {
        $this->get(route('incidents.index'))->assertRedirect(route('login'));
    }

    public function test_un_citoyen_voit_les_incidents_en_cours_sans_informations_internes(): void
    {
        $this->creerIncident(['type' => 'coupure', 'gravite' => 'critique']);

        $this->actingAs($this->citoyen)->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('Centre-ville')
            ->assertSee('Signalé — intervention à programmer')
            ->assertSee('Impact important')
            ->assertSee('Signalez-le')
            ->assertDontSee('/100')
            ->assertDontSee('Priorité')
            ->assertDontSee('Analyse IA');
    }

    public function test_les_etats_sont_traduits_pour_l_habitant(): void
    {
        $programme = $this->creerIncident();
        $this->creerIntervention($programme, 'planifiee', today()->addDays(3)->toDateString());
        $enReparation = $this->creerIncident(['type' => 'coupure']);
        $this->creerIntervention($enReparation, 'en_cours');
        $this->actingAs($this->citoyen);

        $this->get(route('incidents.show', $programme))->assertOk()
            ->assertSee('Intervention programmée')
            ->assertSee('Intervention prévue le ' . today()->addDays(3)->format('d/m/Y'));
        $this->get(route('incidents.show', $enReparation))->assertOk()
            ->assertSee('Réparation en cours')
            ->assertSee("Prévoyez une réserve d'eau");
    }

    public function test_un_retard_interne_n_est_jamais_affiche(): void
    {
        $incident = $this->creerIncident(['date_signalement' => today()->subDays(10)->toDateString()]);
        $this->creerIntervention($incident, 'planifiee', today()->subDays(2)->toDateString());

        $this->actingAs($this->citoyen)->get(route('incidents.show', $incident))->assertOk()
            ->assertSee("Date d'intervention en cours de confirmation")
            ->assertDontSee('retard')
            ->assertDontSee(today()->subDays(2)->format('d/m/Y'));
    }

    public function test_un_incident_resolu_reste_visible_30_jours_puis_disparait(): void
    {
        $recent = $this->creerIncident(['date_signalement' => today()->subDays(12)->toDateString()]);
        $this->creerIntervention($recent, 'terminee', today()->subDays(5)->toDateString());
        $ancien = $this->creerIncident(['type' => 'coupure', 'date_signalement' => today()->subDays(90)->toDateString()]);
        $this->creerIntervention($ancien, 'terminee', today()->subDays(80)->toDateString());
        $this->actingAs($this->citoyen);

        $this->assertEquals([$recent->id], Incident::visiblesPublic()->pluck('id')->all());
        $this->get(route('incidents.show', $recent))->assertOk()->assertSee('Réparé le ' . today()->subDays(5)->format('d/m/Y'));
        $this->get(route('incidents.show', $ancien))->assertNotFound();
    }

    public function test_filtres_par_commune_type_et_situation(): void
    {
        $autreZone = \App\Models\Zone::create(['nom' => 'Sahloul', 'commune' => 'Sousse', 'population' => 31000]);
        $reseauSousse = $autreZone->reseaux()->create([
            'code' => 'STA-SOU-001', 'type' => 'station', 'materiau' => 'acier', 'date_mise_en_service' => '2001-07-22', 'etat' => 'bon',
        ]);
        $this->creerIncident(['type' => 'fuite']);
        $this->creerIncident(['type' => 'contamination', 'reseau_id' => $reseauSousse->id]);
        $this->actingAs($this->citoyen);

        $this->get(route('incidents.index', ['commune' => 'Sousse']))->assertOk()->assertSee('Sahloul')->assertDontSee('Fuite — Centre-ville');
        $this->get(route('incidents.index', ['type' => 'fuite']))->assertOk()->assertSee('Fuite — Centre-ville')->assertDontSee('Contamination — Sahloul');
        $this->get(route('incidents.index', ['situation' => 'resolus']))->assertOk()->assertDontSee('Fuite — Centre-ville');
    }

    public function test_les_gestionnaires_peuvent_aussi_consulter_la_page_publique(): void
    {
        $this->creerIncident();

        $this->actingAs($this->gestionnaire)->get(route('incidents.index'))->assertOk()->assertDontSee('Signalez-le');
    }
}
