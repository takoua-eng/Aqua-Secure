<?php

namespace App\Services\Renovation;

use Illuminate\Support\Facades\DB;

/*
 * Binôme 3 — équité territoriale des financements de rénovation, par gouvernorat.
 *
 * Données réelles uniquement :
 *   - gouvernorat d'une commune : config/communes.php (liste officielle utilisée par le projet) ;
 *   - population : somme des populations des ZONES ENREGISTRÉES dans le gouvernorat (pas un recensement) ;
 *   - financements : montants enregistrés sur les projets dont le réseau est dans le gouvernorat.
 * Indicateurs : DT investis par habitant, courbe de Lorenz et indice de Gini
 * (0 = répartition parfaitement égale par habitant, 1 = tout l'argent au même endroit).
 */
class EquiteTerritoriale
{
    private static ?array $cache = null;

    /** Gouvernorat d'une commune (null si la commune n'est pas dans la configuration). */
    public static function gouvernoratDe(?string $commune): ?string
    {
        foreach (config('communes') as $gouvernorat => $communes) {
            if (isset($communes[$commune])) {
                return $gouvernorat;
            }
        }

        return null;
    }

    /**
     * Une ligne par gouvernorat de la configuration (24), même sans zone ni projet.
     *
     * @return array<string, array{gouvernorat: string, population: int, finance: float, projets: int, en_cours: int, par_habitant: ?float, lat: float, lng: float}>
     */
    public static function parGouvernorat(): array
    {
        return self::$cache ??= self::calculer();
    }

    public static function oublier(): void
    {
        self::$cache = null;
    }

    private static function calculer(): array
    {
        $lignes = [];
        foreach (config('communes') as $gouvernorat => $communes) {
            $centre = reset($communes);
            $lignes[$gouvernorat] = ['gouvernorat' => $gouvernorat, 'population' => 0, 'finance' => 0.0, 'projets' => 0,
                'en_cours' => 0, 'par_habitant' => null, 'lat' => $centre[0], 'lng' => $centre[1]];
        }

        foreach (DB::table('zones')->select('commune', DB::raw('SUM(population) as habitants'))->groupBy('commune')->get() as $z) {
            if ($g = self::gouvernoratDe($z->commune)) {
                $lignes[$g]['population'] += (int) $z->habitants;
            }
        }

        $projets = DB::table('projets_renovation')
            ->join('reseaux_eau', 'reseaux_eau.id', '=', 'projets_renovation.reseau_id')
            ->join('zones', 'zones.id', '=', 'reseaux_eau.zone_id')
            ->leftJoinSub(DB::table('financements')->select('projet_id', DB::raw('SUM(montant) as total'))->groupBy('projet_id'),
                'f', 'f.projet_id', '=', 'projets_renovation.id')
            ->get(['zones.commune', 'projets_renovation.statut', 'f.total']);

        foreach ($projets as $p) {
            if ($g = self::gouvernoratDe($p->commune)) {
                $lignes[$g]['projets']++;
                $lignes[$g]['en_cours'] += $p->statut === 'en_cours' ? 1 : 0;
                $lignes[$g]['finance'] += (float) $p->total;
            }
        }

        foreach ($lignes as &$l) {
            $l['finance'] = round($l['finance'], 2);
            $l['par_habitant'] = $l['population'] > 0 ? round($l['finance'] / $l['population'], 2) : null;
        }

        return $lignes;
    }

    /**
     * Courbe de Lorenz et indice de Gini des DT par habitant (gouvernorats avec une population connue).
     * Calcul pur : testable sans base.
     *
     * @param list<array{population: int, finance: float}> $territoires
     * @return array{gini: ?float, points: list<array{0: float, 1: float}>}
     */
    public static function lorenz(array $territoires): array
    {
        $t = array_values(array_filter($territoires, fn ($x) => $x['population'] > 0));
        $population = array_sum(array_column($t, 'population'));
        $finance = array_sum(array_column($t, 'finance'));

        if (count($t) < 2 || $population <= 0 || $finance <= 0) {
            return ['gini' => null, 'points' => [[0, 0], [100, 100]]];
        }

        usort($t, fn ($a, $b) => $a['finance'] / $a['population'] <=> $b['finance'] / $b['population']);

        $points = [[0.0, 0.0]];
        $x = $y = 0.0;
        $aire = 0.0;   // aire sous la courbe de Lorenz (méthode des trapèzes)
        foreach ($t as $ligne) {
            $nx = $x + $ligne['population'] / $population;
            $ny = $y + $ligne['finance'] / $finance;
            $aire += ($nx - $x) * ($ny + $y) / 2;
            [$x, $y] = [$nx, $ny];
            $points[] = [round(100 * $x, 1), round(100 * $y, 1)];
        }

        return ['gini' => round(max(0, 1 - 2 * $aire), 3), 'points' => $points];
    }

    /** Lecture simple de l'indice de Gini pour le gestionnaire. */
    public static function lecture(?float $gini): string
    {
        return match (true) {
            $gini === null => 'Pas assez de données pour mesurer l\'équité.',
            $gini < 0.25   => 'Répartition plutôt équilibrée entre les gouvernorats.',
            $gini < 0.45   => 'Répartition inégale : certains gouvernorats reçoivent nettement plus par habitant.',
            default        => 'Répartition très concentrée : une petite partie du territoire reçoit l\'essentiel des fonds.',
        };
    }
}
