<?php

namespace Tests\Feature\Renovation;

use App\Models\SimulationFinancement;

// Binôme 3 — chaque écran du module s'affiche sans erreur, dans chaque état utile
class PagesTest extends RenovationTestCase
{
    public function test_formulaires_projets_et_financements(): void
    {
        $projet = $this->projet(['cout_estime' => 90000]);
        $f = $projet->financements()->create(['source' => 'Etat', 'montant' => 1000]);
        $this->actingAs($this->gestionnaire);

        $this->get(route('admin.projets.create', ['reseau' => $this->reseau->id]))->assertOk()->assertSee('Coût total déclaré');
        $this->get(route('admin.projets.edit', $projet))->assertOk()->assertSee($projet->titre);
        $this->get(route('admin.financements.create', ['projet' => $projet->id]))->assertOk()->assertSee('Projet financé');
        $this->get(route('admin.financements.edit', $f))->assertOk();
        $this->get(route('admin.financements.show', $f))->assertOk()->assertSee('Saisie manuelle');
    }

    public function test_page_what_if_sans_projet(): void
    {
        $this->actingAs($this->gestionnaire)->get(route('admin.whatif.index'))->assertOk()->assertSee('Aucun projet à simuler');
    }

    public function test_fiche_d_un_scenario_dans_chaque_etat(): void
    {
        $this->projet(['reseau_id' => $this->reseauDegrade->id, 'cout_estime' => 40000]);
        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.store'), ['nom' => 'Fiche', 'budget' => 50000, 'strategie' => 'optimal']);
        $sim = SimulationFinancement::first();

        $this->actingAs($this->gestionnaire)->get(route('admin.simulations.show', $sim))->assertOk()->assertSee('Demander une validation');

        $sim->update(['statut' => 'soumise', 'soumise_le' => now()]);
        $this->actingAs($this->admin)->get(route('admin.simulations.show', $sim))->assertOk()->assertSee("Décision de l'administrateur", false);
        $this->actingAs($this->admin)->get(route('admin.simulations.index'))->assertOk()->assertSee('attendent votre validation');

        $sim->update(['statut' => 'approuvee', 'source_financement' => 'Etat', 'decide_par' => $this->admin->id, 'decide_le' => now()]);
        $this->actingAs($this->admin)->get(route('admin.simulations.show', $sim))->assertOk()->assertSee('Appliquer le scénario approuvé');

        $this->actingAs($this->admin)->post(route('admin.simulations.appliquer', $sim), ['confirmation' => 1]);
        $this->actingAs($this->admin)->get(route('admin.simulations.show', $sim))->assertOk()->assertSee('Financements créés par cette décision');
        $this->actingAs($this->admin)->get(route('admin.simulations.rapport', $sim))->assertOk();
    }
}
