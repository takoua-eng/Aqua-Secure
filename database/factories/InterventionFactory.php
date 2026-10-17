<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Intervention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique d'interventions (données de test et de démonstration, jamais des données réelles).
 * Exemples :
 *   Intervention::factory()->pourIncident($incident)->create();     // planifiée dans les 14 prochains jours
 *   Intervention::factory()->pourIncident($incident)->enCours()->create();
 *   Intervention::factory()->pourIncident($incident)->terminee()->create();
 *
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    protected $model = Intervention::class;

    public function definition(): array
    {
        return [
            'incident_id'       => Incident::factory(),
            'statut'            => 'planifiee',
            'date_intervention' => today()->addDays(fake()->numberBetween(1, 14))->toDateString(),
        ];
    }

    // ---------- États ----------

    public function pourIncident(Incident $incident): static
    {
        return $this->state(fn () => ['incident_id' => $incident->id]);
    }

    // Date prévue dans le futur (règle T3)
    public function planifiee(): static
    {
        return $this->state(fn () => [
            'statut'            => 'planifiee',
            'date_intervention' => today()->addDays(fake()->numberBetween(1, 14))->toDateString(),
        ]);
    }

    // Date d'exécution : aujourd'hui, donc jamais avant la date de signalement (règles T2 et T4)
    public function enCours(): static
    {
        return $this->state(fn () => ['statut' => 'en_cours', 'date_intervention' => today()->toDateString()]);
    }

    public function terminee(): static
    {
        return $this->state(fn () => ['statut' => 'terminee', 'date_intervention' => today()->toDateString()]);
    }

    // Planifiée à une date déjà passée sans avoir commencé
    public function enRetard(int $jours = 2): static
    {
        return $this->state(fn () => [
            'statut'            => 'planifiee',
            'date_intervention' => today()->subDays($jours)->toDateString(),
        ]);
    }
}
