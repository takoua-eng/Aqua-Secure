<?php

namespace Database\Seeders;

use App\Models\Capteur;
use App\Models\Releve;
use App\Models\ReseauEau;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

/**
 * Capteurs et relevés de démonstration (binôme 4), créés avec les factories.
 * À lancer après InfrastructureSeeder : il faut des réseaux.
 */
class SurveillanceSeeder extends Seeder
{
    // Type de mesure adapté à chaque type de réseau
    private const MESURE_PAR_TYPE_RESEAU = [
        'canalisation' => 'pression',
        'reservoir'    => 'niveau',
        'station'      => 'debit',
    ];

    public function run(): void
    {
        fake()->seed(42);   // mêmes données à chaque lancement
        mt_srand(42);

        $reseaux = ReseauEau::orderBy('id')->take(12)->get();

        foreach ($reseaux as $index => $reseau) {
            $numero = $index + 1;
            $type = self::MESURE_PAR_TYPE_RESEAU[$reseau->type] ?? 'pression';

            // Quelques capteurs en panne / hors service pour la démonstration
            $statut = match ($numero % 7) {
                3       => 'en_panne',
                6       => 'hors_service',
                default => 'actif',
            };

            // 1. Le capteur, avec la CapteurFactory
            $capteur = Capteur::factory()
                ->pourReseau($reseau)
                ->typeMesure($type)
                ->create([
                    'code'   => 'CAP-TN-' . str_pad($numero, 3, '0', STR_PAD_LEFT),
                    'statut' => $statut,
                ]);

            if ($statut === 'hors_service') {
                continue; // un capteur hors service ne produit plus de mesures
            }

            // Réseau dégradé ou capteur en panne : les mesures chutent sous le seuil à la fin
            $probleme = $reseau->etat !== 'bon' || $statut === 'en_panne';
            // Un capteur sur trois a un pic isolé, DANS les seuils : seule l'IA le repère
            $pic = $numero % 3 === 2;

            // 2. Ses relevés, avec la ReleveFactory : un toutes les 6 heures pendant 7 jours
            Releve::factory()
                ->count(29)
                ->for($capteur)
                ->sequence(fn (Sequence $s) => $this->point($capteur, 28 - $s->index, $probleme, $pic))
                ->create();
                
            // Un capteur installé récemment n'a pas de mesures antérieures à son installation
            $capteur->releves()->where('date_releve', '<', $capteur->date_installation)->delete();
        }

        // 3. Un capteur de plus sur un réseau au hasard, entièrement généré par les factories
        if ($reseaux->isNotEmpty()) {
            $extra = Capteur::factory()->actif()->typeMesure('temperature')->create(['code' => 'CAP-TN-099']);
            Releve::factory()->count(10)->for($extra)->create();
            Releve::factory()->for($extra)->horsSeuil()->create(['date_releve' => now()->subHours(2)]);
        }
    }

    // Valeur et date du relevé numéro $i (28 = il y a 7 jours, 0 = maintenant)
    private function point(Capteur $capteur, int $i, bool $probleme, bool $pic): array
    {
        $min = $capteur->seuil_min;
        $max = $capteur->seuil_max;
        $milieu = ($min + $max) / 2;
        $amplitude = ($max - $min) / 2;

        $valeur = $milieu
            + $amplitude * 0.35 * sin($i / 3)                // variation jour/nuit
            + $amplitude * (mt_rand(-10, 10) / 100);         // bruit de mesure

        if ($pic && $i === 14) {
            $valeur = $max - $amplitude * 0.05;              // pic brutal mais encore "normal"
        }

        if ($probleme && $i < 6) {
            $valeur = $min * (0.9 - (6 - $i) * 0.06);        // chute sous le seuil : fuite, panne…
        }

        return [
            'valeur'      => round(max($valeur, 0), 2),
            'date_releve' => now()->subHours($i * 6)->startOfHour(),
            'remarque'    => $probleme && $i === 0 ? 'Chute anormale, intervention à prévoir.' : null,
        ];
    }
}