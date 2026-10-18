<?php

namespace Tests\Feature\Renovation;

use App\Models\AnalyseQualite;
use App\Models\Capteur;
use App\Models\Financement;
use App\Models\PointPrelevement;
use App\Models\Releve;
use App\Models\Zone;
use App\Services\Renovation\AnalyseFinancementIA;
use App\Services\Renovation\ScorePriorite;
use App\Services\Renovation\SignauxTerrain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

// Binôme 3 — innovations : signaux terrain, territoires, robustesse, pluriannuel, F10, Excel, rapport mensuel, QR, arabe
class InnovationsTest extends RenovationTestCase
{
    // ---------- 1. Signaux des modules Surveillance et Qualité de l'eau ----------

    public function test_le_score_integre_les_capteurs_et_la_qualite_de_l_eau(): void
    {
        $projet = $this->projet(['reseau_id' => $this->reseauDegrade->id, 'cout_estime' => 100000]);
        $avant = ScorePriorite::pourProjet($projet->load('reseau.zone'))['score'];

        $capteur = Capteur::factory()->create(['reseau_id' => $this->reseauDegrade->id, 'type_mesure' => 'pression', 'unite' => 'bar',
            'seuil_min' => 2, 'seuil_max' => 6, 'statut' => 'actif']);
        foreach (range(1, 5) as $i) {
            Releve::create(['capteur_id' => $capteur->id, 'valeur' => 9, 'date_releve' => now()->subDays($i)]);   // hors seuil
        }
        $point = PointPrelevement::create(['reseau_id' => $this->reseauDegrade->id, 'code' => 'PP-TN-901', 'nom' => 'Fontaine test',
            'adresse' => 'Rue test', 'type_point' => 'robinet_public']);
        foreach ([0, 0] as $coliformes) {
            AnalyseQualite::create(['point_id' => $point->id, 'date_prelevement' => now()->subDays(10)->toDateString(), 'ph' => 7.2,
                'chlore_residuel' => 0.05, 'turbidite' => 1, 'nitrates' => 10, 'bacteries_coliformes' => $coliformes, 'laboratoire' => 'Labo test']);
        }

        SignauxTerrain::oublier();
        $apres = ScorePriorite::pourProjet($projet->fresh()->load('reseau.zone'));

        $this->assertSame(['capteurs' => 1, 'alertes' => 5, 'analyses' => 2, 'non_conformes' => 2], SignauxTerrain::pourReseau($this->reseauDegrade->id));
        $this->assertSame($avant + 15, $apres['score']);   // + 8 (5 alertes) + 7 (2 analyses non conformes sur 2)
        $this->actingAs($this->gestionnaire)->get(route('admin.projets.show', $projet))->assertSee('5 relevé(s) hors seuil');
    }

    // ---------- 2 et 8. Équité territoriale et comparatif des gouvernorats ----------

    public function test_page_territoires_et_strategie_equite(): void
    {
        $sfax = Zone::create(['nom' => 'Sfax Centre', 'commune' => 'Sfax', 'population' => 10000]);
        $reseauSfax = $sfax->reseaux()->create(['code' => 'CAN-SFX-001', 'type' => 'canalisation', 'materiau' => 'pvc',
            'date_mise_en_service' => '2000-01-01', 'etat' => 'degrade']);
        $tunis = $this->projet(['cout_estime' => 200000]);
        $tunis->financements()->create(['source' => 'Etat', 'montant' => 84000]);   // 2 DT / hab à Tunis (42 000 hab)
        $this->projet(['reseau_id' => $reseauSfax->id, 'cout_estime' => 50000]);

        $this->actingAs($this->gestionnaire)->get(route('admin.renovation.territoires'))
            ->assertOk()->assertSee('Indice de Gini')->assertSee('Sfax')->assertSee('Tataouine')
            ->assertViewHas('territoires', fn ($t) => $t->firstWhere('gouvernorat', 'Tunis')['par_habitant'] === 2.0 && $t->count() === 24);

        $json = $this->actingAs($this->gestionnaire)->postJson(route('admin.whatif.simuler'), ['budget' => 50000, 'strategie' => 'equite'])->assertOk();
        $equite = collect($json->json('comparaison.scenarios.equite.lignes'))->firstWhere('propose', '>', 0);
        $this->assertSame('Sfax', $equite['territoire']);   // Sfax n'a encore rien reçu
    }

