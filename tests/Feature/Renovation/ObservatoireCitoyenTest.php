<?php

namespace Tests\Feature\Renovation;

use App\Models\QuestionProjet;
use App\Models\SoutienProjet;
use App\Services\Renovation\ObservatoireCitoyen;

// Binôme 3 — Observatoire citoyen : quartier, carte, « pourquoi », soutien et questions modérées
class ObservatoireCitoyenTest extends RenovationTestCase
{
    // La participation (soutiens, questions) dépend de la migration 2026_10_18_300004 : sans elle, ces tests sont ignorés
    private function exigerParticipation(): void
    {
        if (! ObservatoireCitoyen::participationDisponible()) {
            $this->markTestSkipped('Participation citoyenne non installée (migration 2026_10_18_300004 absente).');
        }
    }

    private function publie(array $valeurs = [])
    {
        return $this->projet($valeurs + ['publie' => true, 'description' => 'Travaux visibles par les habitants.']);
    }

    public function test_observatoire_avec_quartier_et_donnees_reelles(): void
    {
        $p = $this->publie(['titre' => 'Chantier publié Tunis', 'reseau_id' => $this->reseauDegrade->id, 'statut' => 'en_cours', 'cout_estime' => 100000]);
        $p->financements()->create(['source' => 'Etat', 'montant' => 63000]);
        $p->financements()->create(['source' => 'don', 'montant' => 21000]);
        $this->projet(['titre' => 'Projet interne secret']);

        $this->get(route('renovation.index', ['commune' => 'Tunis']))
            ->assertOk()
            ->assertSee('Observatoire citoyen des rénovations')
            ->assertSee('Chantier publié Tunis')
            ->assertDontSee('Projet interne secret')
            ->assertViewHas('quartier', fn ($q) => $q['commune'] === 'Tunis' && $q['montant'] === 84000.0
                && $q['habitants'] === 42000 && $q['par_habitant'] === 2.0)
            ->assertViewHas('surCent', fn ($s) => array_sum(array_column($s, 'parts')) === 100 && $s[0]['parts'] === 75)
            ->assertViewHas('carte', fn ($c) => count($c) === 1 && $c[0]['precision'] === 'commune');
    }

    public function test_commune_inconnue_ignoree_et_filtres(): void
    {
        $this->publie(['titre' => 'Chantier terminé', 'statut' => 'termine']);
        $this->publie(['titre' => 'Chantier planifié']);

        $this->get(route('renovation.index', ['commune' => '<script>', 'statut' => 'termine']))
            ->assertOk()->assertViewHas('quartier', null)
            ->assertViewHas('liste', fn ($l) => $l->pluck('titre')->all() === ['Chantier terminé']);
    }

    public function test_fiche_explique_pourquoi_avec_les_donnees_du_reseau(): void
    {
        $p = $this->publie(['reseau_id' => $this->reseauDegrade->id]);

        $this->get(route('renovation.show', $p))
            ->assertOk()
            ->assertSee('Pourquoi ce chantier ?')
            ->assertSee('Durée de vie dépassée')          // fonte de 1975 : plus de 50 ans
            ->assertSee('hors service')
            ->assertSee('42 000 habitants');
    }

    public function test_un_citoyen_soutient_puis_retire_son_soutien(): void
    {
        $this->exigerParticipation();
        $p = $this->publie();

        $this->actingAs($this->citoyen)->post(route('renovation.soutien', $p))->assertRedirect();
        $this->assertSame(1, SoutienProjet::count());
        $this->actingAs($this->citoyen)->get(route('renovation.show', $p))->assertSee('Je soutiens ce projet');

        $this->actingAs($this->citoyen)->post(route('renovation.soutien', $p));
        $this->assertSame(0, SoutienProjet::count());
    }

