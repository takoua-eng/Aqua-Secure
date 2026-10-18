<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\Intervention;
use App\Models\ReseauEau;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/*
 * Données de DÉMONSTRATION (fictives) du module Incidents / Interventions (binôme 2).
 * Elles servent uniquement à présenter les listes, la priorisation et les statistiques :
 * ce ne sont pas des données réelles d'exploitation.
 *
 * Lancement (après InfrastructureSeeder, qui crée les réseaux) :
 *   php artisan db:seed --class=IncidentSeeder
 * Ne fait rien si des incidents existent déjà (pas de doublons).
 */
class IncidentSeeder extends Seeder
{
    public const NOMBRE_INCIDENTS = 32;

    public function run(): void
    {
        if (Incident::exists()) {
            $this->command?->warn('IncidentSeeder : des incidents existent déjà, rien n\'a été ajouté.');

            return;
        }

        $reseaux = ReseauEau::all();
        if ($reseaux->isEmpty()) {
            $this->command?->warn('IncidentSeeder : aucun réseau. Lancez d\'abord InfrastructureSeeder.');

            return;
        }

        // Quelques réseaux concentrent les incidents (démonstration des "incidents récurrents")
        $recurrents = $reseaux->random(min(3, $reseaux->count()));

        for ($i = 0; $i < self::NOMBRE_INCIDENTS; $i++) {
            $reseau = fake()->boolean(35) ? $recurrents->random() : $reseaux->random();
            $age = fake()->numberBetween(0, 180);

            $incident = Incident::create([
                'reseau_id'        => $reseau->id,
                'type'             => fake()->randomElement(['fuite', 'fuite', 'fuite', 'coupure', 'coupure', 'contamination']),
                'gravite'          => fake()->randomElement(['faible', 'faible', 'moyenne', 'moyenne', 'critique']),
                'date_signalement' => $this->dateSignalement($reseau, $age)->toDateString(),
            ]);

            $this->interventionsPour($incident, $incident->date_signalement->diffInDays(today()));
        }

        // Deux incidents critiques non pris en charge : démonstration de l'alerte (règle I7)
        foreach ([['contamination', 2], ['coupure', 4]] as [$type, $jours]) {
            $reseau = $reseaux->random();
            Incident::create([
                'reseau_id'        => $reseau->id,
                'type'             => $type,
                'gravite'          => 'critique',
                'date_signalement' => $this->dateSignalement($reseau, $jours)->toDateString(),
            ]);
        }

        $this->command?->info(sprintf(
            'IncidentSeeder : %d incidents et %d interventions de démonstration créés.',
            Incident::count(), Intervention::count()
        ));
    }

    // Jamais avant la mise en service du réseau (règle I2)
    private function dateSignalement(ReseauEau $reseau, int $age): Carbon
    {
        $date = today()->subDays($age);

        return $reseau->date_mise_en_service && $date->lt($reseau->date_mise_en_service)
            ? $reseau->date_mise_en_service->copy()
            : $date;
    }

    /*
     * Situation selon l'ancienneté : les anciens incidents sont souvent résolus, les récents souvent ouverts.
     * Les dates respectent les règles T2 à T4 (jamais avant le signalement, jamais dans le futur sauf "planifiée").
     */
    private function interventionsPour(Incident $incident, float|int $age): void
    {
        $age = (int) $age;
        $debut = $incident->date_signalement;
        $apres = fn (int $min, int $max) => $debut->copy()->addDays(fake()->numberBetween($min, max($min, $max)))->min(today());
        $creer = fn (string $statut, Carbon $date) => Intervention::create([
            'incident_id' => $incident->id, 'statut' => $statut, 'date_intervention' => $date->toDateString(),
        ]);
        $tirage = fake()->numberBetween(1, 100);

        if ($age > 20) {
            if ($tirage <= 70) {
                $creer('terminee', $apres(0, 10));
                if (fake()->boolean(15)) {
                    $creer('terminee', $apres(5, min($age, 25)));   // réparation en deux temps
                }
            } elseif ($tirage <= 90) {
                $creer('en_cours', $apres(1, $age));
            }
            // sinon : non traité

            return;
        }

        if ($age >= 3) {
            match (true) {
                $tirage <= 30 => $creer('planifiee', today()->addDays(fake()->numberBetween(1, 7))),
                $tirage <= 50 => $creer('planifiee', $apres(0, $age - 1)),   // date passée : intervention en retard
                $tirage <= 75 => $creer('en_cours', $apres(0, $age)),
                $tirage <= 85 => $creer('terminee', $apres(0, $age)),
                default       => null,                                        // non traité
            };

            return;
        }

        if ($tirage <= 40) {
            $creer('planifiee', today()->addDays(fake()->numberBetween(0, 3)));
        }
    }
}
