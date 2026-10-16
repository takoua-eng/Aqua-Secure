<?php

namespace App\Services;

use App\Models\Capteur;
use App\Models\Releve;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/*
 * Analyse "intelligente" des mesures d'un capteur, calculée par l'application (sans service externe) :
 *
 * 1. Détection d'anomalies : score z robuste (médiane + écart absolu médian) sur une fenêtre glissante.
 *    Un relevé est anormal s'il s'écarte brutalement des mesures précédentes, MÊME s'il reste dans les seuils
 *    (pic de pression, capteur qui dérive…). C'est ce que les seuils fixes ne voient pas.
 *
 * 2. Prévision : régression linéaire (moindres carrés) sur les derniers relevés.
 *    Elle donne la tendance, la valeur attendue dans 24 h et l'heure estimée de franchissement d'un seuil.
 */
class AnalyseCapteur
{
    public const FENETRE = 12;          // nombre de relevés précédents comparés
    public const SEUIL_ANOMALIE = 3.5;  // score z au-delà duquel un relevé est anormal
    public const POINTS_PREVISION = 12; // relevés utilisés pour la régression

    /**
     * @param  Collection<int, Releve>  $releves  relevés du capteur, du plus ancien au plus récent
     * @return array<int, array{score: float, attendu: float}>  id du relevé => détails de l'anomalie
     */
    public static function anomalies(Collection $releves): array
    {
        $valeurs = $releves->pluck('valeur')->values()->all();
        $anomalies = [];

        foreach ($releves->values() as $i => $releve) {
            if ($i < 6) {
                continue; // pas assez d'historique pour juger
            }

            $fenetre = array_slice($valeurs, max(0, $i - self::FENETRE), min($i, self::FENETRE));
            $mediane = self::mediane($fenetre);
            $ecarts = array_map(fn ($v) => abs($v - $mediane), $fenetre);

            // 1,4826 × écart absolu médian ≈ écart-type, mais insensible aux valeurs extrêmes
            $sigma = 1.4826 * self::mediane($ecarts);
            $sigma = max($sigma, 0.02 * max(abs($mediane), 1));   // évite une division par zéro

            $score = ($releve->valeur - $mediane) / $sigma;

            if (abs($score) >= self::SEUIL_ANOMALIE) {
                $anomalies[$releve->id] = ['score' => round($score, 1), 'attendu' => round($mediane, 2)];
            }
        }

        return $anomalies;
    }

    /**
     * Régression linéaire valeur = a × heures + b sur les derniers relevés.
     *
     * @param  Collection<int, Releve>  $releves  du plus ancien au plus récent
     */
    public static function prevision(Capteur $capteur, Collection $releves): ?array
    {
        $derniers = $releves->take(-self::POINTS_PREVISION)->values();

        if ($derniers->count() < 4) {
            return null; // trop peu de points pour une tendance fiable
        }

        $origine = $derniers->first()->date_releve;
        $x = $derniers->map(fn (Releve $r) => $origine->diffInMinutes($r->date_releve) / 60)->all();
        $y = $derniers->pluck('valeur')->all();
        $n = count($x);

        $moyX = array_sum($x) / $n;
        $moyY = array_sum($y) / $n;
        $sxy = $sxx = $syy = 0;
        for ($i = 0; $i < $n; $i++) {
            $sxy += ($x[$i] - $moyX) * ($y[$i] - $moyY);
            $sxx += ($x[$i] - $moyX) ** 2;
            $syy += ($y[$i] - $moyY) ** 2;
        }

        if ($sxx == 0) {
            return null;
        }

        $pente = $sxy / $sxx;                         // unité par heure
        $ordonnee = $moyY - $pente * $moyX;
        $r2 = $syy == 0 ? 1.0 : ($sxy ** 2) / ($sxx * $syy);   // qualité de l'ajustement (0 à 1)

        $maintenant = end($x);
        $dernierDate = $derniers->last()->date_releve;
        // Seule une température peut être négative : la prévision s'arrête à 0 pour les autres mesures
        $borner = fn (float $v) => $capteur->type_mesure === 'temperature' ? $v : max(0, $v);
        $valeurA = fn (float $heures) => $pente * $heures + $ordonnee;

        // Points de prévision : toutes les 6 h pendant 24 h
        $points = collect([6, 12, 18, 24])->map(fn ($h) => [
            'x' => $dernierDate->copy()->addHours($h)->getTimestampMs(),
            'y' => round($borner($valeurA($maintenant + $h)), 2),
        ])->all();

        // Tendance : "stable" si la variation sur 24 h reste sous 5 % de la plage normale
        $variation24h = $pente * 24;
        $plage = max($capteur->seuil_max - $capteur->seuil_min, 0.01);
        $tendance = abs($variation24h) < 0.05 * $plage ? 'stable' : ($pente > 0 ? 'hausse' : 'baisse');

        // Franchissement d'un seuil dans les 7 prochains jours ?
        $franchissement = null;
        $valeurActuelle = $valeurA($maintenant);
        if ($tendance !== 'stable') {
            $seuil = $pente < 0 ? $capteur->seuil_min : $capteur->seuil_max;
            $cote = $pente < 0 ? 'min' : 'max';
            $dejaHors = $pente < 0 ? $valeurActuelle < $seuil : $valeurActuelle > $seuil;
            $dansHeures = ($seuil - $ordonnee) / $pente - $maintenant;

            if ($dejaHors) {
                $franchissement = ['seuil' => $cote, 'deja' => true, 'dans_heures' => 0, 'date' => null];
            } elseif ($dansHeures > 0 && $dansHeures <= 24 * 7) {
                $franchissement = [
                    'seuil'       => $cote,
                    'deja'        => false,
                    'dans_heures' => (int) round($dansHeures),
                    'date'        => $dernierDate->copy()->addMinutes((int) round($dansHeures * 60)),
                ];
            }
        }

        return [
            'tendance'       => $tendance,
            'pente_jour'     => round($pente * 24, 2),
            'valeur_24h'     => round($borner($valeurA($maintenant + 24)), 2),
            'r2'             => round($r2, 2),
            'confiance'      => $r2 >= 0.7 ? 'élevée' : ($r2 >= 0.4 ? 'moyenne' : 'faible'),
            'points'         => $points,
            'franchissement' => $franchissement,
            'nb_points'      => $n,
        ];
    }

    // Phrase lisible : "seuil minimum franchi dans environ 18 h (le 17/10 à 07:00)"
    public static function phraseFranchissement(?array $franchissement): ?string
    {
        if (! $franchissement) {
            return null;
        }

        $seuil = $franchissement['seuil'] === 'min' ? 'minimum' : 'maximum';

        if ($franchissement['deja']) {
            return "La tendance est déjà au-delà du seuil {$seuil}.";
        }

        /** @var Carbon $date */
        $date = $franchissement['date'];

        return "Seuil {$seuil} atteint dans environ {$franchissement['dans_heures']} h (vers le {$date->format('d/m à H:i')}).";
    }

    private static function mediane(array $valeurs): float
    {
        sort($valeurs);
        $n = count($valeurs);
        $milieu = intdiv($n, 2);

        return $n % 2 ? $valeurs[$milieu] : ($valeurs[$milieu - 1] + $valeurs[$milieu]) / 2;
    }
}