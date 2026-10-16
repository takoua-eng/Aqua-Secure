<?php

namespace App\Services;

use App\Models\ReseauEau;
use App\Models\Zone;
use Illuminate\Support\Collection;

/*
 * Regroupement automatique des zones qui se ressemblent (apprentissage non supervisé : K-means).
 *
 * Chaque zone devient un point à 5 dimensions (caractéristiques ramenées entre 0 et 1) :
 *   vétusté moyenne, part des éléments en mauvais état, score de risque moyen,
 *   population, part des matériaux à risque (amiante-ciment, fonte).
 * K-means cherche K groupes de points proches ; les groupes sont ensuite nommés
 * du plus risqué au moins risqué. Les zones sans réseau sont mises à part.
 */
class GroupesZones
{
    public const CARACTERISTIQUES = [
        'vetuste'    => 'Vétusté moyenne',
        'degradee'   => 'Part en mauvais état',
        'risque'     => 'Risque moyen',
        'population' => 'Population',
        'materiaux'  => 'Matériaux à risque',
    ];

    // Noms des groupes, du plus risqué au moins risqué, selon le nombre de groupes
    private const NOMS = [
        1 => [['Zones analysées', 'azure']],
        2 => [['Prioritaire', 'red'], ['Sain', 'green']],
        3 => [['Prioritaire', 'red'], ['À surveiller', 'yellow'], ['Sain', 'green']],
    ];

    private const ESSAIS = 10;        // K-means est relancé plusieurs fois, on garde le meilleur résultat
    private const ITERATIONS = 100;

    /**
     * @return array{groupes: list<array>, sansReseau: Collection, parZone: array<int, array{libelle: string, couleur: string}>}
     */
    public static function calculer(?Collection $zones = null): array
    {
        $zones ??= Zone::with(['reseaux' => fn ($q) => $q->withMax('historiques as etat_depuis', 'created_at')])->get();

        [$avecReseaux, $sansReseau] = $zones->partition(fn (Zone $z) => $z->reseaux->isNotEmpty());
        $avecReseaux = $avecReseaux->values();

        if ($avecReseaux->isEmpty()) {
            return ['groupes' => [], 'sansReseau' => $sansReseau->values(), 'parZone' => []];
        }

        // 1. Caractéristiques brutes de chaque zone, puis mise à l'échelle 0-1 (min-max)
        $brutes = $avecReseaux->map(fn (Zone $zone) => self::caracteristiques($zone))->all();
        $points = self::normaliser($brutes);

        // 2. K-means (au plus 3 groupes, et jamais plus de groupes que de zones)
        $k = min(3, count($points));
        [$affectations] = self::kmeans($points, $k);

        // 3. Construire les groupes et les trier du plus risqué au moins risqué
        $groupes = collect(range(0, $k - 1))
            ->map(function (int $g) use ($affectations, $avecReseaux, $brutes) {
                $indices = array_keys($affectations, $g, true);

                return [
                    'zones'   => collect($indices)->map(fn ($i) => $avecReseaux[$i])->sortBy('nom')->values(),
                    'moyenne' => collect(self::CARACTERISTIQUES)->keys()
                        ->mapWithKeys(fn ($c) => [$c => collect($indices)->avg(fn ($i) => $brutes[$i][$c])])
                        ->all(),
                ];
            })
            ->filter(fn ($groupe) => $groupe['zones']->isNotEmpty())
            ->sortByDesc(fn ($groupe) => $groupe['moyenne']['risque'])
            ->values();

        $noms = self::NOMS[$groupes->count()] ?? self::NOMS[3];
        $parZone = [];

        $groupes = $groupes->map(function ($groupe, $rang) use ($noms, &$parZone) {
            [$libelle, $couleur] = $noms[$rang];
            foreach ($groupe['zones'] as $zone) {
                $parZone[$zone->id] = ['libelle' => $libelle, 'couleur' => $couleur];
            }

            return $groupe + ['libelle' => $libelle, 'couleur' => $couleur];
        })->all();

        return ['groupes' => $groupes, 'sansReseau' => $sansReseau->values(), 'parZone' => $parZone];
    }