    // ---------- 3 et 4. Robustesse et plan pluriannuel ----------

    public function test_robustesse_et_plan_pluriannuel(): void
    {
        $this->projet(['titre' => 'Projet pluriannuel A', 'cout_estime' => 100000, 'reseau_id' => $this->reseauDegrade->id]);
        $this->projet(['titre' => 'Projet pluriannuel B', 'cout_estime' => 100000]);

        $r = $this->actingAs($this->gestionnaire)->postJson(route('admin.whatif.simuler'),
            ['budget' => 200000, 'strategie' => 'degrades', 'incertitude_estime' => 0, 'incertitude_devis' => 0])->assertOk();
        $this->assertEquals(100, $r->json('robustesse.degrades.tient_pct'));
        $this->actingAs($this->gestionnaire)->postJson(route('admin.whatif.simuler'), ['budget' => 1000, 'strategie' => 'degrades', 'incertitude_estime' => 500])
            ->assertStatus(422);

        $this->actingAs($this->gestionnaire)->get(route('admin.whatif.pluriannuel'))->assertOk()->assertSee('Calculer le plan');
        $this->actingAs($this->gestionnaire)->get(route('admin.whatif.pluriannuel', ['budget' => 100000, 'annees' => 2, 'strategie' => 'degrades', 'report' => 1]))
            ->assertOk()->assertSee('Projet pluriannuel A')->assertSee('Projet pluriannuel B')
            ->assertViewHas('plan', fn ($p) => $p['acheves'] === 2 && $p['annees'][0]['projets'][0]['titre'] === 'Projet pluriannuel A');
        $this->assertSame(0, Financement::count());   // aucune écriture
    }

    // ---------- 5. Montant atypique ----------

    public function test_alerte_montant_atypique_dans_le_tableau(): void
    {
        foreach ([90000, 40000, 60000, 30000, 50000, 25000, 70000, 45000, 16000000] as $i => $montant) {
            $this->projet(['titre' => "Projet financé {$i}"])->financements()->create(['source' => 'Etat', 'montant' => $montant]);
        }

        $this->actingAs($this->gestionnaire)->get(route('admin.renovation.tableau'))->assertOk()->assertSee('Montant atypique')->assertSee('16 000 000 DT');
    }

    // ---------- 6. Export et import Excel (CSV) ----------

    public function test_export_csv_au_format_excel(): void
    {
        $p = $this->projet(['titre' => '=CMD() titre piégé']);
        $p->financements()->create(['source' => 'bailleur', 'montant' => 1234.5]);

        $reponse = $this->actingAs($this->gestionnaire)->get(route('admin.financements.export'))->assertOk();
        $contenu = $reponse->getContent();

        $this->assertStringStartsWith("\u{FEFF}id;date;projet_id", $contenu);
        $this->assertStringContainsString('1234,50', $contenu);
        $this->assertStringContainsString("'=CMD() titre piégé", $contenu);   // formule neutralisée
        $this->assertStringContainsString('Tunis', $contenu);                // gouvernorat
    }

