<?php

namespace Tests\Feature\Renovation;

use App\Models\Financement;
use App\Models\SimulationFinancement;

// Binôme 3 — What If? Engine : simulation, sauvegarde, validation humaine, application
class WhatIfTest extends RenovationTestCase
{
    private function scenario(array $valeurs = []): array
    {
        return $valeurs + ['budget' => 150000, 'strategie' => 'degrades', 'partiel' => 0, 'inclure_estimes' => 1];
    }

    private function deuxProjets(): array
    {
        $a = $this->projet(['titre' => 'Projet réseau hors service', 'reseau_id' => $this->reseauDegrade->id, 'cout_estime' => 100000]);
        $a->financements()->create(['source' => 'Etat', 'montant' => 20000]);
        $b = $this->projet(['titre' => 'Projet réseau en bon état', 'cout_estime' => 60000]);

        return [$a, $b];
    }

    public function test_la_page_s_affiche_pour_le_gestionnaire(): void
    {
        $this->deuxProjets();

        $this->actingAs($this->gestionnaire)->get(route('admin.whatif.index'))
            ->assertOk()->assertSee('What If? Engine')->assertSee('Projet réseau hors service');
        $this->actingAs($this->citoyen)->get(route('admin.whatif.index'))->assertForbidden();
    }

    public function test_la_simulation_compare_4_strategies_sans_rien_ecrire(): void
    {
        $this->deuxProjets();

        $reponse = $this->actingAs($this->gestionnaire)->postJson(route('admin.whatif.simuler'), $this->scenario())
            ->assertOk()
            ->assertJsonStructure(['comparaison' => ['scenarios' => ['degrades', 'equilibre', 'max_projets', 'optimal', 'equite'], 'hypotheses', 'diagnostic'],
                'robustesse' => ['degrades' => ['tient_pct', 'surcout_p95']], 'empreinte']);

        foreach ($reponse->json('comparaison.scenarios') as $s) {
            $this->assertLessThanOrEqual(150000, $s['kpi']['alloue']);
        }
        // Stratégie A : le réseau hors service passe en premier (besoin 80 000 DT)
        $premier = collect($reponse->json('comparaison.scenarios.degrades.lignes'))->firstWhere('rang', 1);
        $this->assertSame('Projet réseau hors service', $premier['titre']);
        $this->assertEquals(80000, $premier['propose']);

        $this->assertSame(1, Financement::count());           // rien n'a été créé
        $this->assertSame(0, SimulationFinancement::count());
    }

    public function test_parametres_controles_cote_serveur(): void
    {
        $this->actingAs($this->gestionnaire)->postJson(route('admin.whatif.simuler'), $this->scenario(['budget' => -5]))
            ->assertStatus(422)->assertJsonValidationErrors('budget');
        $this->actingAs($this->gestionnaire)->postJson(route('admin.whatif.simuler'), $this->scenario(['budget' => 200000000]))
            ->assertStatus(422);
        $this->actingAs($this->gestionnaire)->postJson(route('admin.whatif.simuler'), $this->scenario(['strategie' => 'magique']))
            ->assertStatus(422)->assertJsonValidationErrors('strategie');
    }

    public function test_enregistrement_d_un_brouillon_prive(): void
    {
        $this->deuxProjets();

        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.store'), $this->scenario(['nom' => 'Mon scénario']))
            ->assertRedirect();

        $sim = SimulationFinancement::first();
        $this->assertSame('brouillon', $sim->statut);
        $this->assertCount(count(\App\Services\Renovation\MoteurWhatIf::STRATEGIES), $sim->resultats['scenarios']);
        $this->assertSame(1, Financement::count());

        $autre = \App\Models\User::factory()->create(['role' => 'gestionnaire']);
        $this->actingAs($autre)->get(route('admin.simulations.show', $sim))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.simulations.show', $sim))->assertOk();
        $this->actingAs($this->gestionnaire)->get(route('admin.simulations.show', $sim))->assertOk()->assertSee('Mon scénario');
    }

