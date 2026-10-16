<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\AnalyseQualite;
use App\Models\PointPrelevement;
use App\Models\Zone;
use Illuminate\Http\Request;

// Qualité de l'eau côté citoyens : consultation uniquement (aucune saisie)
class QualiteEauController extends Controller
{
    // Nombre de mois affichés dans le graphique d'évolution de la conformité
    private const MOIS = 8;

    private const NOMS_MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    public function index(Request $request)
    {
        // Seuls les points en service sont montrés au public
        $points = PointPrelevement::query()
            ->where('actif', true)
            ->with(['reseau.zone', 'derniereAnalyse'])
            ->when($request->commune, fn ($query, $commune) => $query->whereHas('reseau.zone', fn ($q) => $q->where('commune', $commune)))
            ->when($request->q, function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                      ->orWhere('adresse', 'like', "%{$recherche}%");
                });
            })
            ->orderBy('nom')
            ->get();

        // Analyses des derniers mois sur ces points (indicateurs + graphique)
        $debut = now()->startOfMonth()->subMonths(self::MOIS - 1);
        $analyses = AnalyseQualite::whereIn('point_id', $points->pluck('id'))
            ->whereDate('date_prelevement', '>=', $debut->toDateString())
            ->get(['date_prelevement', 'conforme']);

        $dernieres = $points->pluck('derniereAnalyse')->filter();

        return view('front.qualite.index', [
            'points'      => $points,
            'communes'    => $this->communes(),
            'indicateurs' => [
                'points'       => $points->count(),
                'bonnes'       => $dernieres->where('conforme', true)->count(),
                'mauvaises'    => $dernieres->where('conforme', false)->count(),
                'taux'         => $analyses->isEmpty() ? null : round($analyses->where('conforme', true)->count() / $analyses->count() * 100, 1),
                'nbAnalyses'   => $analyses->count(),
            ],
            'parMois'     => $this->conformiteParMois($analyses, $debut),
            'carte'       => $this->pointsPourCarte($points),
        ]);
    }

    // Fiche publique d'un point : dernière analyse expliquée simplement + évolution + historique
    public function show(PointPrelevement $point)
    {
        abort_unless($point->actif, 404);

        $point->load(['reseau.zone', 'analyses' => fn ($q) => $q->orderByDesc('date_prelevement')->orderByDesc('id')]);

        return view('front.qualite.show', [
            'point'     => $point,
            'derniere'  => $point->analyses->first(),
            'taux'      => $point->tauxConformite(),
            'evolution' => $point->evolution(),
        ]);
    }

    // Communes qui ont au moins un point de prélèvement en service (liste du filtre)
    private function communes(): array
    {
        return Zone::whereHas('reseaux.pointsPrelevement', fn ($q) => $q->where('actif', true))
            ->orderBy('commune')
            ->pluck('commune')
            ->unique()
            ->values()
            ->all();
    }

    // Taux de conformité de chaque mois : [['mois' => 'mars 2026', 'total' => 10, 'conformes' => 8, 'taux' => 80.0], ...]
    private function conformiteParMois($analyses, $debut): array
    {
        $parMois = $analyses->groupBy(fn (AnalyseQualite $a) => $a->date_prelevement->format('Y-m'));
        $resultat = [];

        for ($i = 0; $i < self::MOIS; $i++) {
            $mois = $debut->copy()->addMonths($i);
            $liste = $parMois->get($mois->format('Y-m'), collect());
            $conformes = $liste->where('conforme', true)->count();

            $resultat[] = [
                'mois'      => self::NOMS_MOIS[$mois->month - 1] . ' ' . $mois->year,
                'total'     => $liste->count(),
                'conformes' => $conformes,
                'taux'      => $liste->isEmpty() ? null : round($conformes / $liste->count() * 100, 1),
            ];
        }

        return $resultat;
    }

    // Points placés sur la carte, colorés selon leur dernière analyse
    private function pointsPourCarte($points): array
    {
        return $points
            ->filter(fn (PointPrelevement $p) => $p->latitude !== null && $p->longitude !== null)
            ->map(fn (PointPrelevement $p) => $p->pourCarte() + ['url' => route('qualite.show', $p)])
            ->values()
            ->all();
    }
}
