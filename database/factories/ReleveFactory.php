<?php

namespace Database\Factories;

use App\Models\Capteur;
use App\Models\Releve;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique de relevés.
 * Par défaut la valeur est tirée DANS les seuils du capteur.
 * Exemples :
 *   Releve::factory()->count(20)->for($capteur)->create();
 *   Releve::factory()->for($capteur)->horsSeuil()->create();
 *
 * hors_seuil n'est pas fourni : il est calculé par le modèle Releve.
 *
 * @extends Factory<Releve>
 */
class ReleveFactory extends Factory
{
    protected $model = Releve::class;

    public function definition(): array
    {
        return [
            'capteur_id'  => Capteur::factory(),
            // La valeur dépend des seuils du capteur choisi juste au-dessus
            'valeur'      => function (array $attributs) {
                $capteur = Capteur::find($attributs['capteur_id']);

                return round(fake()->randomFloat(2, $capteur->seuil_min, $capteur->seuil_max), 2);
            },
            'date_releve' => fake()->dateTimeBetween('-7 days', 'now'),
            'remarque'    => fake()->optional(0.1)->randomElement([
                'Contrôle de routine.',
                'Mesure vérifiée sur place.',
                'Valeur confirmée par le technicien.',
            ]),
        ];
    }

    // Valeur au-dessus du seuil maximum (alerte)
    public function horsSeuil(): static
    {
        return $this->state(fn () => [
            'valeur' => function (array $attributs) {
                $capteur = Capteur::find($attributs['capteur_id']);

                return round($capteur->seuil_max * fake()->randomFloat(2, 1.1, 1.4), 2);
            },
            'remarque' => 'Dépassement du seuil maximum.',
        ]);
    }
}