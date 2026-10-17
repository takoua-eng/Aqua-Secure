<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\InterventionRequest;
use App\Http\Requests\StatutInterventionRequest;
use App\Http\Resources\InterventionResource;
use App\Models\HistoriqueIntervention;
use App\Models\Incident;
use App\Models\Intervention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

// Exploitation (binôme 2) : interventions effectuées suite à un incident
class InterventionController extends Controller
{
    public function index(Request $request)
    {
        // Filtres : les valeurs inconnues (ou envoyées sous forme de tableau) sont ignorées
        $texte = fn (string $cle) => is_string($valeur = $request->query($cle)) ? trim($valeur) : '';
        $parmi = fn (string $cle, array $liste) => array_key_exists($texte($cle), $liste) ? $texte($cle) : null;
        $date = fn (string $cle) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $texte($cle)) && strtotime($texte($cle)) ? $texte($cle) : null;
        $filtres = [
            'statut'   => $parmi('statut', Intervention::STATUTS),
            'type'     => $parmi('type', Incident::TYPES),
            'incident' => ctype_digit(ltrim($texte('incident'), '#')) ? (int) ltrim($texte('incident'), '#') : null,
            'du'       => $date('du'),
            'au'       => $date('au'),
            'retard'   => $request->boolean('retard'),
        ];

        $interventions = Intervention::query()
            ->with('incident.reseau.zone')
            ->when($filtres['statut'], fn ($q, $statut) => $q->where('statut', $statut))
            ->when($filtres['type'], fn ($q, $type) => $q->whereHas('incident', fn ($i) => $i->where('type', $type)))
            ->when($filtres['incident'], fn ($q, $incident) => $q->where('incident_id', $incident))
            ->when($filtres['du'], fn ($q, $du) => $q->whereDate('date_intervention', '>=', $du))
            ->when($filtres['au'], fn ($q, $au) => $q->whereDate('date_intervention', '<=', $au))
            ->when($filtres['retard'], fn ($q) => $q->enRetard())
            ->orderByDesc('date_intervention')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        if ($request->wantsJson()) {
            return InterventionResource::collection($interventions);
        }

        return view('admin.interventions.index', [
            'interventions' => $interventions,
            'filtres'       => $filtres,
            'indicateurs'   => [
                'planifiee' => Intervention::where('statut', 'planifiee')->count(),
                'en_cours'  => Intervention::where('statut', 'en_cours')->count(),
                'terminee'  => Intervention::where('statut', 'terminee')->count(),
                'retard'    => Intervention::enRetard()->count(),
            ],
        ]);
    }

    // Une intervention est toujours créée depuis un incident (règle T1)
    public function create(Incident $incident)
    {
        Gate::authorize('create', Intervention::class);

        return view('admin.interventions.create', [
            'incident'     => $incident->load(['reseau.zone', 'interventions']),
            'intervention' => new Intervention(['statut' => 'planifiee', 'date_intervention' => today()]),
        ]);
    }

    public function store(InterventionRequest $request, Incident $incident)
    {
        Gate::authorize('create', Intervention::class);

        $etaitResolu = $incident->estResolu();
        // L'intervention et sa première ligne d'historique sont écrites ensemble, ou pas du tout
        $intervention = DB::transaction(fn () => $incident->interventions()->create($request->validated()));

        $message = "Intervention « {$intervention->libelleStatut()} » enregistrée pour l'incident n° {$incident->id}.";
        if ($etaitResolu) {
            $message .= " L'incident était résolu : il repasse « En traitement ».";
        }

        return redirect()->route('admin.interventions.show', $intervention)->with('success', $message);
    }

    // Suivi : informations, changement de statut, historique
    public function show(Request $request, Intervention $intervention)
    {
        $intervention->load(['incident.reseau.zone', 'historiques.user']);

        if ($request->wantsJson()) {
            return new InterventionResource($intervention);
        }

        return view('admin.interventions.show', ['intervention' => $intervention]);
    }

    public function edit(Intervention $intervention)
    {
        Gate::authorize('update', $intervention);

        // Règle T7
        if (! $intervention->estModifiable()) {
            return redirect()->route('admin.interventions.show', $intervention)
                ->withErrors(['intervention' => 'Une intervention terminée ne peut plus être modifiée.']);
        }

        return view('admin.interventions.edit', [
            'intervention' => $intervention->load('incident.reseau.zone'),
            'incident'     => $intervention->incident,
        ]);
    }

    // Seule la date se modifie ici ; le statut change par l'action dédiée (règle T5)
    public function update(InterventionRequest $request, Intervention $intervention)
    {
        Gate::authorize('update', $intervention);

        if (! $intervention->estModifiable()) {
            return redirect()->route('admin.interventions.show', $intervention)
                ->withErrors(['intervention' => 'Une intervention terminée ne peut plus être modifiée.']);
        }

        $intervention->update($request->validated());

        return redirect()->route('admin.interventions.show', $intervention)->with('success', 'Intervention modifiée.');
    }

    public function changerStatut(StatutInterventionRequest $request, Intervention $intervention)
    {
        Gate::authorize('changerStatut', $intervention);

        $intervention->passerA($request->validated('statut'));

        $message = "Intervention passée à « {$intervention->libelleStatut()} ».";
        if ($intervention->statut === 'terminee' && $intervention->incident->estResolu()) {
            $message .= " L'incident n° {$intervention->incident_id} est maintenant résolu.";
        }

        return redirect()->route('admin.interventions.show', $intervention)->with('success', $message);
    }

    public function destroy(Intervention $intervention)
    {
        Gate::authorize('delete', $intervention);

        // Règle T8 : en cours ou terminée, l'intervention est conservée
        if (! $intervention->peutEtreSupprimee()) {
            return back()->withErrors([
                'intervention' => "Seule une intervention planifiée peut être supprimée (celle-ci est « {$intervention->libelleStatut()} »).",
            ]);
        }

        $incident = $intervention->incident;
        $intervention->delete();

        return redirect()->route('admin.incidents.show', $incident)->with('success', 'Intervention planifiée annulée et supprimée.');
    }

    // Historique de tous les changements de statut
    public function historique(Request $request)
    {
        $statut = is_string($request->query('statut')) && array_key_exists($request->query('statut'), Intervention::STATUTS)
            ? $request->query('statut') : null;

        $historique = HistoriqueIntervention::query()
            ->with(['intervention.incident.reseau', 'user'])
            ->when($statut, fn ($q) => $q->where('nouveau_statut', $statut))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.interventions.historique', ['historique' => $historique, 'statut' => $statut]);
    }
}
