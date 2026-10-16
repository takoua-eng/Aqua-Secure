<?php

namespace App\Services;

use App\Models\ReseauEau;
use App\Models\Zone;
use Illuminate\Support\Carbon;

/*
 * Score de risque (0 à 100) d'un élément du réseau d'eau, calculé à partir de règles d'expert.
 * Chaque point est expliqué : la liste "facteurs" dit d'où vient le score.
 *
 *   Vétusté              35 pts  âge / durée de vie habituelle du matériau
 *   État actuel          30 pts  bon 0, dégradé 18, hors service 30
 *   Durée du problème    10 pts  depuis combien de mois l'élément n'est plus "bon" (12 mois = 10 pts)
 *   Matériau à risque    10 pts  amiante-ciment, fonte, acier
 *   Population touchée   15 pts  habitants de la zone (50 000 et plus = 15 pts)
 */
class ScoreRisque
{
    // Durée de vie habituelle d'un réseau d'eau, en années, selon le matériau
    public const DUREE_VIE = [
        'fonte' => 50, 'acier' => 40, 'pvc' => 40, 'pehd' => 60, 'beton' => 70, 'amiante_ciment' => 40,
    ];

    public const POINTS_ETAT = ['bon' => 0, 'degrade' => 18, 'hors_service' => 30];

    public const POINTS_MATERIAU = ['amiante_ciment' => 10, 'fonte' => 5, 'acier' => 4];

    public const POPULATION_MAX = 50000;

    // Seuils des niveaux : score minimum => [clé, libellé, couleur Tabler]
    public const NIVEAUX = [
        75 => ['critique', 'Critique', 'red'],
        55 => ['eleve', 'Élevé', 'orange'],
        30 => ['modere', 'Modéré', 'yellow'],
        0  => ['faible', 'Faible', 'green'],
    ];

    /**
     * @return array{score: int, niveau: string, libelle: string, couleur: string, facteurs: list<array{libelle: string, points: int, max: int}>}
     */
    public static function pourReseau(ReseauEau $reseau): array
    {
        $facteurs = [];

        // 1. Vétusté
        $dureeVie = self::DUREE_VIE[$reseau->materiau] ?? 50;
        $age = $reseau->age();
        $facteurs[] = [
            'libelle' => "Âge : {$age} ans pour une durée de vie de {$dureeVie} ans ({$reseau->libelleMateriau()})",
            'points'  => (int) min(35, round(35 * $age / $dureeVie)),
            'max'     => 35,
        ];

        // 2. État actuel
        $facteurs[] = [
            'libelle' => 'État actuel : ' . $reseau->libelleEtat(),
            'points'  => self::POINTS_ETAT[$reseau->etat] ?? 0,
            'max'     => 30,
        ];

        // 3. Depuis combien de temps le problème dure
        $mois = 0;
        if ($reseau->etat !== 'bon' && ($depuis = self::etatDepuis($reseau))) {
            $mois = (int) $depuis->diffInMonths(now());
        }
        $facteurs[] = [
            'libelle' => $reseau->etat === 'bon' ? 'Aucun problème en cours' : "Problème non résolu depuis {$mois} mois",
            'points'  => (int) min(10, round($mois * 10 / 12)),
            'max'     => 10,
        ];

        // 4. Matériau à risque
        $facteurs[] = [
            'libelle' => 'Matériau : ' . $reseau->libelleMateriau(),
            'points'  => self::POINTS_MATERIAU[$reseau->materiau] ?? 0,
            'max'     => 10,
        ];

        // 5. Population touchée en cas de panne
        $population = (int) ($reseau->zone?->population ?? 0);
        $facteurs[] = [
            'libelle' => 'Population de la zone : ' . number_format($population, 0, ',', ' ') . ' habitants',
            'points'  => (int) min(15, round(15 * $population / self::POPULATION_MAX)),
            'max'     => 15,
        ];

        $score = (int) min(100, array_sum(array_column($facteurs, 'points')));

        return ['score' => $score] + self::niveau($score) + ['facteurs' => $facteurs];
    }

    // Score d'une zone = score de son élément le plus à risque (0 si aucun réseau)
    public static function pourZone(Zone $zone): array
    {
        $scores = $zone->reseaux->map(function (ReseauEau $reseau) use ($zone) {
            $reseau->setRelation('zone', $zone);

            return self::pourReseau($reseau)['score'];
        });

        $score = (int) ($scores->max() ?? 0);

        return ['score' => $score, 'moyenne' => (int) round($scores->avg() ?? 0)] + self::niveau($score);
    }

    /** @return array{niveau: string, libelle: string, couleur: string} */
    public static function niveau(int $score): array
    {
        foreach (self::NIVEAUX as $minimum => [$cle, $libelle, $couleur]) {
            if ($score >= $minimum) {
                return ['niveau' => $cle, 'libelle' => $libelle, 'couleur' => $couleur];
            }
        }

        return ['niveau' => 'faible', 'libelle' => 'Faible', 'couleur' => 'green'];
    }

    // Date du dernier changement d'état : colonne etat_depuis (withMax) si chargée, sinon l'historique
    private static function etatDepuis(ReseauEau $reseau): ?Carbon
    {
        $valeur = $reseau->getAttribute('etat_depuis');

        return $valeur ? Carbon::parse($valeur) : $reseau->etatDepuis();
    }
}
