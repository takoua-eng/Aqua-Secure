<?php

namespace Tests\Feature\Renovation;

use App\Models\SimulationFinancement;
use App\Services\Renovation\Apprentissage\JeuEntrainementSynthetique;
use App\Services\Renovation\ModeleCout;
use App\Services\Renovation\ModeleCoutAppris;
use App\Services\Renovation\ModeleSourceAppris;
use Illuminate\Support\Facades\Storage;

// Binôme 3 — modèles IA entraînés (coût, source), page « Modèles IA », intégration
class ModelesIATest extends RenovationTestCase
{
    public function test_page_avant_entrainement_et_droits(): void
    {
        $this->actingAs($this->gestionnaire)->get(route('admin.renovation.modeles'))
            ->assertOk()->assertSee('Les modèles n\'ont pas encore été entraînés', false);
        $this->actingAs($this->citoyen)->get(route('admin.renovation.modeles'))->assertForbidden();
        $this->actingAs($this->citoyen)->post(route('admin.renovation.modeles.entrainer'))->assertForbidden();
    }

    public function test_entrainement_depuis_la_page(): void
    {
        $this->projet(['cout_estime' => 180000])->financements()->create(['source' => 'Etat', 'montant' => 50000]);

        $this->actingAs($this->admin)->post(route('admin.renovation.modeles.entrainer'))
            ->assertRedirect(route('admin.renovation.modeles'))->assertSessionHas('success');

        Storage::disk('local')->assertExists([ModeleCoutAppris::FICHIER, ModeleSourceAppris::FICHIER]);
        $cout = ModeleCoutAppris::charger();
        $this->assertTrue($cout['actif']);
        $this->assertSame(['base' => 1, 'synthetique' => JeuEntrainementSynthetique::TAILLE], $cout['composition']);
        $this->assertLessThan($cout['bareme']['mape'], $cout['retenu']['mape']);   // meilleur que le barème

        $this->actingAs($this->gestionnaire)->get(route('admin.renovation.modeles'))
            ->assertOk()->assertSee('Actif')->assertSee('Matrice de confusion')->assertSee('Contrôle d\'honnêteté', false);
    }

    public function test_un_projet_sans_devis_recoit_le_cout_predit_et_une_source_recommandee(): void
    {
        $projet = $this->projet(['reseau_id' => $this->reseauDegrade->id]);   // pas de coût déclaré
        $this->artisan('renovation:entrainer')->assertSuccessful();

        $cout = ModeleCout::pourProjet($projet->load('reseau.zone'));
        $this->assertSame('predit', $cout['source']);
        $this->assertGreaterThan(0, $cout['marge']);

        $this->actingAs($this->gestionnaire)->get(route('admin.projets.show', $projet))
            ->assertOk()->assertSee('Prédit (IA)')->assertSee('Source à solliciter');
    }

    public function test_sans_assez_de_donnees_le_modele_est_inactif_et_le_bareme_reste(): void
    {
        config(['renovation.ia.jeu_synthetique' => false]);
        $projet = $this->projet(['reseau_id' => $this->reseauDegrade->id]);

        $cout = ModeleCoutAppris::entrainer();
        $source = ModeleSourceAppris::entrainer();

        $this->assertFalse($cout['actif']);
        $this->assertStringContainsString('il en faut au moins', $cout['raison']);
        $this->assertFalse($source['actif']);
        $this->assertSame('estime', ModeleCout::pourProjet($projet->load('reseau.zone'))['source']);
    }

    public function test_essai_en_direct(): void
    {
        $this->artisan('renovation:entrainer')->assertSuccessful();

        $this->actingAs($this->gestionnaire)
            ->get(route('admin.renovation.modeles', ['type' => 'station', 'materiau' => 'acier', 'age' => 40, 'etat' => 'hors_service',
                'population' => 40000, 'gouvernorat' => 'Gafsa']))
            ->assertOk()
            ->assertViewHas('essai', fn ($e) => $e['prediction']['montant'] > $e['bareme'] * 0.8 && $e['source'] !== null);
        $this->actingAs($this->gestionnaire)->get(route('admin.renovation.modeles', ['type' => 'barrage']))->assertSessionHasErrors('type');
    }

    public function test_historique_synthetique_reproductible(): void
    {
        $a = JeuEntrainementSynthetique::generer(20);
        $b = JeuEntrainementSynthetique::generer(20);

        $this->assertSame($a, $b);
        $this->assertCount(20, $a);
        $this->assertGreaterThan(0, min(array_column($a, 'cout')));
    }

    public function test_suggestion_de_source_a_l_approbation(): void
    {
        $this->projet(['reseau_id' => $this->reseauDegrade->id, 'cout_estime' => 100000]);
        $this->artisan('renovation:entrainer')->assertSuccessful();
        $this->actingAs($this->gestionnaire)->post(route('admin.simulations.store'),
            ['nom' => 'Avec suggestion', 'budget' => 100000, 'strategie' => 'degrades', 'soumettre' => 1]);

        $this->actingAs($this->admin)->get(route('admin.simulations.show', SimulationFinancement::first()))
            ->assertOk()->assertSee('Suggestion du modèle IA');
    }
}
