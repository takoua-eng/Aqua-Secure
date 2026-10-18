<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\IA\Incidents\AnalyseAnomalies;
use Illuminate\Http\Request;

// Exploitation (binôme 2) : page « IA — Analyse intelligente des incidents »
class IncidentIAController extends Controller
{
    public function index(Request $request)
    {
        $analyse = AnalyseAnomalies::charger();

        if ($request->wantsJson()) {
            return response()->json($analyse ?? ['statut' => 'aucune_analyse']);
        }

        // Filtres sur les anomalies de la dernière analyse : réseau et niveau
        $anomalies = collect($analyse['anomalies'] ?? []);
        $reseaux = $anomalies->pluck('reseau')->unique()->sort()->values();
        $reseau = is_string($request->query('reseau')) && $reseaux->contains($request->query('reseau')) ? $request->query('reseau') : null;
        $niveau = in_array($request->query('niveau'), ['forte', 'moderee'], true) ? $request->query('niveau') : null;

        return view('admin.incidents.ia', [
            'analyse'   => $analyse,
            'anomalies' => $anomalies
                ->when($reseau, fn ($liste) => $liste->where('reseau', $reseau))
                ->when($niveau, fn ($liste) => $liste->where('niveau', $niveau))
                ->values(),
            'reseaux'   => $reseaux,
            'reseau'    => $reseau,
            'niveau'    => $niveau,
        ]);
    }

    // Lance une nouvelle analyse (lecture seule de la base, résultat enregistré dans storage/)
    public function lancer()
    {
        $analyse = AnalyseAnomalies::executer();

        $message = $analyse['statut'] === 'termine'
            ? "Analyse terminée : {$analyse['signalees']} observation(s) inhabituelle(s) sur {$analyse['observations']}."
            : "Analyse non réalisée : les données sont encore insuffisantes (voir les critères).";

        return redirect()->route('admin.incidents.ia')->with('success', $message);
    }
}
