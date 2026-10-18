<?php

namespace App\Http\Controllers\Admin\Renovation;

use App\Http\Controllers\Controller;
use App\Models\ProjetRenovation;
use App\Models\ReseauEau;
use App\Services\PlanRenovation;
use App\Services\Renovation\Apprentissage\JeuEntrainementSynthetique;
use App\Services\Renovation\ModeleCoutAppris;
use App\Services\Renovation\ModeleSourceAppris;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

// Binôme 3 — page « Modèles IA » : modèles entraînés sur les données (coût, source de financement), performances, essai, réentraînement
class ModelesIAController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', ProjetRenovation::class);

        $gouvernorats = array_keys(config('communes'));
        $essai = $request->validate([
            'type'        => ['nullable', Rule::in(array_keys(ReseauEau::TYPES))],
            'materiau'    => ['nullable', Rule::in(array_keys(ReseauEau::MATERIAUX))],
            'age'         => ['nullable', 'integer', 'min:0', 'max:120'],
            'etat'        => ['nullable', Rule::in(array_keys(ReseauEau::ETATS))],
            'population'  => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'gouvernorat' => ['nullable', Rule::in($gouvernorats)],
        ]);

        return view('admin.renovation.modeles-ia', [
            'cout'         => ModeleCoutAppris::charger(),
            'source'       => ModeleSourceAppris::charger(),
            'regles'       => JeuEntrainementSynthetique::regles(),
            'gouvernorats' => $gouvernorats,
            'essai'        => $request->filled('type') ? $this->essayer($essai) : null,
        ]);
    }

    public function entrainer()
    {
        Gate::authorize('viewAny', ProjetRenovation::class);
        set_time_limit(120);

        $cout = ModeleCoutAppris::entrainer();
        $source = ModeleSourceAppris::entrainer();

        return redirect()->route('admin.renovation.modeles')->with('success', sprintf(
            'Modèles réentraînés. Coût : %s (%d exemples). Source : %s (%d exemples).',
            $cout['actif'] ? 'actif, ' . number_format($cout['retenu']['mape'], 1, ',', ' ') . ' % d\'erreur relative' : 'inactif',
            $cout['n'],
            $source['actif'] ? 'actif, ' . number_format($source['exactitude'], 1, ',', ' ') . ' % de bonnes réponses' : 'inactif',
            $source['n'],
        ));
    }

    // Essai en direct : prédiction pour des caractéristiques saisies (aucune donnée enregistrée)
    private function essayer(array $e): array
    {
        $brut = [
            'type'       => $e['type'],
            'materiau'   => $e['materiau'] ?? 'pvc',
            'age'        => (int) ($e['age'] ?? 30),
            'etat'       => $e['etat'] ?? 'degrade',
            'population' => (int) ($e['population'] ?? 20000),
        ];
        $bareme = (float) PlanRenovation::cout(new ReseauEau(['type' => $brut['type'], 'materiau' => $brut['materiau']]));
        $prediction = ModeleCoutAppris::predire($brut);
        $gouvernorat = $e['gouvernorat'] ?? 'Tunis';
        $commune = array_key_first(config('communes')[$gouvernorat] ?? ['Tunis' => null]);

        return [
            'brut'       => $brut,
            'gouvernorat' => $gouvernorat,
            'bareme'     => $bareme,
            'prediction' => $prediction,
            'source'     => ModeleSourceAppris::recommanderDepuis(
                ModeleSourceAppris::variablesDepuis($brut['type'], $brut['etat'], $prediction['montant'] ?? $bareme, $commune)),
        ];
    }
}