    public function test_cycle_complet_soumission_approbation_application(): void
    {
        [$a, $b] = $this->deuxProjets();
        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.store'), $this->scenario(['nom' => 'Plan 2027']));
        $sim = SimulationFinancement::first();

        // Le gestionnaire ne peut ni décider ni appliquer
        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.decider', $sim), ['decision' => 'approuver', 'source' => 'Etat'])->assertForbidden();
        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.soumettre', $sim))->assertRedirect();
        $this->assertSame('soumise', $sim->fresh()->statut);

        // L'admin doit choisir la source réelle des fonds
        $this->actingAs($this->admin)->post(route('admin.simulations.decider', $sim), ['decision' => 'approuver'])->assertSessionHasErrors('source');
        $this->actingAs($this->admin)->post(route('admin.simulations.decider', $sim), ['decision' => 'approuver', 'source' => 'bailleur']);
        $this->assertSame('approuvee', $sim->fresh()->statut);
        $this->assertSame(1, Financement::count());   // l'approbation seule ne crée rien

        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.appliquer', $sim->fresh()), ['confirmation' => 1])->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.simulations.appliquer', $sim->fresh()), [])->assertSessionHasErrors('confirmation');
        $this->actingAs($this->admin)->post(route('admin.simulations.appliquer', $sim->fresh()), ['confirmation' => 1])->assertSessionHasNoErrors();

        $sim->refresh();
        $this->assertSame('appliquee', $sim->statut);
        $crees = Financement::where('simulation_id', $sim->id)->get();
        $this->assertCount(2, $crees);                                  // 80 000 + 60 000 ≤ 150 000
        $this->assertEquals(140000, $crees->sum('montant'));
        $this->assertTrue($crees->every(fn ($f) => $f->source === 'bailleur'));
        $this->assertSame('20000.00', Financement::whereNull('simulation_id')->first()->montant);   // existant intact

        // Une seconde application est impossible
        $this->actingAs($this->admin)->post(route('admin.simulations.appliquer', $sim), ['confirmation' => 1])->assertForbidden();
        $this->assertSame(3, Financement::count());
    }

    public function test_application_refusee_si_les_donnees_ont_change(): void
    {
        [$a] = $this->deuxProjets();
        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.store'), $this->scenario(['nom' => 'Plan obsolète', 'soumettre' => 1]));
        $sim = SimulationFinancement::first();
        $this->assertSame('soumise', $sim->statut);
        $this->actingAs($this->admin)->post(route('admin.simulations.decider', $sim), ['decision' => 'approuver', 'source' => 'Etat']);

        // Un financement arrive entre-temps : le scénario n'est plus valable
        $a->financements()->create(['source' => 'don', 'montant' => 5000]);

        $this->actingAs($this->admin)->post(route('admin.simulations.appliquer', $sim), ['confirmation' => 1])
            ->assertSessionHasErrors('simulation');
        $this->assertSame('approuvee', $sim->fresh()->statut);
        $this->assertSame(0, Financement::whereNotNull('simulation_id')->count());
    }

    public function test_rejet_avec_motif_obligatoire(): void
    {
        $this->deuxProjets();
        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.store'), $this->scenario(['nom' => 'À rejeter', 'soumettre' => 1]));
        $sim = SimulationFinancement::first();

        $this->actingAs($this->admin)->post(route('admin.simulations.decider', $sim), ['decision' => 'rejeter'])->assertSessionHasErrors('commentaire');
        $this->actingAs($this->admin)->post(route('admin.simulations.decider', $sim), ['decision' => 'rejeter', 'commentaire' => 'Budget non voté.']);
        $this->assertSame('rejetee', $sim->fresh()->statut);
    }

    public function test_historique_comparaison_et_rapport(): void
    {
        $this->deuxProjets();
        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.store'), $this->scenario(['nom' => 'Scénario A']));
        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.store'), $this->scenario(['nom' => 'Scénario C', 'strategie' => 'max_projets', 'budget' => 60000]));
        $ids = SimulationFinancement::pluck('id')->all();

        $this->actingAs($this->gestionnaire)->get(route('admin.simulations.index'))->assertOk()->assertSee('Scénario A')->assertSee('Scénario C');
        $this->actingAs($this->gestionnaire)->get(route('admin.simulations.comparer', ['ids' => $ids]))->assertOk()->assertSee('Scénario C');
        $this->actingAs($this->gestionnaire)->get(route('admin.simulations.comparer', ['ids' => [$ids[0]]]))->assertSessionHasErrors('ids');

        $pdf = $this->actingAs($this->gestionnaire)->get(route('admin.simulations.rapport', $ids[0]));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_suppression_d_un_brouillon_par_son_auteur_uniquement(): void
    {
        $this->deuxProjets();
        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.store'), $this->scenario(['nom' => 'Brouillon']));
        $sim = SimulationFinancement::first();

        $autre = \App\Models\User::factory()->create(['role' => 'gestionnaire']);
        $this->actingAs($autre)->delete(route('admin.simulations.destroy', $sim))->assertSessionHasErrors('simulation');
        $this->actingAs($this->gestionnaire)->delete(route('admin.simulations.destroy', $sim))->assertRedirect(route('admin.simulations.index'));
        $this->assertModelMissing($sim);
    }
}
