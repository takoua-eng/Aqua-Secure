<?php

namespace Database\Factories;

use App\Models\Capteur;
use App\Models\ReseauEau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique de capteurs réalistes.
 * Exemples :
 *   Capteur::factory()->create();                                   // capteur au hasard
 *   Capteur::factory()->pourReseau($reseau)->typeMesure('pression')->create();
 *   Capteur::factory()->count(5)->horsService()->create();
 *
 * @extends Factory<Capteur>
 */
class CapteurFactory extends Factory
{
    protected $model = Capteur::class;

    // Seuils normaux habituels pour chaque type de mesure
    public const SEUILS = [
        'pression'    => [2, 6],
        'debit'       => [20, 120],
        'niveau'      => [1, 8],
        'temperature' => [8, 25],
    ];

    // Modèles réels de capteurs pour chaque type de mesure
    public const MODELES = [
        'pression'    => ['Endress+Hauser Cerabar PMP21', 'Siemens SITRANS P320', 'WIKA A-10'],
        'debit'       => ['Siemens SITRANS FM MAG 5100W', 'Endress+Hauser Promag W 400', 'ABB AquaMaster4'],
        'niveau'      => ['VEGAPULS 21', 'Endress+Hauser Micropilot FMR20', 'Siemens SITRANS LR100'],
        'temperature' => ['WIKA TR10', 'Endress+Hauser iTHERM TM411'],
    ];

    public function definition(): array
    {
        $type = fake()->randomElement(array_keys(Capteur::TYPES_MESURE));
        [$min, $max] = self::SEUILS[$type];

        return [
            // Un réseau existant au hasard (les réseaux viennent du module Infrastructure)
            'reseau_id'         => fn () => ReseauEau::inRandomOrder()->value('id'),
            // Code libre : on retire tant qu'il existe déjà en base (évite l'erreur "code déjà utilisé")
            'code'              => function () {
                do {
                    $code = 'CAP-TN-' . fake()->numerify('####');
                } while (Capteur::where('code', $code)->exists());

                return $code;
            },            'modele'            => fake()->randomElement(self::MODELES[$type]),
            'type_mesure'       => $type,
            'unite'             => Capteur::UNITES[$type],
            'seuil_min'         => $min,
            'seuil_max'         => $max,
            'date_installation' => fake()->dateTimeBetween('-4 years', '-1 month'),
            'statut'            => fake()->randomElement(['actif', 'actif', 'actif', 'actif', 'en_panne', 'hors_service']),
        ];
    }

    // ---------- États ----------

    // Type de mesure imposé (unité, seuils et modèle cohérents)
    public function typeMesure(string $type): static
    {
        [$min, $max] = self::SEUILS[$type];

        return $this->state(fn () => [
            'type_mesure' => $type,
            'unite'       => Capteur::UNITES[$type],
            'seuil_min'   => $min,
            'seuil_max'   => $max,
            'modele'      => fake()->randomElement(self::MODELES[$type]),
        ]);
    }

    // Installé sur ce réseau, forcément après sa mise en service
    public function pourReseau(ReseauEau $reseau): static
    {
        return $this->state(function () use ($reseau) {
            // Au plus tôt : la mise en service du réseau (ou il y a 4 ans si le réseau est plus ancien)
            $debut = $reseau->date_mise_en_service?->copy()->max(now()->subYears(4)) ?? now()->subYears(4);
            $debut = $debut->min(now());

            // Au plus tard : il y a 1 mois, ou aujourd'hui si le réseau est très récent
            $fin = now()->subMonth();
            if ($debut->gt($fin)) {
                $fin = now();
            }

            return [
                'reseau_id'         => $reseau->id,
                'date_installation' => fake()->dateTimeBetween($debut, $fin),
            ];
        });
    }

    public function actif(): static
    {
        return $this->state(fn () => ['statut' => 'actif']);
    }

    public function enPanne(): static
    {
        return $this->state(fn () => ['statut' => 'en_panne']);
    }

    public function horsService(): static
    {
        return $this->state(fn () => ['statut' => 'hors_service']);
    }
}