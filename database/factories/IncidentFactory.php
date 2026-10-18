<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\ReseauEau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique d'incidents (données de test et de démonstration, jamais des données réelles).
 * Exemples :
 *   Incident::factory()->create();                                  // incident au hasard
 *   Incident::factory()->pourReseau($reseau)->critique()->create();
 *   Incident::factory()->type('contamination')->signaleIlYA(3)->create();
 *
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            // Un réseau existant au hasard (les réseaux viennent du module Infrastructure)
            'reseau_id'        => fn () => ReseauEau::inRandomOrder()->value('id'),
            'type'             => fake()->randomElement(array_keys(Incident::TYPES)),
            // Plus d'incidents faibles et moyens que critiques
            'gravite'          => fake()->randomElement(['faible', 'faible', 'moyenne', 'moyenne', 'critique']),
            'date_signalement' => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
        ];
    }

    // ---------- États ----------

    // Sur ce réseau, forcément après sa mise en service
    public function pourReseau(ReseauEau $reseau): static
    {
        return $this->state(function () use ($reseau) {
            $debut = $reseau->date_mise_en_service?->copy()->max(now()->subDays(90)) ?? now()->subDays(90);

            return [
                'reseau_id'        => $reseau->id,
                'date_signalement' => fake()->dateTimeBetween($debut->min(now()), 'now')->format('Y-m-d'),
            ];
        });
    }

    public function type(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function gravite(string $gravite): static
    {
        return $this->state(fn () => ['gravite' => $gravite]);
    }

    public function critique(): static
    {
        return $this->gravite('critique');
    }

    public function signaleIlYA(int $jours): static
    {
        return $this->state(fn () => ['date_signalement' => today()->subDays($jours)->toDateString()]);
    }
}
