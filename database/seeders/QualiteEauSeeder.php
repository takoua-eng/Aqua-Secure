<?php

namespace Database\Seeders;

use App\Models\AnalyseQualite;
use App\Models\PointPrelevement;
use App\Models\ReseauEau;
use App\Services\DetectionContamination;
use Illuminate\Database\Seeder;
use RuntimeException;

class QualiteEauSeeder extends Seeder
{
    // Nombre de mois d'historique (une analyse par mois et par point) : assez pour entraîner le modèle IA
    private const MOIS = 24;

    // Qualité de l'eau simulée de chaque profil de point :
    // chlore moyen (mg/L), turbidité moyenne (NTU), pH moyen et sa dispersion, nitrates de départ (mg/L) et hausse par mois
    private const PROFILS = [
        'bonne'    => ['chlore' => 0.45, 'turbidite' => 0.7, 'ph' => 7.4, 'ph_ecart' => 0.2, 'nitrates' => 12, 'pente' => 0.0],
        'moyenne'  => ['chlore' => 0.28, 'turbidite' => 1.6, 'ph' => 7.3, 'ph_ecart' => 0.3, 'nitrates' => 24, 'pente' => 0.35],
        'mauvaise' => ['chlore' => 0.17, 'turbidite' => 2.8, 'ph' => 7.0, 'ph_ecart' => 0.4, 'nitrates' => 34, 'pente' => 0.6],
    ];

    // Points de prélèvement rattachés aux réseaux d'InfrastructureSeeder, et qualité de l'eau simulée
    public function run(): void
    {
        // code, réseau, nom, adresse, type, en service, latitude, longitude, qualité (bonne / moyenne / mauvaise), laboratoire
        $points = [
            ['PP-TUN-001', 'CAN-TUN-001', 'Fontaine Place Barcelone',    'Place Barcelone, Tunis',              'robinet_public', true,  36.7975, 10.1810, 'moyenne',  'Laboratoire central SONEDE — Tunis'],
            ['PP-TUN-002', 'RES-TUN-001', 'Réservoir du Belvédère',      'Avenue Taïeb Mhiri, Tunis',           'reservoir',      true,  36.8035, 10.1770, 'bonne',    'Laboratoire central SONEDE — Tunis'],
            ['PP-TUN-003', 'RES-TUN-002', 'Réservoir Cité El Khadra',    'Rue de Palestine, Cité El Khadra',    'reservoir',      true,  36.8330, 10.1925, 'moyenne',  'Institut Pasteur de Tunis'],
            ['PP-ARI-001', 'STA-ARI-001', 'Sortie station El Menzah',    'Avenue Hédi Nouira, Ariana',          'sortie_station', true,  36.8545, 10.1725, 'bonne',    'Laboratoire central SONEDE — Tunis'],
            ['PP-ARI-002', 'CAN-ARI-001', 'Fontaine El Menzah 6',        'Rue Ibn Khaldoun, El Menzah 6',       'robinet_public', false, 36.8500, 10.1695, 'mauvaise', 'Institut Pasteur de Tunis'],
            ['PP-SOU-001', 'STA-SOU-001', 'Sortie station Sahloul',      'Route de Ceinture, Sahloul, Sousse',  'sortie_station', true,  35.8395, 10.5895, 'moyenne',  'Laboratoire régional SONEDE — Sousse'],
            ['PP-MAR-001', 'RES-MAR-001', 'Réservoir La Marsa',          'Avenue Habib Bourguiba, La Marsa',    'reservoir',      true,  36.8765, 10.3205, 'bonne',    'Laboratoire central SONEDE — Tunis'],
            ['PP-HLF-001', 'CAN-HLF-001', 'Fontaine publique Hammam Lif', 'Place de la Gare, Hammam Lif',       'robinet_public', true,  36.7295, 10.3345, 'mauvaise', 'Laboratoire central SONEDE — Tunis'],
            ['PP-KAI-001', 'STA-KAI-001', 'Sortie station de Kairouan',  'Route de Sousse, Kairouan',           'sortie_station', true,  35.6825, 10.0935, 'mauvaise', 'Laboratoire régional SONEDE — Sousse'],
            ['PP-MON-001', 'RES-MON-001', 'Réservoir Skanes',            'Zone touristique Skanes, Monastir',   'reservoir',      true,  35.7685, 10.7955, 'bonne',    'Laboratoire régional SONEDE — Sousse'],
        ];

        foreach ($points as [$code, $codeReseau, $nom, $adresse, $type, $actif, $lat, $lng, $qualite, $laboratoire]) {
            $reseau = ReseauEau::where('code', $codeReseau)->first();
            if (! $reseau) {
                continue; // réseau absent (InfrastructureSeeder non lancé) : on passe
            }

            // Seeder relancé : le point est mis à jour au lieu d'être recréé
            $point = PointPrelevement::updateOrCreate(['code' => $code], [
                'reseau_id' => $reseau->id, 'nom' => $nom, 'adresse' => $adresse,
                'type_point' => $type, 'actif' => $actif, 'latitude' => $lat, 'longitude' => $lng,
            ]);

            // Valeurs pseudo-aléatoires mais toujours les mêmes pour un point donné
            mt_srand(crc32($code));

            for ($rang = 0; $rang < self::MOIS; $rang++) {
                $date = now()->startOfMonth()->subMonths(self::MOIS - 1 - $rang)->addDays(mt_rand(2, 20));
                if ($date->isFuture()) {
                    $date = now();
                }

                // Une seule analyse par point et par mois : on met à jour celle qui existe déjà
                $analyse = $point->analyses()
                    ->whereDate('date_prelevement', '>=', $date->copy()->startOfMonth()->toDateString())
                    ->whereDate('date_prelevement', '<=', $date->copy()->endOfMonth()->toDateString())
                    ->first()
                    ?? new AnalyseQualite(['point_id' => $point->id]);

                $analyse->fill($this->mesures(self::PROFILS[$qualite], $type, $date->month, $rang) + [
                    'date_prelevement' => $date->toDateString(),
                    'laboratoire'      => $laboratoire,
                ])->save();   // "conforme" est calculé par le modèle
            }
        }

        mt_srand(); // on rend le hasard au reste de l'application

        // Entraînement du modèle IA de détection de contamination sur ces données
        try {
            $modele = DetectionContamination::entrainer();
            $this->command?->info(sprintf(
                'Modèle IA entraîné sur %d analyses : exactitude %s %%, rappel %s %%.',
                $modele['donnees']['total'],
                round(($modele['metriques']['exactitude'] ?? 0) * 100, 1),
                round(($modele['metriques']['rappel'] ?? 0) * 100, 1),
            ));
        } catch (RuntimeException $e) {
            $this->command?->warn($e->getMessage());
        }
    }

