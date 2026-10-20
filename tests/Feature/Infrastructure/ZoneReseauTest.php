<?php

namespace Tests\Feature\Infrastructure;

use App\Models\ReseauEau;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

// CRUD des zones et des réseaux d'eau : contrôle de saisie, règles métier, historique des états, droits d'accès
class ZoneReseauTest extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;
    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestionnaire = User::factory()->create(['role' => 'gestionnaire']);
        $this->zone = Zone::create(['nom' => 'Centre-ville', 'commune' => 'Tunis', 'population' => 42000]);
    }

    private function reseau(array $valeurs = []): array
    {
        return $valeurs + [
            'zone_id' => $this->zone->id, 'code' => 'CAN-TUN-001', 'type' => 'canalisation', 'materiau' => 'fonte',
            'date_mise_en_service' => '1985-05-12', 'etat' => 'bon',
        ];
    }

    // ---------- Zones ----------

    public function test_creer_une_zone_valide(): void
    {
        $this->actingAs($this->gestionnaire)
            ->post(route('admin.zones.store'), ['nom' => 'Les Berges du Lac', 'commune' => 'La Marsa', 'population' => 9000])
            ->assertRedirect(route('admin.zones.index'));

        $this->assertDatabaseHas('zones', ['nom' => 'Les Berges du Lac', 'commune' => 'La Marsa', 'population' => 9000]);
    }

    public function test_controle_de_saisie_des_zones(): void
    {
        $this->actingAs($this->gestionnaire)
            ->post(route('admin.zones.store'), ['nom' => 'Z<script>', 'commune' => 'Paris', 'population' => -5])
            ->assertSessionHasErrors(['nom', 'commune', 'population']);

        // Même nom dans la même commune : refusé
        $this->actingAs($this->gestionnaire)
            ->post(route('admin.zones.store'), ['nom' => 'Centre-ville', 'commune' => 'Tunis', 'population' => 100])
            ->assertSessionHasErrors(['nom' => 'Cette zone existe déjà dans cette commune.']);

        $this->assertDatabaseCount('zones', 1);
    }

    public function test_une_zone_qui_contient_des_reseaux_ne_peut_pas_etre_supprimee(): void
    {
        $this->zone->reseaux()->create($this->reseau());

        $this->actingAs($this->gestionnaire)->delete(route('admin.zones.destroy', $this->zone))->assertSessionHasErrors('zone');
        $this->assertDatabaseHas('zones', ['id' => $this->zone->id]);
    }

    // ---------- Réseaux ----------

    public function test_creer_un_reseau_met_le_code_en_majuscules(): void
    {
        $this->actingAs($this->gestionnaire)
            ->post(route('admin.reseaux.store'), $this->reseau(['code' => 'can-tun-010', 'latitude' => 36.8, 'longitude' => 10.18]))
            ->assertRedirect(route('admin.reseaux.index'));

        $this->assertDatabaseHas('reseaux_eau', ['code' => 'CAN-TUN-010']);
    }

    public function test_controle_de_saisie_des_reseaux(): void
    {
        ReseauEau::create($this->reseau());

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.reseaux.store'), $this->reseau([
                'code' => 'can-tun-001',            // déjà utilisé (en majuscules)
                'zone_id' => 999999,                // zone inexistante
                'type' => 'piscine', 'materiau' => 'bois',
                'date_mise_en_service' => now()->addYear()->format('Y-m-d'),
                'latitude' => 48.8,                 // hors de la Tunisie, et sans longitude
            ]))
            ->assertSessionHasErrors(['code', 'zone_id', 'type', 'materiau', 'date_mise_en_service', 'latitude', 'longitude']);

        $this->assertDatabaseCount('reseaux_eau', 1);
    }

    public function test_l_historique_enregistre_chaque_changement_d_etat_et_lui_seul(): void
    {
        $this->actingAs($this->gestionnaire)->post(route('admin.reseaux.store'), $this->reseau());
        $reseau = ReseauEau::first();

        $this->actingAs($this->gestionnaire)->put(route('admin.reseaux.update', $reseau), $this->reseau(['etat' => 'degrade']));
        $this->actingAs($this->gestionnaire)->put(route('admin.reseaux.update', $reseau), $this->reseau(['etat' => 'degrade', 'materiau' => 'pvc']));

        $historique = $reseau->historiques()->get();
        $this->assertCount(2, $historique);                                 // création + passage à "dégradé"
        $this->assertSame('bon', $historique[0]->ancien_etat);
        $this->assertSame('degrade', $historique[0]->nouvel_etat);
        $this->assertSame($this->gestionnaire->id, $historique[0]->user_id);
    }

    public function test_import_csv_valide_avec_libelles_dates_et_virgules_decimales(): void
    {
        $csv = "code;zone;commune;type;materiau;date_mise_en_service;etat;latitude;longitude\n"
            . "res-tun-020;Centre-ville;Tunis;Réservoir;Béton;15/03/1999;Dégradé;36,80;10,18\n"
            . "CAN-TUN-021;Centre-ville;Tunis;CANALISATION;pehd;2021-02-01;bon;;\n";

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.reseaux.import.store'), ['fichier' => UploadedFile::fake()->createWithContent('reseaux.csv', $csv)])
            ->assertRedirect(route('admin.reseaux.index'));

        $this->assertDatabaseHas('reseaux_eau', ['code' => 'RES-TUN-020', 'type' => 'reservoir', 'etat' => 'degrade', 'date_mise_en_service' => '1999-03-15']);
        $this->assertDatabaseHas('reseaux_eau', ['code' => 'CAN-TUN-021', 'type' => 'canalisation', 'materiau' => 'pehd']);
    }

    public function test_import_csv_tout_ou_rien_avec_erreurs_par_ligne(): void
    {
        $csv = "code;zone;commune;type;materiau;date_mise_en_service;etat\n"
            . "CAN-OK-001;Centre-ville;Tunis;canalisation;fonte;2000-01-01;bon\n"
            . "CAN-KO-002;Zone inconnue;Tunis;canalisation;fonte;2000-01-01;bon\n";

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.reseaux.import.store'), ['fichier' => UploadedFile::fake()->createWithContent('reseaux.csv', $csv)])
            ->assertSessionHas('erreursImport', fn ($erreurs) => str_contains($erreurs[0], 'Ligne 3'));

        $this->assertDatabaseCount('reseaux_eau', 0);   // la ligne valide n'est pas importée non plus
    }

    public function test_un_citoyen_n_accede_pas_a_la_gestion_de_l_infrastructure(): void
    {
        $citoyen = User::factory()->create(['role' => 'citoyen']);

        foreach (['admin.zones.index', 'admin.reseaux.index', 'admin.risques.index', 'admin.risques.plan'] as $route) {
            $this->actingAs($citoyen)->get(route($route))->assertForbidden();
        }
        $this->actingAs($citoyen)->post(route('admin.zones.store'), ['nom' => 'Pirate', 'commune' => 'Tunis', 'population' => 1])->assertForbidden();
        $this->assertDatabaseCount('zones', 1);
    }
}
