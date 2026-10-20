<?php

namespace Tests\Feature\Infrastructure;

use App\Models\CommentaireCoupure;
use App\Models\CoupureProgrammee;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Commentaires des citoyens sur les coupures : publication, affichage public, "j'aime", modération
class CommentaireCoupureTest extends TestCase
{
    use RefreshDatabase;

    private CoupureProgrammee $coupure;
    private User $citoyen;
    private User $voisin;

    protected function setUp(): void
    {
        parent::setUp();

        $zone = Zone::create(['nom' => 'Centre-ville', 'commune' => 'Tunis', 'population' => 42000]);
        $reseau = $zone->reseaux()->create([
            'code' => 'RES-TUN-001', 'type' => 'reservoir', 'materiau' => 'beton',
            'date_mise_en_service' => '1990-03-20', 'etat' => 'bon',
        ]);
        $this->coupure = CoupureProgrammee::create([
            'reseau_eau_id' => $reseau->id, 'motif' => 'Nettoyage du réservoir',
            'debut' => now()->addDays(2), 'fin' => now()->addDays(2)->addHours(8),
        ]);

        $this->citoyen = User::factory()->create(['role' => 'citoyen', 'nom' => 'Salma Ben Ali']);
        $this->voisin = User::factory()->create(['role' => 'citoyen', 'nom' => 'Youssef Trabelsi']);
    }

    private function commenter(User $user, string $texte)
    {
        return $this->actingAs($user)->post(route('coupures.commenter', $this->coupure), ['contenu' => $texte]);
    }

    public function test_un_citoyen_publie_un_commentaire_visible_par_tous_meme_sans_compte(): void
    {
        $this->commenter($this->citoyen, "Merci pour l'avis, les citernes seront où ?")
            ->assertRedirect(route('coupures.index') . '#coupure-' . $this->coupure->id)
            ->assertSessionHas('succes_commentaire');

        $this->assertDatabaseHas('commentaires_coupure', ['user_id' => $this->citoyen->id, 'coupure_programmee_id' => $this->coupure->id]);

        // Page publique : un visiteur voit le commentaire, avec seulement le prénom et l'initiale du nom
        auth()->logout();
        $this->get(route('coupures.index'))
            ->assertOk()
            ->assertSee('les citernes seront où ?')
            ->assertSee('Salma B.')
            ->assertDontSee('Salma Ben Ali')
            ->assertSee('Connectez-vous');
    }

    public function test_controle_de_saisie_du_commentaire(): void
    {
        $bag = 'commentaire' . $this->coupure->id;

        $this->commenter($this->citoyen, '')->assertSessionHasErrorsIn($bag, 'contenu');
        $this->commenter($this->citoyen, 'ok')->assertSessionHasErrorsIn($bag, 'contenu');
        $this->commenter($this->citoyen, str_repeat('a', CommentaireCoupure::LONGUEUR_MAX + 1))->assertSessionHasErrorsIn($bag, 'contenu');

        $this->assertDatabaseCount('commentaires_coupure', 0);
    }

    public function test_seul_un_citoyen_connecte_peut_commenter(): void
    {
        $this->post(route('coupures.commenter', $this->coupure), ['contenu' => 'Bonjour à tous'])->assertRedirect(route('login'));

        $gestionnaire = User::factory()->create(['role' => 'gestionnaire']);
        $this->commenter($gestionnaire, 'Bonjour à tous')->assertForbidden();

        $nonVerifie = User::factory()->unverified()->create(['role' => 'citoyen']);
        $this->commenter($nonVerifie, 'Bonjour à tous')->assertRedirect();
        $this->assertDatabaseCount('commentaires_coupure', 0);
    }

    public function test_on_ne_commente_pas_une_coupure_annulee(): void
    {
        $this->coupure->forceFill(['annulee_le' => now()])->save();

        $this->commenter($this->citoyen, 'Encore une coupure ?')->assertSessionHasErrors('contenu');
        $this->assertDatabaseCount('commentaires_coupure', 0);
    }

    public function test_aimer_puis_ne_plus_aimer_un_commentaire(): void
    {
        $this->commenter($this->citoyen, "L'eau est revenue à 15 h.");
        $commentaire = CommentaireCoupure::first();

        $this->actingAs($this->voisin)->post(route('commentaires.like', $commentaire));
        $this->assertSame(1, $commentaire->likes()->count());

        // Deuxième clic : le "j'aime" est retiré (jamais deux "j'aime" du même citoyen)
        $this->actingAs($this->voisin)->post(route('commentaires.like', $commentaire));
        $this->assertSame(0, $commentaire->likes()->count());
    }

    public function test_on_ne_peut_pas_aimer_son_propre_commentaire(): void
    {
        $this->commenter($this->citoyen, "L'eau est revenue à 15 h.");
        $commentaire = CommentaireCoupure::first();

        $this->actingAs($this->citoyen)->post(route('commentaires.like', $commentaire))->assertSessionHasErrors('like');
        $this->assertSame(0, $commentaire->likes()->count());
    }

    public function test_les_commentaires_les_plus_aimes_s_affichent_en_premier(): void
    {
        $this->commenter($this->citoyen, 'Premier commentaire peu aimé');
        $this->commenter($this->voisin, 'Second commentaire très aimé');
        $populaire = CommentaireCoupure::where('contenu', 'like', 'Second%')->first();
        $populaire->likes()->attach(User::factory()->count(3)->create(['role' => 'citoyen'])->pluck('id'));

        $this->get(route('coupures.index'))
            ->assertSeeInOrder(['Second commentaire très aimé', 'Premier commentaire peu aimé']);
    }

    public function test_le_gestionnaire_voit_les_commentaires_dans_les_details_de_la_coupure_et_les_supprime(): void
    {
        $this->commenter($this->citoyen, 'Message déplacé à supprimer');
        $commentaire = CommentaireCoupure::first();
        $commentaire->likes()->attach($this->voisin->id);
        $gestionnaire = User::factory()->create(['role' => 'gestionnaire']);

        // Les commentaires sont dans le panneau "Voir détails" de la coupure, sur la page des coupures
        $this->actingAs($gestionnaire)->get(route('admin.coupures.index'))
            ->assertOk()
            ->assertSee('Voir détails')
            ->assertSee('id="details-' . $this->coupure->id . '"', false)
            ->assertSee('Message déplacé à supprimer')
            ->assertSee('Salma Ben Ali')
            ->assertSee("1 j'aime", false);

        // Après suppression : retour sur la liste, panneau de cette coupure rouvert
        $this->actingAs($gestionnaire)
            ->from(route('admin.coupures.index'))
            ->delete(route('admin.commentaires.destroy', $commentaire))
            ->assertRedirect(route('admin.coupures.index') . '#details-' . $this->coupure->id)
            ->assertSessionHas('success', 'Commentaire supprimé.');

        $this->assertDatabaseCount('commentaires_coupure', 0);
        $this->assertDatabaseCount('likes_commentaire', 0);   // supprimés avec le commentaire
    }

    public function test_un_citoyen_ne_peut_pas_moderer(): void
    {
        $this->commenter($this->voisin, 'Un commentaire du voisin');
        $commentaire = CommentaireCoupure::first();

        $this->actingAs($this->citoyen)->get(route('admin.coupures.index'))->assertForbidden();
        $this->actingAs($this->citoyen)->delete(route('admin.commentaires.destroy', $commentaire))->assertForbidden();
        $this->assertDatabaseCount('commentaires_coupure', 1);
    }
}
