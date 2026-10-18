<?php

namespace App\Http\Controllers\Admin\Renovation;

use App\Http\Controllers\Controller;
use App\Models\SimulationFinancement;
use App\Services\Renovation\AnalyseFinancementIA;
use App\Services\Renovation\CandidatsWhatIf;
use App\Services\Renovation\MoteurWhatIf;
use App\Services\Renovation\PlanPluriannuel;
use App\Services\Renovation\RobustesseScenario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/*
 * Binôme 3 — AquaSecure What If? Engine.
 * Toutes les simulations sont recalculées côté serveur à partir des données réelles du moment :
 * les résultats envoyés par le navigateur ne sont jamais réutilisés. Aucune écriture en base ici.
 */
class WhatIfController extends Controller
{
    public function index()
    {
        Gate::authorize('create', SimulationFinancement::class);

        $candidats = CandidatsWhatIf::charger();
        $besoin = MoteurWhatIf::comparer($candidats, 0)['besoin_total'];

        return view('admin.renovation.what-if.index', [
            'candidats'   => $candidats,
            'besoinTotal' => $besoin,
            // Budget proposé au départ : la moitié des besoins, arrondie à 10 000 DT (simple point de départ)
            'budgetInitial' => (int) min(MoteurWhatIf::BUDGET_MAX, max(10000, round($besoin / 2 / 10000) * 10000)),
            'budgetMax'   => (int) min(MoteurWhatIf::BUDGET_MAX, max(100000, ceil($besoin * 1.2 / 100000) * 100000)),
            'strategies'  => MoteurWhatIf::STRATEGIES,
            'iaConfiguree' => AnalyseFinancementIA::iaConfiguree(),
            'recentes'    => SimulationFinancement::where('user_id', auth()->id())->latest()->limit(5)->get(),
        ]);
    }

    public function simuler(Request $request): JsonResponse
    {
        Gate::authorize('create', SimulationFinancement::class);
        $p = self::parametres($request);
        $candidats = CandidatsWhatIf::charger();

        $comparaison = MoteurWhatIf::comparer($candidats, $p['budget'], $p);

        return response()->json([
            'comparaison' => $comparaison,
            'robustesse'  => RobustesseScenario::pourComparaison($comparaison, $p['incertitude_estime'], $p['incertitude_devis']),
            'strategie'   => $p['strategie'],
            'empreinte'   => CandidatsWhatIf::empreinte($candidats),
        ]);
    }

    public function analyser(Request $request): JsonResponse
    {
        Gate::authorize('create', SimulationFinancement::class);
        set_time_limit(120);
        $p = self::parametres($request);

        $comparaison = MoteurWhatIf::comparer(CandidatsWhatIf::charger(), $p['budget'], $p);

        return response()->json(AnalyseFinancementIA::analyserScenarios($comparaison));
    }

    // Plan pluriannuel : le moteur rejoué sur plusieurs années (hypothèses affichées, aucune écriture)
    public function pluriannuel(Request $request)
    {
        Gate::authorize('create', SimulationFinancement::class);

        $candidats = CandidatsWhatIf::charger();
        $besoin = MoteurWhatIf::comparer($candidats, 0)['besoin_total'];
        $plan = null;

        if ($request->filled('budget')) {
            $donnees = $request->validate([
                'budget'    => ['required', 'numeric', 'min:1000', 'max:' . MoteurWhatIf::BUDGET_MAX],
                'annees'    => ['required', 'integer', 'min:1', 'max:' . PlanPluriannuel::ANNEES_MAX],
                'strategie' => ['required', Rule::in(array_keys(MoteurWhatIf::STRATEGIES))],
                'report'    => ['nullable', 'boolean'],
            ], [
                'budget.min' => 'Le budget annuel doit être d\'au moins 1 000 DT.',
                'budget.max' => 'Le budget annuel ne peut pas dépasser 100 000 000 DT.',
            ]);

            $plan = PlanPluriannuel::calculer($candidats, (float) $donnees['budget'], (int) $donnees['annees'],
                $donnees['strategie'], $request->boolean('report'), (int) now()->year + 1);
        }

        return view('admin.renovation.what-if.pluriannuel', [
            'plan'       => $plan,
            'besoin'     => $besoin,
            'strategies' => MoteurWhatIf::STRATEGIES,
            'budgetPropose' => (int) max(10000, round($besoin / 3 / 10000) * 10000),
        ]);
    }

    /** Paramètres d'une simulation : contrôlés côté serveur (le curseur du navigateur n'est pas fiable). */
    public static function parametres(Request $request): array
    {
        $donnees = $request->validate([
            'budget'          => ['required', 'numeric', 'min:0', 'max:' . MoteurWhatIf::BUDGET_MAX],
            'strategie'       => ['required', Rule::in(array_keys(MoteurWhatIf::STRATEGIES))],
            'exclus'          => ['nullable', 'array', 'max:500'],
            'exclus.*'        => ['integer', 'min:1'],
            'partiel'         => ['nullable', 'boolean'],
            'inclure_estimes' => ['nullable', 'boolean'],
            'incertitude_estime' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'incertitude_devis'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'budget.required' => 'Indiquez un budget hypothétique.',
            'budget.numeric'  => 'Le budget doit être un montant en dinars.',
            'budget.min'      => 'Le budget ne peut pas être négatif.',
            'budget.max'      => 'Le budget ne peut pas dépasser 100 000 000 DT.',
            'strategie.in'    => 'Stratégie inconnue.',
        ]);

        return [
            'budget'          => round((float) $donnees['budget'], 2),
            'strategie'       => $donnees['strategie'],
            'exclus'          => array_map('intval', $donnees['exclus'] ?? []),
            'partiel'         => $request->boolean('partiel'),
            'inclure_estimes' => $request->boolean('inclure_estimes', true),
            // Hypothèses d'incertitude des coûts pour le test de robustesse (en %, converties en fraction)
            'incertitude_estime' => (float) ($donnees['incertitude_estime'] ?? 30) / 100,
            'incertitude_devis'  => (float) ($donnees['incertitude_devis'] ?? 10) / 100,
        ];
    }
}