    public function test_seuls_les_citoyens_connectes_participent_sur_un_projet_publie(): void
    {
        $this->exigerParticipation();
        $p = $this->publie();
        $interne = $this->projet();

        $this->post(route('renovation.soutien', $p))->assertRedirect(route('login'));
        $this->actingAs($this->gestionnaire)->post(route('renovation.soutien', $p))->assertForbidden();
        $this->actingAs($this->citoyen)->post(route('renovation.soutien', $interne))->assertNotFound();
        $this->actingAs($this->citoyen)->post(route('renovation.questions.store', $interne), ['question' => 'Question sur un projet interne ?'])->assertNotFound();
        $this->assertSame(0, SoutienProjet::count() + QuestionProjet::count());
    }

    public function test_question_publiee_seulement_apres_reponse_du_gestionnaire(): void
    {
        $this->exigerParticipation();
        $p = $this->publie();

        $this->actingAs($this->citoyen)->post(route('renovation.questions.store', $p), ['question' => 'court'])->assertSessionHasErrors('question');
        $this->actingAs($this->citoyen)->post(route('renovation.questions.store', $p), ['question' => 'La rue Ibn Khaldoun sera-t-elle fermée longtemps ?'])
            ->assertSessionHasNoErrors();
        $q = QuestionProjet::first();

        // Invisible pour le public tant qu'il n'y a pas de réponse ; l'auteur la voit « en attente »
        $this->app['auth']->forgetGuards();
        $this->get(route('renovation.show', $p))->assertDontSee('Ibn Khaldoun');
        $this->actingAs($this->citoyen)->get(route('renovation.show', $p))->assertSee('en attente de réponse');

        // Le citoyen ne peut pas répondre ; le gestionnaire répond et publie
        $this->actingAs($this->citoyen)->post(route('admin.projets.questions.repondre', [$p, $q]), ['reponse' => 'Oui.'])->assertForbidden();
        $this->actingAs($this->gestionnaire)->get(route('admin.projets.show', $p))->assertOk()->assertSee('Voix des citoyens')->assertSee('En attente');
        $this->actingAs($this->gestionnaire)->post(route('admin.projets.questions.repondre', [$p, $q]), ['reponse' => 'Des coupures de 2 heures sont prévues le matin.', 'publiee' => 1])
            ->assertSessionHasNoErrors();

        $this->app['auth']->forgetGuards();
        $this->get(route('renovation.show', $p))->assertSee('Ibn Khaldoun')->assertSee('Des coupures de 2 heures');
    }

    public function test_limite_de_trois_questions_en_attente(): void
    {
        $this->exigerParticipation();
        $p = $this->publie();
        foreach (range(1, 3) as $i) {
            $p->questions()->create(['user_id' => $this->citoyen->id, 'question' => "Question numéro {$i} en attente ?"]);
        }

        $this->actingAs($this->citoyen)->post(route('renovation.questions.store', $p), ['question' => 'Une quatrième question en attente ?'])
            ->assertSessionHasErrors('question');
        $this->assertSame(3, QuestionProjet::count());
    }

    public function test_une_question_d_un_autre_projet_ne_peut_pas_etre_traitee_par_erreur(): void
    {
        $this->exigerParticipation();
        $a = $this->publie();
        $b = $this->publie();
        $q = $b->questions()->create(['user_id' => $this->citoyen->id, 'question' => 'Question rattachée au projet B ?']);

        $this->actingAs($this->gestionnaire)->post(route('admin.projets.questions.repondre', [$a, $q]), ['reponse' => 'Réponse erronée.'])->assertNotFound();
        $this->actingAs($this->gestionnaire)->delete(route('admin.projets.questions.destroy', [$b, $q]))->assertRedirect();
        $this->assertModelMissing($q);
    }

    public function test_mosaique_sur_cent_somme_exactement_100(): void
    {
        $parts = ObservatoireCitoyen::surCent(['Etat' => 1, 'municipalite' => 1, 'don' => 1]);

        $this->assertSame(100, array_sum(array_column($parts, 'parts')));
        $this->assertSame([], ObservatoireCitoyen::surCent([]));
    }
}