    public function test_import_valide_puis_tout_ou_rien(): void
    {
        $a = $this->projet(['cout_estime' => 100000]);
        $b = $this->projet();

        $valide = "projet_id;projet;source;montant\n{$a->id};A;État;60 000,50\n{$b->id};B;don;1500\n";
        $this->actingAs($this->gestionnaire)->post(route('admin.financements.import.store'), ['fichier' => UploadedFile::fake()->createWithContent('f.csv', $valide)])
            ->assertRedirect(route('admin.financements.index'));
        $this->assertSame(2, Financement::count());
        $this->assertSame('60000.50', Financement::where('projet_id', $a->id)->first()->montant);

        // Une ligne en erreur (dépassement du devis par cumul) => rien n'est importé
        $invalide = "projet_id;source;montant\n{$b->id};Etat;100\n{$a->id};Etat;30000\n{$a->id};Etat;20000\n999;banque;-5\n";
        $this->actingAs($this->gestionnaire)->post(route('admin.financements.import.store'), ['fichier' => UploadedFile::fake()->createWithContent('f.csv', $invalide)])
            ->assertSessionHasErrors('fichier')
            ->assertSessionHas('erreurs_import', fn ($e) => count($e) === 2 && str_contains($e[0], 'Ligne 4') && str_contains($e[1], 'Ligne 5'));
        $this->assertSame(2, Financement::count());

        $this->actingAs($this->gestionnaire)->get(route('admin.financements.import'))->assertOk();
        $this->assertStringContainsString('projet_id;projet;source;montant',
            $this->actingAs($this->gestionnaire)->get(route('admin.financements.import.modele'))->getContent());
        $this->actingAs($this->citoyen)->get(route('admin.financements.export'))->assertForbidden();
    }

    // ---------- 7. Rapport mensuel ----------

    public function test_rapport_mensuel_pdf_en_mode_secours_et_avec_l_ia(): void
    {
        $this->projet()->financements()->create(['source' => 'Etat', 'montant' => 5000]);

        $pdf = $this->actingAs($this->gestionnaire)->get(route('admin.renovation.rapport-mensuel', ['mois' => now()->format('Y-m')]));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->actingAs($this->gestionnaire)->get(route('admin.renovation.rapport-mensuel', ['mois' => now()->addMonths(2)->format('Y-m')]))
            ->assertSessionHasErrors('mois');

        // Avec une IA (simulée) : la synthèse est validée, un chiffre inventé est rejeté
        $donnees = \App\Services\Renovation\RapportMensuel::donnees(now());
        config(['renovation.ia.groq.key' => 'gsk_test']);
        Http::fake(['*' => Http::response(['model' => 'm', 'choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode([
            'synthese' => 'Un financement de 5 000 DT ce mois-ci.', 'faits_marquants' => [], 'points_attention' => [], 'recommandations' => ['Poursuivre.'],
        ])]]]])]);
        $this->assertSame('ia', AnalyseFinancementIA::syntheseMensuelle($donnees)['origine']);
    }

    // ---------- 9. Panneau de chantier (QR) ----------

    public function test_panneau_de_chantier_avec_qr(): void
    {
        $p = $this->projet(['publie' => true, 'description' => 'Projet publié.']);
        $interne = $this->projet();

        $this->actingAs($this->gestionnaire)->get(route('admin.projets.panneau', $p))
            ->assertOk()->assertSee('Scannez pour suivre ce chantier')->assertSee(route('renovation.show', $p), false)
            ->assertDontSee('n\'est pas publié', false);
        $this->actingAs($this->gestionnaire)->get(route('admin.projets.panneau', $interne))->assertOk()->assertSee('pas publié');
        $this->actingAs($this->citoyen)->get(route('admin.projets.panneau', $p))->assertForbidden();
    }

    // ---------- 10. Version arabe ----------

    public function test_observatoire_en_arabe_avec_choix_memorise(): void
    {
        $p = $this->publie();

        $this->get(route('renovation.index', ['langue' => 'ar']))
            ->assertOk()->assertSee('dir="rtl"', false)->assertSee('المرصد المواطني لتجديد شبكة الماء')
            ->assertCookie('observatoire_langue', 'ar');

        $this->withCookie('observatoire_langue', 'ar')->get(route('renovation.show', $p))
            ->assertOk()->assertSee('لماذا هذا المشروع؟')->assertSee('خارج الخدمة')->assertSee('Français');

        $this->get(route('renovation.index', ['langue' => 'xx']))->assertOk()->assertSee('dir="ltr"', false)->assertSee('Observatoire citoyen des rénovations');
    }

    private function publie()
    {
        return $this->projet(['reseau_id' => $this->reseauDegrade->id, 'publie' => true, 'description' => 'Travaux.']);
    }
}
