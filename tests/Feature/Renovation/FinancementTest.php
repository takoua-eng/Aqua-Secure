<?php

namespace Tests\Feature\Renovation;

use App\Models\Financement;
use App\Models\SimulationFinancement;

// Binôme 3 — CRUD des financements, montants, intégrité
class FinancementTest extends RenovationTestCase
{
    public function test_creation_et_rattachement_au_projet(): void
    {
        $projet = $this->projet();

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.financements.store'), ['projet_id' => $projet->id, 'source' => 'bailleur', 'montant' => '150 000,25'])
            ->assertRedirect(route('admin.projets.show', $projet));

        $f = Financement::first();
        $this->assertSame('150000.25', $f->montant);
        $this->assertTrue($f->projet->is($projet));
        $this->assertSame('Bailleur de fonds', $f->libelleSource());
    }

    public function test_montants_et_sources_invalides(): void
    {
        $projet = $this->projet();
        $this->actingAs($this->gestionnaire);

        foreach (['0', '-500', 'abc', '10,555', '100000000'] as $montant) {
            $this->post(route('admin.financements.store'), ['projet_id' => $projet->id, 'source' => 'Etat', 'montant' => $montant])
                ->assertSessionHasErrors('montant');
        }
        $this->post(route('admin.financements.store'), ['projet_id' => $projet->id, 'source' => 'banque', 'montant' => '100'])
            ->assertSessionHasErrors('source');
        $this->post(route('admin.financements.store'), ['projet_id' => 999, 'source' => 'Etat', 'montant' => '100'])
            ->assertSessionHasErrors('projet_id');

        $this->assertSame(0, Financement::count());
    }

    public function test_le_total_ne_peut_pas_depasser_le_cout_declare(): void
    {
        $projet = $this->projet(['cout_estime' => 100000]);
        $projet->financements()->create(['source' => 'Etat', 'montant' => 70000]);

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.financements.store'), ['projet_id' => $projet->id, 'source' => 'don', 'montant' => '30000,01'])
            ->assertSessionHasErrors('montant');
        $this->actingAs($this->gestionnaire)
            ->post(route('admin.financements.store'), ['projet_id' => $projet->id, 'source' => 'don', 'montant' => '30000'])
            ->assertSessionHasNoErrors();
    }

    public function test_un_projet_termine_ne_recoit_plus_de_financement_sauf_par_un_admin(): void
    {
        $projet = $this->projet(['statut' => 'termine']);

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.financements.store'), ['projet_id' => $projet->id, 'source' => 'Etat', 'montant' => '1000'])
            ->assertSessionHasErrors('projet_id');
        $this->actingAs($this->admin)
            ->post(route('admin.financements.store'), ['projet_id' => $projet->id, 'source' => 'Etat', 'montant' => '1000'])
            ->assertSessionHasNoErrors();
    }

    public function test_liste_filtres_et_totaux(): void
    {
        $projet = $this->projet(['titre' => 'Projet financé plusieurs fois']);
        $projet->financements()->create(['source' => 'Etat', 'montant' => 1000]);
        $projet->financements()->create(['source' => 'don', 'montant' => 500]);

        $this->actingAs($this->gestionnaire)->get(route('admin.financements.index'))
            ->assertOk()->assertSee('1 500&nbsp;DT', false);
        $this->actingAs($this->gestionnaire)->get(route('admin.financements.index', ['source' => 'don']))
            ->assertOk()->assertViewHas('total', 500.0);
    }

    public function test_modification_et_suppression(): void
    {
        $projet = $this->projet();
        $f = $projet->financements()->create(['source' => 'Etat', 'montant' => 1000]);

        $this->actingAs($this->gestionnaire)->get(route('admin.financements.show', $f))->assertOk();
        $this->actingAs($this->gestionnaire)
            ->put(route('admin.financements.update', $f), ['projet_id' => $projet->id, 'source' => 'municipalite', 'montant' => '2000'])
            ->assertRedirect(route('admin.financements.show', $f));
        $this->assertSame('municipalite', $f->fresh()->source);

        $this->actingAs($this->gestionnaire)->delete(route('admin.financements.destroy', $f))->assertRedirect(route('admin.projets.show', $projet));
        $this->assertModelMissing($f);
    }

    public function test_un_financement_issu_d_un_scenario_est_reserve_a_l_admin(): void
    {
        $projet = $this->projet();
        $simulation = SimulationFinancement::create([
            'user_id' => $this->gestionnaire->id, 'nom' => 'Décision', 'budget' => 1000, 'strategie' => 'degrades',
            'parametres' => [], 'resultats' => [], 'empreinte' => 'x', 'statut' => 'appliquee',
        ]);
        $f = $projet->financements()->create(['source' => 'Etat', 'montant' => 1000, 'simulation_id' => $simulation->id]);

        $this->actingAs($this->gestionnaire)->get(route('admin.financements.edit', $f))->assertForbidden();
        $this->actingAs($this->gestionnaire)->delete(route('admin.financements.destroy', $f))->assertSessionHasErrors('financement');
        $this->assertModelExists($f);

        $this->actingAs($this->admin)->delete(route('admin.financements.destroy', $f));
        $this->assertModelMissing($f);
    }

    public function test_citoyen_refuse(): void
    {
        $projet = $this->projet();

        $this->actingAs($this->citoyen)->get(route('admin.financements.index'))->assertForbidden();
        $this->actingAs($this->citoyen)
            ->post(route('admin.financements.store'), ['projet_id' => $projet->id, 'source' => 'Etat', 'montant' => '100'])
            ->assertForbidden();
        $this->assertSame(0, Financement::count());
    }
}
