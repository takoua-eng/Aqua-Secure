<?php

namespace App\Services\Renovation\Apprentissage;

/*
 * Binôme 3 — générateur pseudo-aléatoire REPRODUCTIBLE pour les simulations (historique synthétique, Monte-Carlo).
 *
 * Générateur congruentiel linéaire (constantes de « Numerical Recipes ») : même graine => même suite de nombres.
 * Il a son propre état : il ne touche pas au générateur global de PHP (mt_rand) utilisé ailleurs dans l'application.
 * Il ne sert qu'à des calculs de simulation, JAMAIS à la sécurité (jetons, mots de passe…) : random_int() est fait pour ça.
 */
final class GenerateurReproductible
{
    private const MODULE = 4294967296;   // 2^32

    private int $etat;

    public function __construct(int $graine)
    {
        $this->etat = $graine % self::MODULE;
    }

    /** Entier dans [0, 2^32[ (le produit tient dans un entier PHP 64 bits, sans dépassement). */
    public function suivant(): int
    {
        $this->etat = ($this->etat * 1664525 + 1013904223) % self::MODULE;

        return $this->etat;
    }

    /** Nombre réel dans [0, 1[. */
    public function reel(): float
    {
        return $this->suivant() / self::MODULE;
    }

    /** Entier dans [min, max] (bornes comprises). */
    public function entier(int $min, int $max): int
    {
        return $min + (int) floor($this->reel() * ($max - $min + 1));
    }

    /** Loi normale centrée réduite (méthode de Box-Muller). */
    public function gauss(): float
    {
        $u = max(1e-12, $this->reel());

        return sqrt(-2 * log($u)) * cos(2 * M_PI * $this->reel());
    }
}
