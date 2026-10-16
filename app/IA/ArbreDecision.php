<?php

namespace App\IA;

/*
 * Arbre de décision pour une classification binaire (oui / non), codé sans bibliothèque.
 * Algorithme CART :
 *   - à chaque nœud, on essaie toutes les questions possibles « variable <= seuil ? »
 *     (seuils = milieux entre deux valeurs observées) ;
 *   - on garde la question qui sépare le mieux les exemples, c'est-à-dire celle qui fait
 *     le plus baisser l'impureté de Gini : gini = 1 - p² - (1 - p)²   (p = part de "oui") ;
 *   - on recommence dans chaque branche jusqu'à la profondeur maximale, un nœud pur,
 *     ou trop peu d'exemples.
 * Une feuille donne la probabilité de "oui" : (oui + 1) / (total + 2)  (lissage de Laplace).
 */
class ArbreDecision
{
    private array $racine = [];

    // Importance de chaque variable : somme des baisses d'impureté qu'elle a produites
    private array $importances = [];

    private int $total = 0;

    public function __construct(
        private int $profondeurMax = 4,
        private int $minParFeuille = 5,
    ) {
    }

    // $exemples : liste de vecteurs [x1, x2, ...] ; $cibles : liste de 0 / 1
    public function entrainer(array $exemples, array $cibles): self
    {
        $this->total = count($exemples);
        $this->importances = array_fill(0, count($exemples[0] ?? []), 0.0);
        $this->racine = $this->construire($exemples, $cibles, 0);

        // Importances ramenées à un total de 1
        $somme = array_sum($this->importances);
        if ($somme > 0) {
            $this->importances = array_map(fn ($v) => $v / $somme, $this->importances);
        }

        return $this;
    }

    // Probabilité de "oui" pour un exemple
    public function probabilite(array $x): float
    {
        return $this->feuille($x)['proba'];
    }

    // Questions posées pour arriver à la feuille : [['variable' => 1, 'seuil' => 0.15, 'inferieur' => true], ...]
    public function chemin(array $x): array
    {
        $chemin = [];
        $noeud = $this->racine;

        while (! $noeud['feuille']) {
            $inferieur = $x[$noeud['variable']] <= $noeud['seuil'];
            $chemin[] = ['variable' => $noeud['variable'], 'seuil' => $noeud['seuil'], 'inferieur' => $inferieur];
            $noeud = $inferieur ? $noeud['gauche'] : $noeud['droite'];
        }

        return $chemin;
    }

    public function importances(): array
    {
        return $this->importances;
    }

    // Toutes les règles de l'arbre (un chemin racine → feuille par règle)
    public function regles(): array
    {
        $regles = [];
        $parcourir = function (array $noeud, array $conditions) use (&$parcourir, &$regles) {
            if ($noeud['feuille']) {
                $regles[] = ['conditions' => $conditions, 'proba' => $noeud['proba'], 'n' => $noeud['n'], 'positifs' => $noeud['positifs']];
                return;
            }
            $parcourir($noeud['gauche'], [...$conditions, ['variable' => $noeud['variable'], 'seuil' => $noeud['seuil'], 'inferieur' => true]]);
            $parcourir($noeud['droite'], [...$conditions, ['variable' => $noeud['variable'], 'seuil' => $noeud['seuil'], 'inferieur' => false]]);
        };
        $parcourir($this->racine, []);

        return $regles;
    }

    // Sauvegarde / chargement (JSON)
    public function versTableau(): array
    {
        return [
            'profondeurMax' => $this->profondeurMax,
            'minParFeuille' => $this->minParFeuille,
            'total'         => $this->total,
            'importances'   => $this->importances,
            'racine'        => $this->racine,
        ];
    }

    public static function depuisTableau(array $donnees): self
    {
        $arbre = new self($donnees['profondeurMax'], $donnees['minParFeuille']);
        $arbre->total = $donnees['total'];
        $arbre->importances = $donnees['importances'];
        $arbre->racine = $donnees['racine'];

        return $arbre;
    }

