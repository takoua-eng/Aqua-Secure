<?php

namespace Tests\Feature\Infrastructure;

use App\Models\CoupureProgrammee;
use App\Models\ReseauEau;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\CoupureProgrammeeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// Coupures d'eau programmées : CRUD, contrôle de saisie, avis aux citoyens de la zone
class CoupureProgrammeeTest extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;
    private Zone $zone;
    private ReseauEau $reservoir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestionnaire = User::factory()->create(['role' => 'gestionnaire']);
        $this->zone = Zone::create(['nom' => 'Centre-ville', 'commune' => 'Tunis', 'population' => 42000]);
        $this->reservoir = $this->zone->reseaux()->create([
            'code' => 'RES-TUN-001', 'type' => 'reservoir', 'materiau' => 'beton',
            'date_mise_en_service' => '1990-03-20', 'etat' => 'bon', 'capacite' => 15000,
        ]);
    }

    private function donnees(array $valeurs = []): array
    {
        return $valeurs + [
            'reseau_eau_id' => $this->reservoir->id,
            'motif'         => 'Nettoyage du réservoir',
            'debut'         => now()->addDays(2)->setTime(8, 0)->format('Y-m-d\TH:i'),
            'fin'           => now()->addDays(2)->setTime(16, 0)->format('Y-m-d\TH:i'),
        ];
    }

    private function citoyen(?Zone $zone, array $valeurs = []): User
    {
        $citoyen = User::factory()->create($valeurs + ['role' => 'citoyen']);
        $citoyen->forceFill(['zone_id' => $zone?->id])->save();

        return $citoyen;
    }

    public function test_programmer_une_coupure_previent_seulement_les_citoyens_verifies_de_la_zone(): void
    {
        Notification::fake();
        $concerne = $this->citoyen($this->zone);
        $nonVerifie = $this->citoyen($this->zone, ['email_verified_at' => null]);
        $autreZone = $this->citoyen(Zone::create(['nom' => 'Ennasr', 'commune' => 'Ariana', 'population' => 9000]));
        $sansZone = $this->citoyen(null);

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.coupures.store'), $this->donnees())
            ->assertRedirect(route('admin.coupures.index'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, '1 citoyen'));

        $this->assertDatabaseHas('coupures_programmees', ['motif' => 'Nettoyage du réservoir', 'user_id' => $this->gestionnaire->id]);
        Notification::assertSentTo($concerne, CoupureProgrammeeNotification::class,
            fn ($n, $canaux) => $n->evenement === 'planifiee' && $canaux === ['mail']);
        Notification::assertNotSentTo([$nonVerifie, $autreZone, $sansZone], CoupureProgrammeeNotification::class);
    }

    public function test_controle_de_saisie_cote_serveur(): void
    {
        $this->actingAs($this->gestionnaire)
            ->post(route('admin.coupures.store'), [
                'reseau_eau_id' => 999999, 'motif' => 'abc',
                'debut' => now()->subDay()->format('Y-m-d\TH:i'), 'fin' => now()->subDays(2)->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors(['reseau_eau_id', 'motif', 'debut', 'fin']);

        $this->assertDatabaseCount('coupures_programmees', 0);
    }

    public function test_une_coupure_ne_peut_pas_durer_plus_de_7_jours(): void
    {
        $this->actingAs($this->gestionnaire)
            ->post(route('admin.coupures.store'), $this->donnees(['fin' => now()->addDays(10)->format('Y-m-d\TH:i')]))
            ->assertSessionHasErrors(['fin' => 'Une coupure programmée ne peut pas durer plus de 7 jours.']);
    }

    public function test_deux_coupures_ne_peuvent_pas_se_chevaucher_sur_le_meme_reseau(): void
    {
        $this->actingAs($this->gestionnaire)->post(route('admin.coupures.store'), $this->donnees());

        $this->actingAs($this->gestionnaire)
            ->post(route('admin.coupures.store'), $this->donnees([
                'debut' => now()->addDays(2)->setTime(12, 0)->format('Y-m-d\TH:i'),
                'fin'   => now()->addDays(2)->setTime(18, 0)->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('debut');

        $this->assertDatabaseCount('coupures_programmees', 1);
    }

    public function test_modifier_les_dates_previent_les_citoyens_mais_pas_un_simple_changement_de_motif(): void
    {
        $this->actingAs($this->gestionnaire)->post(route('admin.coupures.store'), $this->donnees());
        $coupure = CoupureProgrammee::first();
        $citoyen = $this->citoyen($this->zone);
        Notification::fake();

        $this->actingAs($this->gestionnaire)
            ->put(route('admin.coupures.update', $coupure), $this->donnees(['motif' => 'Nettoyage complet du réservoir']))
            ->assertRedirect(route('admin.coupures.index'));
        Notification::assertNothingSent();

        $this->actingAs($this->gestionnaire)
            ->put(route('admin.coupures.update', $coupure), $this->donnees(['fin' => now()->addDays(2)->setTime(18, 0)->format('Y-m-d\TH:i')]));
        Notification::assertSentTo($citoyen, CoupureProgrammeeNotification::class, fn ($n) => $n->evenement === 'modifiee');
    }

    public function test_annuler_previent_les_citoyens_et_garde_la_coupure_dans_l_historique(): void
    {
        $this->actingAs($this->gestionnaire)->post(route('admin.coupures.store'), $this->donnees());
        $coupure = CoupureProgrammee::first();
        $citoyen = $this->citoyen($this->zone);
        Notification::fake();

        // Une coupure à venir ne se supprime pas : il faut d'abord l'annuler
        $this->actingAs($this->gestionnaire)->delete(route('admin.coupures.destroy', $coupure))->assertSessionHasErrors('coupure');

        $this->actingAs($this->gestionnaire)->post(route('admin.coupures.annuler', $coupure));

        $this->assertSame('annulee', $coupure->fresh()->statut());
        Notification::assertSentTo($citoyen, CoupureProgrammeeNotification::class, fn ($n) => $n->evenement === 'annulee');
    }

    public function test_statut_et_habitants_touches(): void
    {
        $this->zone->reseaux()->createMany([
            ['code' => 'CAN-TUN-001', 'type' => 'canalisation', 'materiau' => 'fonte', 'date_mise_en_service' => '1980-01-01', 'etat' => 'bon'],
            ['code' => 'CAN-TUN-002', 'type' => 'canalisation', 'materiau' => 'pvc', 'date_mise_en_service' => '2010-01-01', 'etat' => 'bon'],
        ]);
        $canalisation = ReseauEau::where('code', 'CAN-TUN-001')->first();

        $enCours = CoupureProgrammee::create(['reseau_eau_id' => $canalisation->id, 'motif' => 'Réparation',
            'debut' => now()->subHour(), 'fin' => now()->addHour()]);
        $terminee = CoupureProgrammee::create(['reseau_eau_id' => $this->reservoir->id, 'motif' => 'Nettoyage',
            'debut' => now()->subDays(3), 'fin' => now()->subDays(2)]);

        $this->assertSame('en_cours', $enCours->statut());
        $this->assertSame('terminee', $terminee->statut());
        // Réservoir : toute la zone ; canalisation : la zone répartie entre ses 2 canalisations
        $this->assertSame(42000, $terminee->habitantsTouches());
        $this->assertSame(21000, $enCours->habitantsTouches());
    }

    public function test_le_citoyen_choisit_sa_zone_et_voit_les_coupures_de_sa_zone(): void
    {
        $citoyen = $this->citoyen(null);

        $this->actingAs($citoyen)->put(route('compte.zone'), ['zone_id' => 999999])->assertSessionHasErrors('zone_id');
        $this->actingAs($citoyen)->put(route('compte.zone'), ['zone_id' => $this->zone->id])->assertSessionHas('succes_zone');
        $this->assertSame($this->zone->id, $citoyen->fresh()->zone_id);

        $this->actingAs($this->gestionnaire)->post(route('admin.coupures.store'), $this->donnees());

        $this->actingAs($citoyen->fresh())
            ->get(route('coupures.index'))
            ->assertOk()
            ->assertSee('Nettoyage du réservoir')
            ->assertSee('dans votre zone');
    }

    public function test_un_citoyen_ne_peut_pas_gerer_les_coupures(): void
    {
        $citoyen = $this->citoyen($this->zone);

        $this->actingAs($citoyen)->get(route('admin.coupures.index'))->assertForbidden();
        $this->actingAs($citoyen)->post(route('admin.coupures.store'), $this->donnees())->assertForbidden();
        $this->assertDatabaseCount('coupures_programmees', 0);
    }
}
