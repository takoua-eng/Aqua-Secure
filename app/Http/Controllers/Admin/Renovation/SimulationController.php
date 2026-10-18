<?php

namespace App\Http\Controllers\Admin\Renovation;

use App\Http\Controllers\Controller;
use App\Models\Financement;
use App\Models\ProjetRenovation;
use App\Models\SimulationFinancement;
use App\Services\Renovation\AnalyseFinancementIA;
use App\Services\Renovation\CandidatsWhatIf;
use App\Services\Renovation\MoteurWhatIf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/*
 * Binôme 3 — scénarios What If? enregistrés : historique, comparaison, cycle de validation et application.
 * L'application d'un scénario est la SEULE opération qui crée des financements réels :
 * administrateur uniquement, scénario approuvé, données revérifiées, transaction, ajout uniquement.
 */
class SimulationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', SimulationFinancement::class);
        $moi = $request->user();

        $simulations = SimulationFinancement::with(['auteur:id,nom', 'decideur:id,nom'])
            // Brouillons privés : visibles par leur auteur et par les administrateurs
            ->when(! $moi->isAdmin(), fn ($q) => $q->where(fn ($s) => $s->where('user_id', $moi->id)->orWhere('statut', '!=', 'brouillon')))
            ->when($request->statut, fn ($q, $statut) => $q->where('statut', $statut))
            ->when($request->boolean('miennes'), fn ($q) => $q->where('user_id', $moi->id))
            ->latest()->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('admin.renovation.simulations.index', [
            'simulations' => $simulations,
            'enAttente'   => SimulationFinancement::where('statut', 'soumise')->count(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', SimulationFinancement::class);

        $request->validate(['nom' => ['required', 'string', 'min:3', 'max:120']], [
            'nom.required' => 'Donnez un nom à la simulation.',
            'nom.min'      => 'Le nom doit contenir au moins 3 caractères.',
            'nom.max'      => 'Le nom ne peut pas dépasser 120 caractères.',
        ]);
        $p = WhatIfController::parametres($request);

        // Recalcul serveur : on enregistre ce que le moteur calcule, pas ce que le navigateur affiche
        $candidats = CandidatsWhatIf::charger();
        $comparaison = MoteurWhatIf::comparer($candidats, $p['budget'], $p);

        $simulation = SimulationFinancement::create([
            'user_id'    => $request->user()->id,
            'nom'        => trim($request->nom),
            'budget'     => $p['budget'],
            'strategie'  => $p['strategie'],
            'parametres' => $comparaison['options'],
            'resultats'  => $comparaison,
            'empreinte'  => CandidatsWhatIf::empreinte($candidats),
            'statut'     => $request->boolean('soumettre') ? 'soumise' : 'brouillon',
            'soumise_le' => $request->boolean('soumettre') ? now() : null,
        ]);

        return redirect()->route('admin.simulations.show', $simulation)->with('success', $simulation->statut === 'soumise'
            ? 'Simulation enregistrée et soumise pour validation. Aucune donnée réelle n\'a été modifiée.'
            : 'Simulation enregistrée (brouillon). Aucune donnée réelle n\'a été modifiée.');
    }

    public function show(SimulationFinancement $simulation)
    {
        Gate::authorize('view', $simulation);
        $simulation->load(['auteur:id,nom', 'decideur:id,nom', 'financements']);

        return view('admin.renovation.simulations.show', [
            'simulation'     => $simulation,
            'ecarts'         => in_array($simulation->statut, ['brouillon', 'soumise', 'approuvee'], true) ? $this->ecarts($simulation) : [],
            'sourceSuggeree' => $simulation->statut === 'soumise' ? $this->sourceSuggeree($simulation) : null,
        ]);
    }

    /**
     * Modèle IA entraîné : source la plus probable pour l'ensemble du scénario
     * (probabilités de chaque projet pondérées par le montant proposé). Simple suggestion pour l'administrateur.
     */
    private function sourceSuggeree(SimulationFinancement $simulation): ?array
    {
        $lignes = collect($simulation->scenarioRetenu()['lignes'] ?? [])->where('propose', '>', 0);
        $projets = ProjetRenovation::with('reseau.zone')->whereIn('id', $lignes->pluck('id'))->get()->keyBy('id');
        $cumul = [];

        foreach ($lignes as $l) {
            $r = $projets->has($l['id']) ? \App\Services\Renovation\ModeleSourceAppris::recommander($projets[$l['id']]) : null;
            foreach ($r['probabilites'] ?? [] as $source => $proba) {
                $cumul[$source] = ($cumul[$source] ?? 0) + $proba * $l['propose'];
            }
        }
        if (! $cumul) {
            return null;
        }

        arsort($cumul);
        $total = array_sum($cumul);

        return ['source' => array_key_first($cumul), 'part' => round(100 * reset($cumul) / $total)];
    }

    // Analyse IA du scénario enregistré (stockée avec la simulation, clairement marquée comme interprétation)
    public function analyser(SimulationFinancement $simulation): JsonResponse
    {
        Gate::authorize('update', $simulation);
        set_time_limit(120);

        $resultat = AnalyseFinancementIA::analyserScenarios($simulation->resultats);
        $simulation->update(['analyse_ia' => $resultat]);

        return response()->json($resultat);
    }

    public function soumettre(SimulationFinancement $simulation)
    {
        Gate::authorize('soumettre', $simulation);

        $simulation->update(['statut' => 'soumise', 'soumise_le' => now()]);

        return back()->with('success', 'Scénario soumis : un administrateur doit maintenant l\'approuver ou le rejeter.');
    }

    public function decider(Request $request, SimulationFinancement $simulation)
    {
        Gate::authorize('decider', $simulation);

        $donnees = $request->validate([
            'decision'    => ['required', Rule::in(['approuver', 'rejeter'])],
            'source'      => ['required_if:decision,approuver', 'nullable', Rule::in(array_keys(Financement::SOURCES))],
            'commentaire' => ['required_if:decision,rejeter', 'nullable', 'string', 'max:1000'],
        ], [
            'source.required_if'      => 'Choisissez la source réelle des fonds avant d\'approuver.',
            'commentaire.required_if' => 'Expliquez le motif du rejet.',
        ]);

        $approuve = $donnees['decision'] === 'approuver';
        $simulation->update([
            'statut'               => $approuve ? 'approuvee' : 'rejetee',
            'source_financement'   => $approuve ? $donnees['source'] : null,
            'commentaire_decision' => $donnees['commentaire'] ?? null,
            'decide_par'           => $request->user()->id,
            'decide_le'            => now(),
        ]);

        return back()->with('success', $approuve
            ? 'Scénario approuvé. Il peut maintenant être appliqué : les financements seront créés après une dernière vérification.'
            : 'Scénario rejeté.');
    }

    public function appliquer(Request $request, SimulationFinancement $simulation)
    {
        Gate::authorize('appliquer', $simulation);
        $request->validate(['confirmation' => ['accepted']], ['confirmation.accepted' => 'Cochez la case de confirmation.']);

        $crees = DB::transaction(function () use ($simulation) {
            // Verrou : deux administrateurs ne peuvent pas appliquer le même scénario en même temps
            $sim = SimulationFinancement::whereKey($simulation->id)->lockForUpdate()->firstOrFail();
            if ($sim->statut !== 'approuvee') {
                throw ValidationException::withMessages(['simulation' => 'Ce scénario n\'est plus approuvé : rien n\'a été appliqué.']);
            }

            $ecarts = $this->ecarts($sim);
            if ($ecarts) {
                throw ValidationException::withMessages(['simulation' => 'Les données réelles ont changé depuis la simulation : '
                    . implode(' ', array_slice($ecarts, 0, 3)) . ' Relancez une simulation.']);
            }

            $lignes = array_filter($sim->scenarioRetenu()['lignes'] ?? [], fn ($l) => $l['propose'] > 0);
            if (array_sum(array_column($lignes, 'propose')) > (float) $sim->budget + 0.005) {
                throw ValidationException::withMessages(['simulation' => 'Le scénario dépasse son budget : application refusée.']);
            }

            $nombre = 0;
            foreach ($lignes as $ligne) {
                // Ajout uniquement : les financements existants ne sont ni modifiés ni supprimés
                Financement::create([
                    'projet_id'     => $ligne['id'],
                    'source'        => $sim->source_financement,
                    'montant'       => round(min($ligne['propose'], Financement::MONTANT_MAX), 2),
                    'simulation_id' => $sim->id,
                ]);
                $nombre++;
            }

            $sim->update(['statut' => 'appliquee', 'appliquee_le' => now()]);

            return $nombre;
        });

        return back()->with('success', "Scénario appliqué : {$crees} financement(s) réel(s) créé(s), rattaché(s) à cette décision.");
    }

    public function destroy(SimulationFinancement $simulation)
    {
        $reponse = Gate::inspect('delete', $simulation);
        if ($reponse->denied()) {
            return back()->withErrors(['simulation' => $reponse->message()]);
        }

        $simulation->delete();

        return redirect()->route('admin.simulations.index')->with('success', 'Simulation supprimée.');
    }

    // Comparaison de 2 ou 3 simulations enregistrées (stratégie retenue de chacune)
    public function comparer(Request $request)
    {
        $request->validate(['ids' => ['required', 'array', 'min:2', 'max:3'], 'ids.*' => ['integer']], [
            'ids.required' => 'Cochez 2 ou 3 simulations à comparer.',
            'ids.min'      => 'Cochez au moins 2 simulations.',
            'ids.max'      => 'Cochez au plus 3 simulations.',
        ]);

        $simulations = SimulationFinancement::with('auteur:id,nom')->whereIn('id', $request->ids)->orderBy('id')->get();
        abort_if($simulations->count() < 2, 404);
        $simulations->each(fn ($s) => Gate::authorize('view', $s));

        return view('admin.renovation.simulations.comparer', compact('simulations'));
    }

    // Rapport de décision PDF : hypothèses, résultats calculés, interprétation IA, décision
    public function rapport(SimulationFinancement $simulation)
    {
        Gate::authorize('view', $simulation);
        $simulation->load(['auteur:id,nom', 'decideur:id,nom']);

        return Pdf::loadView('admin.renovation.simulations.pdf', ['simulation' => $simulation, 'genereLe' => now()])
            ->setPaper('a4')
            ->download('rapport-decision-' . str($simulation->nom)->slug() . '.pdf');
    }

    /**
     * Différences entre les données utilisées par le scénario retenu et les données réelles actuelles.
     *
     * @return list<string>
     */
    private function ecarts(SimulationFinancement $simulation): array
    {
        $lignes = array_filter($simulation->scenarioRetenu()['lignes'] ?? [], fn ($l) => $l['propose'] > 0);
        if (! $lignes) {
            return [];
        }

        $actuels = collect(CandidatsWhatIf::depuisProjets(
            CandidatsWhatIf::requete()->whereIn('id', array_column($lignes, 'id'))->get()
        ))->keyBy('id');

        $ecarts = [];
        foreach ($lignes as $l) {
            $a = $actuels[$l['id']] ?? null;
            if (! $a) {
                $ecarts[] = "Le projet « {$l['titre']} » n'existe plus.";
            } elseif ($a['statut'] === 'termine') {
                $ecarts[] = "Le projet « {$l['titre']} » est désormais terminé.";
            } elseif (abs($a['finance'] - $l['finance']) > 0.005) {
                $ecarts[] = "Les financements de « {$l['titre']} » ont changé.";
            } elseif ($a['cout'] === null || abs($a['cout'] - $l['cout']) > 0.005) {
                $ecarts[] = "Le coût de « {$l['titre']} » a changé.";
            }
        }

        return $ecarts;
    }
}
