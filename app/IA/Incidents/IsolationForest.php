<?php

namespace App\IA\Incidents;

use InvalidArgumentException;
use LogicException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/*
 * Isolation Forest (Liu, Ting et Zhou, 2008), codé sans bibliothèque — binôme 2.
 *
 * Idée : une observation inhabituelle est isolée en peu de coupures aléatoires.
 *   - chaque arbre est construit sur un échantillon de ψ observations tirées sans remise ;
 *   - à chaque nœud : une variable au hasard (parmi celles qui varient encore), un seuil au hasard entre son min et son max ;
 *   - on s'arrête à la hauteur ceil(log2 ψ), sur un nœud d'une seule observation, ou si plus rien ne varie ;
 *   - longueur de chemin h(x) = profondeur atteinte + c(taille de la feuille) ;
 *   - score s(x) = 2^(−moyenne(h(x)) / c(ψ)) : proche de 1 = inhabituel, autour de 0,5 ou moins = habituel.
 * c(n) = 2·H(n−1) − 2(n−1)/n, avec H(i) ≈ ln(i) + 0,5772 (constante d'Euler) : longueur moyenne d'une recherche ratée.
 *
 * Non supervisé : aucun exemple étiqueté n'est nécessaire. Tirage aléatoire reproductible (graine).
 */
class IsolationForest
{
    private const EULER = 0.5772156649015329;

    private array $arbres = [];

    private int $tailleEchantillon = 0;

    private Randomizer $hasard;

    public function __construct(
        private int $nbArbres = 100,
        private int $echantillon = 64,
        private int $graine = 42,
    ) {
        if ($nbArbres < 1 || $echantillon < 2) {
            throw new InvalidArgumentException('Il faut au moins 1 arbre et un échantillon de 2 observations.');
        }
    }

    // $donnees : liste de vecteurs numériques de même longueur [[x1, x2, ...], ...]
    public function entrainer(array $donnees): self
    {
        $donnees = array_values(array_map(fn ($x) => array_map('floatval', array_values($x)), $donnees));
        $n = count($donnees);

        if ($n < 2) {
            throw new InvalidArgumentException('Au moins 2 observations sont nécessaires pour entraîner le modèle.');
        }

        $this->hasard = new Randomizer(new Mt19937($this->graine));
        $this->tailleEchantillon = min($this->echantillon, $n);
        $hauteurMax = (int) ceil(log($this->tailleEchantillon, 2));

        $this->arbres = [];
        for ($t = 0; $t < $this->nbArbres; $t++) {
            $indices = $this->hasard->pickArrayKeys($donnees, $this->tailleEchantillon);   // tirage sans remise
            $echantillon = array_map(fn ($i) => $donnees[$i], $indices);
            $this->arbres[] = $this->construire($echantillon, 0, $hauteurMax);
        }

        return $this;
    }

    // Score d'anomalie entre 0 et 1
    public function score(array $x): float
    {
        $c = self::c($this->tailleEchantillon);

        return $c > 0 ? 2 ** (-$this->longueurMoyenne($x) / $c) : 0.5;
    }

    // Longueur de chemin moyenne sur tous les arbres
    public function longueurMoyenne(array $x): float
    {
        if (! $this->arbres) {
            throw new LogicException("Le modèle n'est pas entraîné.");
        }

        $x = array_map('floatval', array_values($x));
        $somme = 0.0;
        foreach ($this->arbres as $arbre) {
            $somme += $this->longueur($x, $arbre);
        }

        return $somme / count($this->arbres);
    }

    // Longueur moyenne d'une recherche ratée dans un arbre binaire de n éléments
    public static function c(int $n): float
    {
        if ($n <= 1) {
            return 0.0;
        }
        if ($n === 2) {
            return 1.0;
        }

        return 2.0 * (log($n - 1) + self::EULER) - 2.0 * ($n - 1) / $n;
    }

    // Modèle sous forme de tableau (pour l'enregistrer en JSON)
    public function exporter(): array
    {
        return [
            'nb_arbres'  => $this->nbArbres,
            'echantillon' => $this->tailleEchantillon,
            'graine'     => $this->graine,
            'arbres'     => $this->arbres,
        ];
    }

    public static function importer(array $modele): self
    {
        $foret = new self($modele['nb_arbres'], max(2, $modele['echantillon']), $modele['graine']);
        $foret->arbres = $modele['arbres'];
        $foret->tailleEchantillon = $modele['echantillon'];

        return $foret;
    }

    // ---------- Construction ----------

    private function construire(array $x, int $profondeur, int $hauteurMax): array
    {
        $n = count($x);
        if ($profondeur >= $hauteurMax || $n <= 1) {
            return ['taille' => $n];
        }

        // Variables qui varient encore dans ce nœud
        $candidates = [];
        foreach (array_keys($x[0]) as $j) {
            $colonne = array_column($x, $j);
            $min = min($colonne);
            $max = max($colonne);
            if ($max > $min) {
                $candidates[] = [$j, $min, $max];
            }
        }
        if (! $candidates) {
            return ['taille' => $n];
        }

        [$j, $min, $max] = $candidates[$this->hasard->getInt(0, count($candidates) - 1)];
        $seuil = $min + $this->aleatoire01() * ($max - $min);

        $gauche = [];
        $droite = [];
        foreach ($x as $ligne) {
            if ($ligne[$j] < $seuil) {
                $gauche[] = $ligne;
            } else {
                $droite[] = $ligne;
            }
        }

        // Seuil tombé exactement sur le minimum : pas de coupure possible
        if (! $gauche || ! $droite) {
            return ['taille' => $n];
        }

        return [
            'variable' => $j,
            'seuil'    => $seuil,
            'gauche'   => $this->construire($gauche, $profondeur + 1, $hauteurMax),
            'droite'   => $this->construire($droite, $profondeur + 1, $hauteurMax),
        ];
    }

    private function longueur(array $x, array $noeud): float
    {
        $profondeur = 0;
        while (! isset($noeud['taille'])) {
            $noeud = $x[$noeud['variable']] < $noeud['seuil'] ? $noeud['gauche'] : $noeud['droite'];
            $profondeur++;
        }

        return $profondeur + self::c($noeud['taille']);
    }

    // Réel uniforme dans [0, 1[
    private function aleatoire01(): float
    {
        return $this->hasard->getInt(0, PHP_INT_MAX - 1) / PHP_INT_MAX;
    }
}
