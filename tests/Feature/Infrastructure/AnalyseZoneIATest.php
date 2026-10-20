<?php

namespace Tests\Feature\Infrastructure;

use App\Models\User;
use App\Models\Zone;
use App\Services\AnalyseZoneIA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Rapport IA d'une zone : Groq (simulé, aucun appel réel), mode démo, secours en cas d'erreur, cache
class AnalyseZoneIATest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.anthropic.key' => null, 'renovation.ia.groq.key' => null]);

        $this->zone = Zone::create(['nom' => 'Médina', 'commune' => 'Kairouan', 'population' => 35000]);
        $this->zone->reseaux()->create([
            'code' => 'CAN-KAI-001', 'type' => 'canalisation', 'materiau' => 'fonte',
            'date_mise_en_service' => '1960-01-15', 'etat' => 'hors_service',
        ]);
    }

    private function avecGroq(): void
    {
        config(['renovation.ia.groq.key' => 'cle-de-test', 'renovation.ia.groq.model' => 'openai/gpt-oss-120b',
                'renovation.ia.groq.model_relais' => 'openai/gpt-oss-20b']);
    }

    private function reponseGroq(string $texte = "## Synthèse\nZone critique : **CAN-KAI-001** est hors service.\n## Points critiques\n- CAN-KAI-001"): array
    {
        return ['model' => 'openai/gpt-oss-120b', 'choices' => [['finish_reason' => 'stop', 'message' => ['content' => $texte]]]];
    }

    public function test_sans_cle_le_rapport_de_demonstration_est_utilise(): void
    {
        Http::fake();

        $rapport = AnalyseZoneIA::analyser($this->zone);

        $this->assertSame('demo', AnalyseZoneIA::fournisseur());
        $this->assertTrue($rapport['demo']);
        $this->assertStringContainsString('CAN-KAI-001', $rapport['html']);
        $this->assertSame(4, substr_count($rapport['html'], '<h2>'));   // Synthèse, Points critiques, Actions, À surveiller
        Http::assertNothingSent();
    }

    public function test_avec_une_cle_groq_le_rapport_est_redige_par_l_ia(): void
    {
        $this->avecGroq();
        Http::fake(['api.groq.com/*' => Http::response($this->reponseGroq())]);

        $rapport = AnalyseZoneIA::analyser($this->zone);

        $this->assertSame('groq', AnalyseZoneIA::fournisseur());
        $this->assertArrayNotHasKey('demo', $rapport);
        $this->assertStringContainsString('<strong>CAN-KAI-001</strong>', $rapport['html']);
        $this->assertSame('openai/gpt-oss-120b (Groq)', $rapport['modele']);

        // Les données de la zone et la consigne sont bien envoyées, avec la clé
        Http::assertSent(fn ($requete) => $requete->hasHeader('Authorization', 'Bearer cle-de-test')
            && str_contains($requete['messages'][1]['content'], 'CAN-KAI-001')
            && str_contains($requete['messages'][0]['content'], '## Actions recommandées'));
    }

    public function test_le_html_brut_renvoye_par_l_ia_est_neutralise(): void
    {
        $this->avecGroq();
        Http::fake(['api.groq.com/*' => Http::response($this->reponseGroq("## Synthèse\n<script>alert('x')</script>"))]);

        $this->assertStringNotContainsString('<script>', AnalyseZoneIA::analyser($this->zone)['html']);
    }

    public function test_quota_atteint_on_reessaie_avec_le_modele_de_relais(): void
    {
        $this->avecGroq();
        Http::fakeSequence('api.groq.com/*')
            ->push(['error' => ['message' => 'Rate limit']], 429)
            ->push($this->reponseGroq());

        $rapport = AnalyseZoneIA::analyser($this->zone);

        $this->assertArrayNotHasKey('avertissement', $rapport);
        Http::assertSentCount(2);
        Http::assertSent(fn ($requete) => $requete['model'] === 'openai/gpt-oss-20b');
    }

    public function test_ia_indisponible_le_rapport_de_secours_s_affiche_avec_la_raison(): void
    {
        $this->avecGroq();
        Http::fake(['api.groq.com/*' => Http::response(['error' => 'Invalid API Key'], 401)]);

        $rapport = AnalyseZoneIA::analyser($this->zone);

        $this->assertTrue($rapport['demo']);
        $this->assertStringContainsString('GROQ_API_KEY est invalide', $rapport['avertissement']);
    }

    public function test_un_rapport_ia_est_mis_en_cache_tant_que_les_donnees_ne_changent_pas(): void
    {
        $this->avecGroq();
        Http::fake(['api.groq.com/*' => Http::response($this->reponseGroq())]);

        AnalyseZoneIA::analyser($this->zone);
        AnalyseZoneIA::analyser($this->zone);
        Http::assertSentCount(1);

        // Un réseau change d'état : nouvelles données, nouveau rapport
        $this->zone->reseaux()->first()->update(['etat' => 'degrade']);
        AnalyseZoneIA::analyser($this->zone->fresh());
        Http::assertSentCount(2);
    }

    public function test_le_bouton_analyser_renvoie_le_rapport_en_json(): void
    {
        $gestionnaire = User::factory()->create(['role' => 'gestionnaire']);

        $this->actingAs($gestionnaire)
            ->postJson(route('admin.zones.analyse-ia', $this->zone))
            ->assertOk()
            ->assertJsonStructure(['html', 'genereLe', 'modele'])
            ->assertJson(['demo' => true]);

        $this->actingAs(User::factory()->create(['role' => 'citoyen']))
            ->postJson(route('admin.zones.analyse-ia', $this->zone))
            ->assertForbidden();
    }
}