    // ---------- Apprentissage ----------

    private function construire(array $exemples, array $cibles, int $profondeur): array
    {
        $n = count($cibles);
        $positifs = array_sum($cibles);
        $feuille = ['feuille' => true, 'n' => $n, 'positifs' => $positifs, 'proba' => round(($positifs + 1) / ($n + 2), 4)];

        // Conditions d'arrêt
        if ($profondeur >= $this->profondeurMax || $n < 2 * $this->minParFeuille || $positifs === 0 || $positifs === $n) {
            return $feuille;
        }

        $meilleure = $this->meilleureQuestion($exemples, $cibles);
        if ($meilleure === null) {
            return $feuille;
        }

        // Importance pondérée par la part des exemples qui passent par ce nœud
        $this->importances[$meilleure['variable']] += $n / $this->total * $meilleure['gain'];

        [$xg, $yg, $xd, $yd] = [[], [], [], []];
        foreach ($exemples as $i => $x) {
            if ($x[$meilleure['variable']] <= $meilleure['seuil']) {
                $xg[] = $x; $yg[] = $cibles[$i];
            } else {
                $xd[] = $x; $yd[] = $cibles[$i];
            }
        }

        return [
            'feuille'  => false,
            'variable' => $meilleure['variable'],
            'seuil'    => $meilleure['seuil'],
            'n'        => $n,
            'positifs' => $positifs,
            'gauche'   => $this->construire($xg, $yg, $profondeur + 1),
            'droite'   => $this->construire($xd, $yd, $profondeur + 1),
        ];
    }

    // Question « variable <= seuil » qui fait le plus baisser l'impureté de Gini
    private function meilleureQuestion(array $exemples, array $cibles): ?array
    {
        $n = count($cibles);
        $giniParent = self::gini(array_sum($cibles), $n);
        $meilleure = null;

        foreach (array_keys($exemples[0]) as $variable) {
            // Exemples triés par valeur : on fait glisser le seuil de gauche à droite
            $ordre = range(0, $n - 1);
            usort($ordre, fn ($a, $b) => $exemples[$a][$variable] <=> $exemples[$b][$variable]);

            $positifsGauche = 0;
            $positifsTotal = array_sum($cibles);

            for ($k = 0; $k < $n - 1; $k++) {
                $positifsGauche += $cibles[$ordre[$k]];
                $valeur = $exemples[$ordre[$k]][$variable];
                $suivante = $exemples[$ordre[$k + 1]][$variable];

                $nGauche = $k + 1;
                $nDroite = $n - $nGauche;
                if ($valeur == $suivante || $nGauche < $this->minParFeuille || $nDroite < $this->minParFeuille) {
                    continue;
                }

                $giniEnfants = ($nGauche * self::gini($positifsGauche, $nGauche)
                    + $nDroite * self::gini($positifsTotal - $positifsGauche, $nDroite)) / $n;
                $gain = $giniParent - $giniEnfants;

                if ($gain > 1e-9 && ($meilleure === null || $gain > $meilleure['gain'])) {
                    $meilleure = ['variable' => $variable, 'seuil' => round(($valeur + $suivante) / 2, 4), 'gain' => $gain];
                }
            }
        }

        return $meilleure;
    }

    private static function gini(int $positifs, int $n): float
    {
        if ($n === 0) {
            return 0.0;
        }
        $p = $positifs / $n;

        return 1 - $p * $p - (1 - $p) * (1 - $p);
    }

    private function feuille(array $x): array
    {
        $noeud = $this->racine;
        while (! $noeud['feuille']) {
            $noeud = $x[$noeud['variable']] <= $noeud['seuil'] ? $noeud['gauche'] : $noeud['droite'];
        }

        return $noeud;
    }
}
