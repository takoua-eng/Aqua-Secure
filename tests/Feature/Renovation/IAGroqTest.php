<?php

namespace Tests\Feature\Renovation;

use App\Services\Renovation\AnalyseFinancementIA;
use App\Services\Renovation\CandidatsWhatIf;
use App\Services\Renovation\MoteurWhatIf;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// Binôme 3 — fournisseur Groq (API au format OpenAI) : réponses simulées, aucun appel réel
class IAGroqTest extends RenovationTestCase
{
    private const URL = 'https://api.groq.com/openai/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();
        config(['renovation.ia.groq.key' => 'gsk_test', 'renovation.ia.groq.model' => 'modele-test', 'renovation.ia.groq.url' => self::URL]);
    }

    private function comparaison(): array
    {
        $this->projet(['titre' => 'Projet Groq', 'reseau_id' => $this->reseauDegrade->id, 'cout_estime' => 100000]);

        return MoteurWhatIf::comparer(CandidatsWhatIf::charger(), 50000, ['partiel' => true]);
    }

    private function reponseGroq(array $contenu, string $fin = 'stop'): array
    {
        return ['model' => 'modele-test', 'choices' => [['finish_reason' => $fin, 'message' => ['content' => json_encode($contenu)]]]];
    }

    private function analyseValide(array $c, array $valeurs = []): array
    {
        return $valeurs + [
            'synthese' => 'Le budget hypothétique ne permet pas d\'achever le projet.',
            'recommandation' => ['strategie' => 'degrades', 'justification' => 'Le réseau est hors service.'],
            'strategies' => [], 'donnees_manquantes' => [], 'ameliorations' => [],
            'projets_cites' => [$c['scenarios']['degrades']['lignes'][0]['id']],
        ];
    }

    public function test_groq_est_choisi_quand_sa_cle_est_presente(): void
    {
        $this->assertSame('groq', AnalyseFinancementIA::fournisseur());
        config(['renovation.ia.groq.key' => null]);
        $this->assertNull(AnalyseFinancementIA::fournisseur());
    }

    public function test_reponse_valide_de_groq(): void
    {
        $c = $this->comparaison();
        Http::fake([self::URL => Http::response($this->reponseGroq($this->analyseValide($c)))]);

        $r = AnalyseFinancementIA::analyserScenarios($c);

        $this->assertSame('ia', $r['origine']);
        $this->assertStringContainsString('Groq', $r['modele']);
        Http::assertSent(fn (Request $req) => $req->hasHeader('Authorization', 'Bearer gsk_test')
            && $req['response_format']['type'] === 'json_schema'
            && $req['model'] === 'modele-test');
    }

    public function test_les_donnees_restent_sous_le_plafond_meme_avec_beaucoup_de_projets(): void
    {
        foreach (range(1, 80) as $i) {
            $this->projet(['titre' => "Projet de rénovation numéro {$i} avec un titre assez long pour peser", 'cout_estime' => 10000 * $i]);
        }
        $c = MoteurWhatIf::comparer(CandidatsWhatIf::charger(), 2000000, ['partiel' => true]);

        $donnees = AnalyseFinancementIA::donneesScenarios($c);

        $this->assertLessThanOrEqual(12000, strlen(json_encode($donnees, JSON_UNESCAPED_UNICODE)));   // ≈ 4 000 tokens
        $this->assertCount(5, $donnees['scenarios']);
        $this->assertGreaterThan(0, $donnees['projets_non_decrits']);
    }

    public function test_modele_de_relais_si_le_premier_refuse(): void
    {
        config(['renovation.ia.groq.model_relais' => 'modele-relais']);
        $c = $this->comparaison();
        Http::fake([self::URL => Http::sequence()
            ->push(['error' => ['message' => 'Request too large']], 413)
            ->push($this->reponseGroq($this->analyseValide($c)))]);

        $r = AnalyseFinancementIA::analyserScenarios($c);

        $this->assertSame('ia', $r['origine']);
        Http::assertSent(fn (Request $req) => $req['model'] === 'modele-relais');
    }

    public function test_schema_non_pris_en_charge_bascule_en_json_simple(): void
    {
        $c = $this->comparaison();
        Http::fake([self::URL => Http::sequence()
            ->push(['error' => ['message' => 'response_format json_schema is not supported']], 400)
            ->push($this->reponseGroq($this->analyseValide($c)))]);

        $this->assertSame('ia', AnalyseFinancementIA::analyserScenarios($c)['origine']);
        Http::assertSentCount(2);
    }

    private function avertissementAvec($reponseSimulee): array
    {
        $c = $this->comparaison();
        Http::fake([self::URL => $reponseSimulee instanceof \Closure ? $reponseSimulee($c) : $reponseSimulee]);

        return AnalyseFinancementIA::analyserScenarios($c);
    }

    public function test_cle_invalide_bascule_sur_le_mode_secours(): void
    {
        $r = $this->avertissementAvec(Http::response(['error' => 'invalid'], 401));

        $this->assertSame('regles', $r['origine']);
        $this->assertStringContainsString('GROQ_API_KEY est invalide', $r['avertissement']);
    }

    public function test_quota_gratuit_atteint_bascule_sur_le_mode_secours(): void
    {
        $this->assertStringContainsString('Quota', $this->avertissementAvec(Http::response(['error' => 'quota'], 429))['avertissement']);
    }

    public function test_coupure_reseau_bascule_sur_le_mode_secours(): void
    {
        $c = $this->comparaison();
        Http::fake([self::URL => Http::failedConnection()]);

        $this->assertStringContainsString('injoignable', AnalyseFinancementIA::analyserScenarios($c)['avertissement']);
    }

    public function test_chiffre_invente_est_ecarte(): void
    {
        $r = $this->avertissementAvec(fn ($c) => Http::response($this->reponseGroq($this->analyseValide($c, ['synthese' => 'Il manque 777 777 DT.']))));

        $this->assertSame('regles', $r['origine']);
        $this->assertStringContainsString('chiffres absents', $r['avertissement']);
    }

    public function test_json_casse_est_ecarte(): void
    {
        $r = $this->avertissementAvec(Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'pas du JSON']]]]));

        $this->assertStringContainsString('JSON valide', $r['avertissement']);
    }
}
