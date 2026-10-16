<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReseauEau;
use App\Models\Zone;
use App\Services\AnalyseZoneIA;
use App\Services\GroupesZones;
use App\Services\PlanRenovation;
use App\Services\ProbabiliteDefaillance;
use App\Services\ScoreRisque;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RisqueController extends Controller
{
    // Page "Analyse des risques" : classement des réseaux + prédictions + groupes de zones (K-means)
    public function index()
    {
        return view('admin.risques.index', $this->donneesAnalyse());
    }

    // Même contenu, en PDF à imprimer ou à joindre
    public function pdf()
    {
        return Pdf::loadView('admin.risques.pdf', $this->donneesAnalyse() + ['genereLe' => now()])
            ->setPaper('a4')
            ->download('analyse-risques-' . now()->format('Y-m-d') . '.pdf');
    }

    // Plan de renouvellement : meilleur choix d'éléments à rénover pour un budget donné
    public function plan(Request $request)
    {
        $plan = null;

        if ($request->filled('budget')) {
            $donnees = $request->validate([
                'budget' => ['required', 'integer', 'min:10000', 'max:100000000'],
            ], [
                'budget.required' => 'Le budget est obligatoire.',
                'budget.integer'  => 'Le budget doit être un nombre entier de dinars.',
                'budget.min'      => 'Le budget doit être d\'au moins 10 000 TND.',
                'budget.max'      => 'Le budget ne peut pas dépasser 100 000 000 TND.',
            ]);

            $plan = PlanRenovation::calculer((int) $donnees['budget']);
        }

        return view('admin.risques.plan', compact('plan'));
    }

    // Rapport IA d'une zone, demandé en arrière-plan (fetch) depuis la carte ou la page des risques
    public function analyserZone(Zone $zone): JsonResponse
    {
        set_time_limit(120); // la réponse de l'IA peut prendre plusieurs dizaines de secondes

        try {
            return response()->json(AnalyseZoneIA::analyser($zone));
        } catch (RuntimeException $e) {
            return response()->json(['erreur' => $e->getMessage()], 503);
        }
    }

    // Rapport d'une zone en PDF (rapport IA, ou rapport de démonstration sans clé API)
    public function rapportZonePdf(Zone $zone)
    {
        set_time_limit(120);

        try {
            $rapport = AnalyseZoneIA::analyser($zone);
        } catch (RuntimeException $e) {
            return back()->withErrors(['zone' => $e->getMessage()]);
        }

        $zone->load(['reseaux' => fn ($q) => $q->orderBy('code')->withMax('historiques as etat_depuis', 'created_at')]);
        $reseaux = $zone->reseaux->each(fn (ReseauEau $r) => $r->setRelation('zone', $zone))
            ->map(fn (ReseauEau $r) => [
                'reseau'     => $r,
                'risque'     => ScoreRisque::pourReseau($r),
                'prediction' => ProbabiliteDefaillance::pourReseau($r),
            ]);

        return Pdf::loadView('admin.risques.pdf-zone', [
            'zone'     => $zone,
            'reseaux'  => $reseaux,
            'risque'   => ScoreRisque::pourZone($zone),
            'rapport'  => $rapport,
            'genereLe' => now(),
        ])->setPaper('a4')->download('rapport-zone-' . str($zone->nom)->slug() . '.pdf');
    }

    private function donneesAnalyse(): array
    {
        $classement = ReseauEau::with('zone')
            ->withMax('historiques as etat_depuis', 'created_at')
            ->get()
            ->map(fn (ReseauEau $reseau) => [
                'reseau'     => $reseau,
                'risque'     => ScoreRisque::pourReseau($reseau),
                'prediction' => ProbabiliteDefaillance::pourReseau($reseau),
            ])
            ->sortByDesc(fn ($ligne) => $ligne['risque']['score'])
            ->values();

        $resultat = GroupesZones::calculer();

        return [
            'classement' => $classement,
            'groupes'    => $resultat['groupes'],
            'sansReseau' => $resultat['sansReseau'],
            // Nombre d'éléments par niveau de risque (pour les indicateurs en haut de page)
            'parNiveau'  => $classement->countBy(fn ($ligne) => $ligne['risque']['niveau']),
        ];
    }
}