    // Mesures d'un échantillon, avec des liens réalistes entre les paramètres :
    // - l'été, la chaleur fait baisser le chlore ; l'hiver, les pluies troublent l'eau ;
    // - les nitrates (engrais) montent avec le temps sur les points pollués, et après l'épandage (janvier à mars) ;
    // - les coliformes apparaissent surtout quand le chlore est bas et l'eau trouble, au robinet public et l'été.
    private function mesures(array $profil, string $type, int $mois, int $rang): array
    {
        $ete = $mois >= 6 && $mois <= 9;
        $hiver = in_array($mois, [11, 12, 1, 2]);

        $chlore = max(0, $this->normale($profil['chlore'] - ($ete ? 0.08 : 0), 0.1));
        $turbidite = max(0.1, $profil['turbidite'] * exp($this->normale(0, 0.35))
            + ($hiver && $this->alea(0, 1) < 0.3 ? $this->alea(1.5, 4) : 0));
        $ph = $this->normale($profil['ph'], $profil['ph_ecart']);
        $nitrates = max(0, $profil['nitrates'] + $profil['pente'] * $rang + ($mois <= 3 ? 5 : 0) + $this->normale(0, 2.5));

        // Probabilité de contamination (fonction logistique)
        $score = -3.2 + 12 * (0.2 - $chlore) + 0.55 * $turbidite + ($type === 'robinet_public' ? 0.7 : 0) + ($ete ? 0.6 : 0);
        $contamine = $this->alea(0, 1) < 1 / (1 + exp(-$score));

        return [
            'ph'                   => round(min(14, max(0, $ph)), 2),
            'chlore_residuel'      => round($chlore, 2),
            'turbidite'            => round($turbidite, 2),
            'nitrates'             => round($nitrates, 2),
            'bacteries_coliformes' => $contamine ? mt_rand(1, 40) : 0,
        ];
    }

    private function alea(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }

    // Tirage selon une loi normale (méthode de Box-Muller)
    private function normale(float $moyenne, float $ecartType): float
    {
        $u = max(1e-9, $this->alea(0, 1));
        $v = $this->alea(0, 1);

        return $moyenne + $ecartType * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
}
