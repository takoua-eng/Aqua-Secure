<?php

namespace App\Services;

use App\Models\AnalyseQualite;
use App\Models\PointPrelevement;
use App\Models\ReseauEau;
use Illuminate\Support\Facades\DB;

// Indicateurs de qualité de l'eau utilisés hors du module : tableau de bord, zones, réseaux
class QualiteEau
{
    // Widgets du tableau de bord : conformité du mois en cours et points à surveiller
    public static function tableauDeBord(): array
    {
        $duMois = AnalyseQualite::whereDate('date_prelevement', '>=', now()->startOfMonth()->toDateString())
            ->get(['conforme']);
        $conformes = $duMois->where('conforme', true)->count();

        // Points en service dont la dernière analyse est non conforme, ou sans analyse récente
        $aSurveiller = PointPrelevement::where('actif', true)
            ->with(['reseau.zone', 'derniereAnalyse'])
            ->orderBy('code')
            ->get()
            ->map(function (PointPrelevement $point) {
                $derniere = $point->derniereAnalyse;
                // Raisons : [['texte' => 'pH trop acide : …', 'retard' => false], ...]
                $raisons = collect($derniere?->parametresHorsNorme() ?? [])
                    ->map(fn ($texte) => ['texte' => $texte, 'retard' => false])
                    ->values()->all();
                if ($retard = PointPrelevement::retardAnalyse(true, $derniere?->date_prelevement)) {
                    $raisons[] = ['texte' => $retard, 'retard' => true];
                }

                return ['point' => $point, 'derniere' => $derniere, 'raisons' => $raisons, 'contamine' => $derniere && ! $derniere->conforme];
            })
            ->filter(fn ($ligne) => $ligne['raisons'])
            // Les eaux non conformes d'abord, puis les retards
            ->sortByDesc('contamine')
            ->values();

        return [
            'mois'         => $duMois->count(),
            'conformes'    => $conformes,
            'nonConformes' => $duMois->count() - $conformes,
            'taux'         => $duMois->isEmpty() ? null : round($conformes / $duMois->count() * 100, 1),
            'aSurveiller'  => $aSurveiller,
        ];
    }

    // Conformité des analyses de chaque zone : [zone_id => ['taux' => 87.5, 'total' => 16]]
    public static function parZone(): array
    {
        return DB::table('analyses_qualite')
            ->join('points_prelevement', 'points_prelevement.id', '=', 'analyses_qualite.point_id')
            ->join('reseaux_eau', 'reseaux_eau.id', '=', 'points_prelevement.reseau_id')
            ->groupBy('reseaux_eau.zone_id')
            ->selectRaw('reseaux_eau.zone_id, COUNT(*) as total, SUM(CASE WHEN analyses_qualite.conforme THEN 1 ELSE 0 END) as conformes')
            ->get()
            ->mapWithKeys(fn ($ligne) => [$ligne->zone_id => [
                'total' => (int) $ligne->total,
                'taux'  => round($ligne->conformes / $ligne->total * 100, 1),
            ]])
            ->all();
    }

    // Qualité de l'eau d'un réseau : ses points, leur dernière analyse et le taux global
    public static function pourReseau(ReseauEau $reseau): array
    {
        $points = $reseau->pointsPrelevement()
            ->with('derniereAnalyse')
            ->withCount(['analyses', 'analyses as analyses_conformes_count' => fn ($q) => $q->where('conforme', true)])
            ->orderBy('code')
            ->get();

        $total = $points->sum('analyses_count');

        return [
            'points' => $points,
            'total'  => $total,
            'taux'   => $total ? round($points->sum('analyses_conformes_count') / $total * 100, 1) : null,
        ];
    }
}
