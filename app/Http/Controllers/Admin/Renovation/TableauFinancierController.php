<?php

namespace App\Http\Controllers\Admin\Renovation;

use App\Http\Controllers\Controller;
use App\Models\ProjetRenovation;
use App\Services\Renovation\AnalyseFinancementIA;
use App\Services\Renovation\DetectionRisquesFinanciers;
use App\Services\Renovation\RapportMensuel;
use App\Services\Renovation\SuiviBudget;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

// Binôme 3 — Smart Budget Monitoring : tableau de bord financier et alertes
class TableauFinancierController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', ProjetRenovation::class);

        return view('admin.renovation.tableau', [
            'suivi'   => SuiviBudget::calculer(),
            'alertes' => DetectionRisquesFinanciers::pourBase(),
            'iaConfiguree' => AnalyseFinancementIA::iaConfiguree(),
        ]);
    }

    // Rapport mensuel PDF : chiffres calculés + synthèse rédigée par l'IA (ou le mode secours)
    public function rapportMensuel(Request $request)
    {
        Gate::authorize('viewAny', ProjetRenovation::class);
        set_time_limit(120);

        $donnees = $request->validate(['mois' => ['nullable', 'date_format:Y-m', 'before_or_equal:' . now()->format('Y-m')]], [
            'mois.date_format'     => 'Mois invalide (format attendu : AAAA-MM).',
            'mois.before_or_equal' => 'Le rapport ne peut pas porter sur un mois futur.',
        ]);
        $mois = Carbon::createFromFormat('Y-m-d', ($donnees['mois'] ?? now()->format('Y-m')) . '-01');

        $chiffres = RapportMensuel::donnees($mois);
        $synthese = AnalyseFinancementIA::syntheseMensuelle($chiffres);

        return Pdf::loadView('admin.renovation.rapport-mensuel', ['d' => $chiffres, 'synthese' => $synthese, 'genereLe' => now()])
            ->setPaper('a4')
            ->download('rapport-financement-' . $mois->format('Y-m') . '.pdf');
    }

    // Explication des alertes par l'IA (ou le mode secours), demandée en arrière-plan
    public function expliquerAlertes(): JsonResponse
    {
        Gate::authorize('viewAny', ProjetRenovation::class);
        set_time_limit(120);

        $suivi = SuiviBudget::calculer();
        $resume = [
            'nb_projets'          => $suivi['nb_projets'],
            'total_finance_dt'    => $suivi['total_finance'],
            'nb_sans_financement' => $suivi['sans_financement']->count(),
            'taux_couverture_cout_declare_pct' => $suivi['couverture']['taux'],
        ];

        return response()->json(AnalyseFinancementIA::expliquerAlertes(DetectionRisquesFinanciers::pourBase(), $resume));
    }
}
