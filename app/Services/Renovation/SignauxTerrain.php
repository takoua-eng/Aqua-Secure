<?php

namespace App\Services\Renovation;

use Illuminate\Support\Facades\DB;

/*
 * Binôme 3 — signaux « terrain » venant des autres modules, en LECTURE SEULE :
 *   - Surveillance (binôme 4)      : relevés hors seuil des capteurs installés sur le réseau, sur 30 jours ;
 *   - Qualité de l'eau (binôme 5)  : analyses non conformes des points de prélèvement du réseau, sur 90 jours.
 * Aucune table n'est créée ni modifiée ; les tables des autres binômes sont seulement lues (requêtes agrégées).
 * Le calcul se fait en 3 requêtes pour tous les réseaux, puis il est gardé en mémoire le temps de la requête.
 */
class SignauxTerrain
{
    public const JOURS_CAPTEURS = 30;

    public const JOURS_ANALYSES = 90;

    private const VIDE = ['capteurs' => 0, 'alertes' => 0, 'analyses' => 0, 'non_conformes' => 0];

    private static ?array $cache = null;

    /** @return array{capteurs: int, alertes: int, analyses: int, non_conformes: int} */
    public static function pourReseau(?int $reseauId): array
    {
        self::$cache ??= self::charger();

        return self::$cache[$reseauId] ?? self::VIDE;
    }

    public static function oublier(): void
    {
        self::$cache = null;
    }

    private static function charger(): array
    {
        $signaux = [];
        $ajouter = function ($lignes, string $cle, string $colonne) use (&$signaux) {
            foreach ($lignes as $l) {
                $signaux[$l->reseau_id] = ($signaux[$l->reseau_id] ?? self::VIDE);
                $signaux[$l->reseau_id][$cle] = (int) $l->{$colonne};
            }
        };

        $ajouter(DB::table('capteurs')->select('reseau_id', DB::raw('COUNT(*) as n'))->groupBy('reseau_id')->get(), 'capteurs', 'n');

        $ajouter(DB::table('releves')
            ->join('capteurs', 'capteurs.id', '=', 'releves.capteur_id')
            ->where('releves.hors_seuil', true)
            ->where('releves.date_releve', '>=', now()->subDays(self::JOURS_CAPTEURS))
            ->select('capteurs.reseau_id', DB::raw('COUNT(*) as n'))
            ->groupBy('capteurs.reseau_id')->get(), 'alertes', 'n');

        $analyses = DB::table('analyses_qualite')
            ->join('points_prelevement', 'points_prelevement.id', '=', 'analyses_qualite.point_id')
            ->where('analyses_qualite.date_prelevement', '>=', now()->subDays(self::JOURS_ANALYSES)->toDateString())
            ->select('points_prelevement.reseau_id', DB::raw('COUNT(*) as n'),
                DB::raw('SUM(CASE WHEN analyses_qualite.conforme = 0 THEN 1 ELSE 0 END) as nc'))
            ->groupBy('points_prelevement.reseau_id')->get();
        $ajouter($analyses, 'analyses', 'n');
        $ajouter($analyses, 'non_conformes', 'nc');

        return $signaux;
    }
}