    /** Caractéristiques brutes (non mises à l'échelle) d'une zone qui a des réseaux */
    private static function caracteristiques(Zone $zone): array
    {
        $reseaux = $zone->reseaux->each(fn (ReseauEau $r) => $r->setRelation('zone', $zone));

        return [
            // âge / durée de vie du matériau (1 = en fin de vie), plafonné à 1,5
            'vetuste'    => $reseaux->avg(fn ($r) => min(1.5, $r->age() / (ScoreRisque::DUREE_VIE[$r->materiau] ?? 50))),
            'degradee'   => $reseaux->filter(fn ($r) => $r->etat !== 'bon')->count() / $reseaux->count(),
            'risque'     => $reseaux->avg(fn ($r) => ScoreRisque::pourReseau($r)['score']),
            'population' => (float) $zone->population,
            'materiaux'  => $reseaux->filter(fn ($r) => in_array($r->materiau, ['amiante_ciment', 'fonte']))->count() / $reseaux->count(),
        ];
    }

    /** Met chaque caractéristique entre 0 et 1, pour qu'aucune n'écrase les autres (la population surtout) */
    private static function normaliser(array $brutes): array
    {
        $cles = array_keys(self::CARACTERISTIQUES);
        $min = $max = [];
        foreach ($cles as $c) {
            $valeurs = array_column($brutes, $c);
            $min[$c] = min($valeurs);
            $max[$c] = max($valeurs);
        }

        return array_map(fn ($ligne) => array_map(
            fn ($c) => $max[$c] > $min[$c] ? ($ligne[$c] - $min[$c]) / ($max[$c] - $min[$c]) : 0.0,
            $cles
        ), $brutes);
    }

    /**
     * K-means avec initialisation k-means++ ; graine fixe pour que les groupes ne changent pas à chaque affichage.
     *
     * @return array{0: list<int>, 1: float} affectation de chaque point et inertie (somme des distances²)
     */
    private static function kmeans(array $points, int $k): array
    {
        mt_srand(42);
        $meilleur = null;

        for ($essai = 0; $essai < self::ESSAIS; $essai++) {
            $centres = self::initialiser($points, $k);
            $affectations = [];

            for ($i = 0; $i < self::ITERATIONS; $i++) {
                // Chaque point va vers le centre le plus proche
                $nouvelles = array_map(fn ($p) => self::plusProche($p, $centres), $points);
                if ($nouvelles === $affectations) {
                    break;
                }
                $affectations = $nouvelles;

                // Chaque centre devient la moyenne de ses points
                foreach ($centres as $g => $centre) {
                    $membres = array_values(array_intersect_key($points, array_filter($affectations, fn ($a) => $a === $g)));
                    if ($membres) {
                        $centres[$g] = array_map(fn (...$v) => array_sum($v) / count($v), ...$membres);
                    }
                }
            }

            $inertie = array_sum(array_map(fn ($p, $a) => self::distance2($p, $centres[$a]), $points, $affectations));
            if ($meilleur === null || $inertie < $meilleur[1]) {
                $meilleur = [$affectations, $inertie];
            }
        }

        return $meilleur;
    }

    // k-means++ : premier centre au hasard, les suivants de préférence loin des centres déjà choisis
    private static function initialiser(array $points, int $k): array
    {
        $centres = [$points[mt_rand(0, count($points) - 1)]];

        while (count($centres) < $k) {
            $poids = array_map(fn ($p) => min(array_map(fn ($c) => self::distance2($p, $c), $centres)), $points);
            $total = array_sum($poids);

            if ($total == 0) {
                $centres[] = $points[mt_rand(0, count($points) - 1)];
                continue;
            }

            $tirage = mt_rand() / mt_getrandmax() * $total;
            foreach ($poids as $i => $w) {
                $tirage -= $w;
                if ($tirage <= 0) {
                    $centres[] = $points[$i];
                    break;
                }
            }
        }

        return $centres;
    }

    private static function plusProche(array $point, array $centres): int
    {
        $distances = array_map(fn ($c) => self::distance2($point, $c), $centres);

        return array_search(min($distances), $distances, true);
    }

    private static function distance2(array $a, array $b): float
    {
        return array_sum(array_map(fn ($x, $y) => ($x - $y) ** 2, $a, $b));
    }
}
