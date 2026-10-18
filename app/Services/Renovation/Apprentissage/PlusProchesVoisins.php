<?php

namespace App\Services\Renovation\Apprentissage;

/*
 * Binôme 3 — k plus proches voisins (régression), sans bibliothèque.
 * Pour un nouvel exemple : on cherche les k exemples d'entraînement les plus proches (distance euclidienne
 * sur des variables centrées-réduites) et on fait la moyenne de leurs valeurs. Très explicable :
 * « estimé à partir de ces k projets similaires ».
 */
class PlusProchesVoisins
{
    /**
     * @param list<list<float>> $x exemples d'entraînement
     * @param list<float> $y valeurs connues
     * @return array{valeur: float, voisins: list<int>}
     */
    public static function predire(array $x, array $y, array $nouveau, int $k): array
    {
        $distances = [];
        foreach ($x as $i => $ligne) {
            $somme = 0.0;
            foreach ($ligne as $j => $v) {
                $somme += ($v - $nouveau[$j]) ** 2;
            }
            $distances[$i] = $somme;
        }

        asort($distances);   // tri stable : à distance égale, l'ordre des exemples est conservé
        $voisins = array_slice(array_keys($distances), 0, max(1, min($k, count($x))));

        return [
            'valeur'  => array_sum(array_map(fn ($i) => $y[$i], $voisins)) / count($voisins),
            'voisins' => $voisins,
        ];
    }
}
