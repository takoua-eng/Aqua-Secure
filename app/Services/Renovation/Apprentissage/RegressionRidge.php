<?php

namespace App\Services\Renovation\Apprentissage;

use InvalidArgumentException;

/*
 * Binôme 3 — régression linéaire régularisée (Ridge), codée sans bibliothèque.
 *
 *   prédiction = biais + Σ poids_j × x_j
 *   poids = (XᵀX + λI)⁻¹ Xᵀ(y − ȳ)      (le biais = ȳ n'est pas pénalisé ; X doit être centré)
 *
 * La pénalité λ empêche les poids de devenir énormes quand il y a peu d'exemples : le modèle « colle » moins
 * aux données d'entraînement et généralise mieux. Le système est résolu par élimination de Gauss (pivot partiel).
 */
class RegressionRidge
{
    /**
     * @param list<list<float>> $x exemples (variables déjà centrées-réduites)
     * @param list<float> $y valeurs à prédire
     * @return array{biais: float, poids: list<float>, lambda: float}
     */
    public static function entrainer(array $x, array $y, float $lambda): array
    {
        $n = count($x);
        if ($n === 0 || $n !== count($y)) {
            throw new InvalidArgumentException('Exemples et cibles doivent avoir la même taille (non nulle).');
        }

        $d = count($x[0]);
        $moyenne = array_sum($y) / $n;

        // A = XᵀX + λI ; b = Xᵀ(y − ȳ)
        $a = array_fill(0, $d, array_fill(0, $d, 0.0));
        $b = array_fill(0, $d, 0.0);
        foreach ($x as $i => $ligne) {
            $ecart = $y[$i] - $moyenne;
            for ($j = 0; $j < $d; $j++) {
                $b[$j] += $ligne[$j] * $ecart;
                for ($k = $j; $k < $d; $k++) {
                    $a[$j][$k] += $ligne[$j] * $ligne[$k];
                }
            }
        }
        for ($j = 0; $j < $d; $j++) {
            for ($k = 0; $k < $j; $k++) {
                $a[$j][$k] = $a[$k][$j];   // matrice symétrique
            }
            $a[$j][$j] += $lambda;
        }

        return ['biais' => $moyenne, 'poids' => self::resoudre($a, $b), 'lambda' => $lambda];
    }

    public static function predire(array $modele, array $x): float
    {
        $valeur = $modele['biais'];
        foreach ($modele['poids'] as $j => $p) {
            $valeur += $p * $x[$j];
        }

        return $valeur;
    }

    // Élimination de Gauss avec pivot partiel : résout A w = b
    private static function resoudre(array $a, array $b): array
    {
        $d = count($b);
        for ($col = 0; $col < $d; $col++) {
            $pivot = $col;
            for ($l = $col + 1; $l < $d; $l++) {
                if (abs($a[$l][$col]) > abs($a[$pivot][$col])) {
                    $pivot = $l;
                }
            }
            [$a[$col], $a[$pivot]] = [$a[$pivot], $a[$col]];
            [$b[$col], $b[$pivot]] = [$b[$pivot], $b[$col]];

            if (abs($a[$col][$col]) < 1e-12) {
                continue;   // impossible avec λ > 0, sécurité
            }
            for ($l = $col + 1; $l < $d; $l++) {
                $f = $a[$l][$col] / $a[$col][$col];
                for ($k = $col; $k < $d; $k++) {
                    $a[$l][$k] -= $f * $a[$col][$k];
                }
                $b[$l] -= $f * $b[$col];
            }
        }

        $w = array_fill(0, $d, 0.0);
        for ($l = $d - 1; $l >= 0; $l--) {
            $s = $b[$l];
            for ($k = $l + 1; $k < $d; $k++) {
                $s -= $a[$l][$k] * $w[$k];
            }
            $w[$l] = abs($a[$l][$l]) < 1e-12 ? 0.0 : $s / $a[$l][$l];
        }

        return $w;
    }
}
