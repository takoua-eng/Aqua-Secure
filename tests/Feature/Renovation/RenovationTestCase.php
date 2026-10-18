<?php

namespace Tests\Feature\Renovation;

use App\Models\ProjetRenovation;
use App\Models\ReseauEau;
use App\Models\User;
use App\Models\Zone;
use App\Services\Renovation\AnalyseFinancementIA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/*
 * Binôme 3 — base des tests Feature du module.
 * Si routes/web.php ne charge pas encore routes/renovation.php (modification partagée en attente d'accord),
 * les routes du module sont chargées ici, dans le groupe « web », uniquement pour les tests.
 */
abstract class RenovationTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $gestionnaire;
    protected User $admin;
    protected User $citoyen;
    protected ReseauEau $reseau;
    protected ReseauEau $reseauDegrade;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('admin.financements.index')) {
            Route::middleware('web')->group(base_path('routes/renovation.php'));
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }

        // Jamais d'appel réel à une API d'IA pendant les tests (même si une clé est présente dans le .env)
        config(['services.anthropic.key' => null, 'renovation.ia.groq.key' => null]);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        AnalyseFinancementIA::utiliserAppelant(null);
        \App\Services\Renovation\ObservatoireCitoyen::oublier();
        \App\Services\Renovation\SignauxTerrain::oublier();
        \App\Services\Renovation\EquiteTerritoriale::oublier();
        // Modèles entraînés : stockage de test vide (aucun fichier réel lu ni écrit)
        \Illuminate\Support\Facades\Storage::fake('local');
        \App\Services\Renovation\ModeleCoutAppris::oublier();
        \App\Services\Renovation\ModeleSourceAppris::oublier();

        $this->gestionnaire = User::factory()->create(['role' => 'gestionnaire']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->citoyen = User::factory()->create(['role' => 'citoyen']);

        $zone = Zone::create(['nom' => 'Centre-ville', 'commune' => 'Tunis', 'population' => 42000]);
        $this->reseau = $zone->reseaux()->create([
            'code' => 'CAN-TUN-001', 'type' => 'canalisation', 'materiau' => 'pehd',
            'date_mise_en_service' => '2015-01-01', 'etat' => 'bon',
        ]);
        $this->reseauDegrade = $zone->reseaux()->create([
            'code' => 'CAN-TUN-002', 'type' => 'canalisation', 'materiau' => 'fonte',
            'date_mise_en_service' => '1975-01-01', 'etat' => 'hors_service',
        ]);
    }

    protected function tearDown(): void
    {
        AnalyseFinancementIA::utiliserAppelant(null);
        parent::tearDown();
    }

    protected function projet(array $valeurs = []): ProjetRenovation
    {
        return ProjetRenovation::create($valeurs + [
            'reseau_id' => $this->reseau->id, 'titre' => 'Rénovation de test ' . uniqid(), 'statut' => 'planifie',
        ]);
    }
}
