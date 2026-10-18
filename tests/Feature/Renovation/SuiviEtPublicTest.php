<?php

namespace Tests\Feature\Renovation;

use Illuminate\Support\Facades\Route;

// Binôme 3 — tableau financier, alertes, page citoyenne et non-régression des routes existantes
class SuiviEtPublicTest extends RenovationTestCase
{
    public function test_tableau_financier_calcule_sur_les_donnees_reelles(): void
    {
        $p = $this->projet(['titre' => 'Chantier sans argent', 'statut' => 'en_cours']);
        $q = $this->projet(['titre' => 'Projet financé', 'cout_estime' => 200000]);
        $q->financements()->create(['source' => 'Etat', 'montant' => 50000]);

        $this->actingAs($this->gestionnaire)->get(route('admin.renovation.tableau'))
            ->assertOk()
            ->assertSee('Chantier sans argent')
            ->assertSee('Projet sans financement')                 // alerte F1
            ->assertViewHas('suivi', fn ($s) => $s['total_finance'] === 50000.0
                && $s['couverture']['taux'] === 25.0                 // uniquement sur le coût déclaré
                && $s['couverture']['nb_estimes'] === 1);
    }

    public function test_tableau_vide_sans_projet(): void
    {
        $this->actingAs($this->gestionnaire)->get(route('admin.renovation.tableau'))->assertOk()->assertSee('Aucun projet de rénovation enregistré');
    }

    public function test_explication_des_alertes_en_mode_secours(): void
    {
        $this->projet(['statut' => 'en_cours']);

        $this->actingAs($this->gestionnaire)->postJson(route('admin.renovation.alertes-ia'))
            ->assertOk()->assertJsonPath('origine', 'regles')->assertJsonPath('analyse.actions.0.code', 'F1');
    }

    public function test_page_publique_ne_montre_que_les_projets_publies(): void
    {
        $public = $this->projet(['titre' => 'Projet publié aux citoyens', 'publie' => true, 'description' => 'Travaux visibles.']);
        $public->financements()->create(['source' => 'municipalite', 'montant' => 12000]);
        $interne = $this->projet(['titre' => 'Projet interne confidentiel']);

        $this->get(route('renovation.index'))->assertOk()->assertSee('Projet publié aux citoyens')->assertDontSee('Projet interne confidentiel');
        $this->get(route('renovation.show', $public))->assertOk()->assertSee('Municipalité')->assertSeeInOrder(['12 000', 'DT']);
        $this->get(route('renovation.show', $interne))->assertNotFound();
    }

    public function test_le_citoyen_n_accede_a_aucune_fonction_interne(): void
    {
        foreach (['admin.renovation.tableau', 'admin.whatif.index', 'admin.simulations.index', 'admin.projets.create'] as $route) {
            $this->actingAs($this->citoyen)->get(route($route))->assertForbidden();
        }
        $this->actingAs($this->citoyen)->postJson(route('admin.whatif.simuler'), ['budget' => 1000, 'strategie' => 'degrades'])->assertForbidden();
    }

    public function test_les_routes_des_autres_modules_sont_intactes(): void
    {
        foreach (['admin.dashboard', 'admin.zones.index', 'admin.reseaux.index', 'admin.risques.index', 'admin.risques.plan',
                  'admin.capteurs.index', 'admin.prelevements.index', 'admin.analyses.index', 'admin.incidents.index',
                  'admin.interventions.index', 'home', 'qualite.index'] as $nom) {
            $this->assertTrue(Route::has($nom), "Route {$nom} manquante");
        }

        $this->actingAs($this->gestionnaire)->get(route('admin.zones.index'))->assertOk();
        $this->actingAs($this->gestionnaire)->get(route('admin.reseaux.index'))->assertOk();
        $this->actingAs($this->gestionnaire)->get(route('admin.incidents.index'))->assertOk();
    }
}
