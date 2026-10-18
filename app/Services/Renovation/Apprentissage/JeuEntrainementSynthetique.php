<?php

namespace App\Services\Renovation\Apprentissage;

/*
 * Binôme 3 — historique SYNTHÉTIQUE de projets passés, pour entraîner les modèles de démonstration.
 *
 * Pourquoi : les devis et financements de la base de démonstration ont été saisis à la main, sans régularité ;
 * un modèle ne peut rien en apprendre. Comme le seeder du module Qualité de l'eau (binôme 5), on génère un historique
 * qui suit des règles réalistes ET DOCUMENTÉES (affichées sur la page « Modèles IA »), avec une part de hasard.
 * Rien n'est écrit en base : l'historique est recalculé à l'identique à chaque entraînement (graine fixe).
 *
 * Avec de vrais devis et financements (SONEDE, ministère…), il suffit de désactiver ce jeu (RENOVATION_JEU_SYNTHETIQUE=false) :
 * le même code apprend alors uniquement sur la base.
 */
class JeuEntrainementSynthetique
{
    public const GRAINE = 2026;

    public const TAILLE = 150;

    // ---------- Règles du coût (documentées) ----------

    public const COUT_BASE = ['canalisation' => 150000, 'reservoir' => 420000, 'station' => 950000];

    public const FACTEUR_MATERIAU = ['amiante_ciment' => 1.35, 'fonte' => 1.15, 'acier' => 1.10, 'beton' => 1.05, 'pvc' => 0.90, 'pehd' => 0.85];

    public const FACTEUR_ETAT = ['bon' => 1.0, 'degrade' => 1.15, 'hors_service' => 1.35];

    public const BRUIT = 0.12;   // écart-type du hasard multiplicatif (log-normal) : environ ± 12 %

    private const MATERIAUX_PAR_TYPE = [
        'canalisation' => ['fonte', 'pvc', 'pehd', 'amiante_ciment', 'acier'],
        'reservoir'    => ['beton', 'beton', 'acier'],
        'station'      => ['acier', 'beton'],
    ];

    /** Règles lisibles, affichées sur la page « Modèles IA ». */
    public static function regles(): array
    {
        return [
            'cout' => [
                'Coût de base : canalisation 150 000 DT, réservoir 420 000 DT, station 950 000 DT.',
                'Matériau : amiante-ciment ×1,35 · fonte ×1,15 · acier ×1,10 · béton ×1,05 · PVC ×0,90 · PEHD ×0,85.',
                'Âge : +0,8 % par année de service.',
                'État : dégradé ×1,15 · hors service ×1,35 (travaux plus lourds).',
                'Population de la zone : jusqu\'à +25 % pour 50 000 habitants (travaux en zone dense).',
                'Hasard : ±12 % environ (imprévus de chantier).',
            ],
            'source' => [
                'Gros projets (plus de 600 000 DT) : surtout des bailleurs de fonds, puis l\'État.',
                'Réseaux hors service : l\'État intervient davantage (urgence).',
                'Petits projets du Nord : souvent la municipalité.',
                'Régions du Centre et du Sud : davantage de bailleurs (programmes de développement régional).',
                'Petits projets : quelques dons.',
                'Le tirage reste aléatoire : aucune règle n\'est absolue.',
            ],
        ];
    }

    /**
     * @return list<array{brut: array, cout: float, source: string, commune: string, gouvernorat: string}>
     */
    public static function generer(int $taille = self::TAILLE): array
    {
        mt_srand(self::GRAINE);
        $communes = [];
        foreach (config('communes') as $gouvernorat => $liste) {
            foreach (array_keys($liste) as $commune) {
                $communes[] = [$gouvernorat, $commune];
            }
        }

        $exemples = [];
        for ($i = 0; $i < $taille; $i++) {
            $type = self::tirer(['canalisation' => 60, 'reservoir' => 25, 'station' => 15]);
            $materiaux = self::MATERIAUX_PAR_TYPE[$type];
            $materiau = $materiaux[mt_rand(0, count($materiaux) - 1)];
            $age = mt_rand(5, 65);
            $etat = self::tirer($age > 40 ? ['bon' => 20, 'degrade' => 50, 'hors_service' => 30]
                : ['bon' => 55, 'degrade' => 35, 'hors_service' => 10]);
            $population = mt_rand(5, 60) * 1000;
            [$gouvernorat, $commune] = $communes[mt_rand(0, count($communes) - 1)];

            $cout = self::COUT_BASE[$type] * self::FACTEUR_MATERIAU[$materiau] * (1 + 0.008 * $age)
                * self::FACTEUR_ETAT[$etat] * (1 + 0.25 * min(1, $population / 50000))
                * exp(self::BRUIT * self::gauss());
            $cout = round($cout / 1000) * 1000;

            $exemples[] = [
                'brut'        => ['type' => $type, 'materiau' => $materiau, 'age' => $age, 'etat' => $etat, 'population' => $population],
                'cout'        => (float) $cout,
                'source'      => self::source($cout, $etat, $gouvernorat),
                'commune'     => $commune,
                'gouvernorat' => $gouvernorat,
            ];
        }
        mt_srand();

        return $exemples;
    }

    // Source de financement tirée selon des poids qui dépendent du projet
    private static function source(float $cout, string $etat, string $gouvernorat): string
    {
        $region = \App\Services\Renovation\ModeleSourceAppris::region($gouvernorat);
        $poids = ['Etat' => 30, 'municipalite' => 20, 'bailleur' => 20, 'don' => 5];

        if ($cout >= 600000) {
            $poids['bailleur'] += 45;
            $poids['municipalite'] -= 15;
            $poids['don'] -= 4;
        } elseif ($cout < 200000) {
            $poids['municipalite'] += $region === 'Nord' ? 35 : 10;
            $poids['don'] += 10;
            $poids['bailleur'] -= 12;
        }
        if ($etat === 'hors_service') {
            $poids['Etat'] += 30;
        }
        if ($region !== 'Nord') {
            $poids['bailleur'] += 18;
        }

        return self::tirer(array_map(fn ($p) => max(1, $p), $poids));
    }

    private static function tirer(array $poids): string
    {
        $r = mt_rand(1, array_sum($poids));
        foreach ($poids as $valeur => $p) {
            if (($r -= $p) <= 0) {
                return $valeur;
            }
        }

        return array_key_last($poids);
    }

    // Loi normale centrée réduite (méthode de Box-Muller)
    private static function gauss(): float
    {
        $u = max(1e-12, mt_rand() / mt_getrandmax());
        $v = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
}
