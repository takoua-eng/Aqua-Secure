<?php

namespace Tests\Feature\Renovation;

use App\Models\Financement;
use App\Models\ProjetRenovation;

// Binôme 3 — CRUD des projets, validations, droits, relations
class ProjetRenovationTest extends RenovationTestCase
{
    private function donnees(array $valeurs = []): array
    {
        return $valeurs + [
            'reseau_id' => $this->reseau->id, 'titre' => 'Remplacement de la conduite principale',
            'statut' => 'planifie', 'cout_estime' => '250 000,50', 'description' => '', 'publie' => '0',
        ];
    }

    public function test_acces_reserve_au_back_office(): void
    {
        $this->get(route('admin.projets.index'))->assertRedirect();
        $this->actingAs($this->citoyen)->get(route('admin.projets.index'))->assertForbidden();
        $this->actingAs($this->citoyen)->post(route('admin.projets.store'), $this->donnees())->assertForbidden();
        $this->assertSame(0, ProjetRenovation::count());
    }

    public function test_la_page_provisoire_est_remplacee_par_le_crud(): void
    {
        $this->projet(['titre' => 'Projet visible dans la liste']);

        $this->actingAs($this->gestionnaire)->get(route('admin.projets.index'))
            ->assertOk()
            ->assertSee('Projet visible dans la liste')
            ->assertDontSee('Module en cours de développement');
    }

    public function test_liste_avec_recherche_et_filtres(): void
    {
        $this->projet(['titre' => 'Projet alpha planifié']);
        $this->projet(['titre' => 'Projet beta en cours', 'statut' => 'en_cours', 'reseau_id' => $this->reseauDegrade->id]);

        $this->actingAs($this->gestionnaire)->get(route('admin.projets.index', ['statut' => 'en_cours']))
            ->assertOk()->assertSee('Projet beta en cours')->assertDontSee('Projet alpha planifié');
        $this->actingAs($this->gestionnaire)->get(route('admin.projets.index', ['q' => 'CAN-TUN-002']))
            ->assertOk()->assertSee('Projet beta en cours')->assertDontSee('Projet alpha planifié');
        $this->actingAs($this->gestionnaire)->get(route('admin.projets.index', ['financement' => 'sans']))
            ->assertOk()->assertSee('Projet alpha planifié');
    }

    public function test_creation_avec_montant_au_format_francais(): void
    {
        $this->actingAs($this->gestionnaire)->post(route('admin.projets.store'), $this->donnees())
            ->assertRedirect()->assertSessionHasNoErrors();

        $projet = ProjetRenovation::first();
        $this->assertSame('250000.50', $projet->cout_estime);
        $this->assertTrue($projet->reseau->is($this->reseau));
        $this->assertFalse($projet->publie);
    }

    public function test_validations_serveur(): void
    {
        $this->actingAs($this->gestionnaire);

        $this->post(route('admin.projets.store'), $this->donnees(['titre' => '', 'reseau_id' => 9999, 'statut' => 'annule', 'cout_estime' => '12']))
            ->assertSessionHasErrors(['titre', 'reseau_id', 'statut', 'cout_estime']);
        $this->post(route('admin.projets.store'), $this->donnees(['cout_estime' => '1000,555']))->assertSessionHasErrors('cout_estime');
        $this->post(route('admin.projets.store'), $this->donnees(['publie' => '1', 'description' => '']))->assertSessionHasErrors('description');

        $this->post(route('admin.projets.store'), $this->donnees())->assertSessionHasNoErrors();
        $this->post(route('admin.projets.store'), $this->donnees())->assertSessionHasErrors('titre');   // titre unique par réseau
        $this->assertSame(1, ProjetRenovation::count());
    }

    public function test_le_cout_declare_ne_peut_pas_etre_inferieur_aux_financements(): void
    {
        $projet = $this->projet(['cout_estime' => 100000]);
        $projet->financements()->create(['source' => 'Etat', 'montant' => 80000]);

        $this->actingAs($this->gestionnaire)
            ->put(route('admin.projets.update', $projet), $this->donnees(['titre' => $projet->titre, 'cout_estime' => '50000']))
            ->assertSessionHasErrors('cout_estime');
    }

    public function test_consultation_avec_financements_et_score(): void
    {
        $projet = $this->projet(['reseau_id' => $this->reseauDegrade->id, 'cout_estime' => 200000]);
        $projet->financements()->create(['source' => 'Etat', 'montant' => 50000]);
        $projet->financements()->create(['source' => 'don', 'montant' => 25000]);

        $this->actingAs($this->gestionnaire)->get(route('admin.projets.show', $projet))
            ->assertOk()
            ->assertSee('Score de priorité')
            ->assertSee('75 000&nbsp;DT', false)
            ->assertSee('CAN-TUN-002');

        $this->assertSame(75000.0, $projet->totalFinance());
        $this->assertCount(2, $projet->financements);
    }

    public function test_modification(): void
    {
        $projet = $this->projet();

        $this->actingAs($this->gestionnaire)
            ->put(route('admin.projets.update', $projet), $this->donnees(['titre' => 'Titre modifié du projet', 'statut' => 'en_cours']))
            ->assertRedirect(route('admin.projets.show', $projet));

        $this->assertSame('en_cours', $projet->fresh()->statut);
    }

    public function test_un_projet_termine_n_est_modifiable_que_par_un_admin(): void
    {
        $projet = $this->projet(['statut' => 'termine']);

        $this->actingAs($this->gestionnaire)->get(route('admin.projets.edit', $projet))->assertForbidden();
        $this->actingAs($this->gestionnaire)->put(route('admin.projets.update', $projet), $this->donnees(['statut' => 'en_cours']))->assertForbidden();
        $this->actingAs($this->admin)->put(route('admin.projets.update', $projet), $this->donnees(['titre' => $projet->titre, 'statut' => 'en_cours']))
            ->assertSessionHasNoErrors();

        $this->assertSame('en_cours', $projet->fresh()->statut);
    }

    public function test_suppression_d_un_projet_sans_financement(): void
    {
        $projet = $this->projet();

        $this->actingAs($this->gestionnaire)->delete(route('admin.projets.destroy', $projet))->assertRedirect(route('admin.projets.index'));
        $this->assertModelMissing($projet);
    }

    public function test_un_projet_finance_ne_peut_pas_etre_supprime(): void
    {
        $projet = $this->projet();
        $projet->financements()->create(['source' => 'Etat', 'montant' => 1000]);

        $this->actingAs($this->admin)->delete(route('admin.projets.destroy', $projet))->assertSessionHasErrors('projet');
        $this->assertModelExists($projet);
        $this->assertSame(1, Financement::count());
    }
}
