<?php

namespace App\Services\Renovation\Apprentissage;

use InvalidArgumentException;

/*
 * Binôme 3 — classifieur bayésien naïf sur des variables catégorielles, sans bibliothèque.
 *
 *   P(classe | x) ∝ P(classe) × Π P(x_j | classe)
 *
 * Les probabilités sont des fréquences comptées sur les exemples, avec un lissage de Laplace (α) :
 * une valeur jamais vue pour une classe ne donne pas une probabilité nulle. Calcul en logarithmes (stable).
 */
class BayesNaif
{
    /**
     * @param list<array<string, string>> $exemples ex. [['type' => 'station', 'etat' => 'degrade'], ...]
     * @param list<string> $classes classe de chaque exemple
     */
    public static function entrainer(array $exemples, array $classes, float $alpha = 1.0): array
    {
        if (! $exemples || count($exemples) !== count($classes)) {
            throw new InvalidArgumentException('Exemples et classes doivent avoir la même taille (non nulle).');
        }

        $parClasse = [];
        $comptes = [];      // [variable][valeur][classe] => nombre
        $modalites = [];    // [variable] => valeurs observées
        foreach ($exemples as $i => $x) {
            $c = $classes[$i];
            $parClasse[$c] = ($parClasse[$c] ?? 0) + 1;
            foreach ($x as $variable => $valeur) {
                $comptes[$variable][$valeur][$c] = ($comptes[$variable][$valeur][$c] ?? 0) + 1;
                $modalites[$variable][$valeur] = true;
            }
        }
        ksort($parClasse);

        return [
            'alpha'     => $alpha,
            'total'     => count($classes),
            'classes'   => $parClasse,
            'comptes'   => $comptes,
            'modalites' => array_map(fn ($v) => array_keys($v), $modalites),
        ];
    }

    /** @return array<string, float> probabilité de chaque classe (somme = 1), de la plus probable à la moins probable */
    public static function probabilites(array $modele, array $x): array
    {
        $alpha = $modele['alpha'];
        $nbClasses = count($modele['classes']);
        $log = [];

        foreach ($modele['classes'] as $c => $nombre) {
            $l = log(($nombre + $alpha) / ($modele['total'] + $alpha * $nbClasses));
            foreach ($x as $variable => $valeur) {
                if (! isset($modele['modalites'][$variable])) {
                    continue;   // variable inconnue du modèle : ignorée
                }
                $k = count($modele['modalites'][$variable]) + (in_array($valeur, $modele['modalites'][$variable], true) ? 0 : 1);
                $l += log((($modele['comptes'][$variable][$valeur][$c] ?? 0) + $alpha) / ($nombre + $alpha * $k));
            }
            $log[$c] = $l;
        }

        $max = max($log);
        $exp = array_map(fn ($v) => exp($v - $max), $log);
        $somme = array_sum($exp);
        $probas = array_map(fn ($v) => $v / $somme, $exp);
        uksort($probas, fn ($a, $b) => [$probas[$b], $a] <=> [$probas[$a], $b]);

        return $probas;
    }

    /** Part des exemples de la valeur donnée qui appartiennent à la classe : P(classe | variable = valeur) observée. */
    public static function part(array $modele, string $variable, string $valeur, string $classe): ?float
    {
        $comptes = $modele['comptes'][$variable][$valeur] ?? [];
        $total = array_sum($comptes);

        return $total > 0 ? ($comptes[$classe] ?? 0) / $total : null;
    }
}
