<?php

namespace App\Http\Controllers\Admin\Renovation;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjetRenovationRequest;
use App\Models\Financement;
use App\Models\ProjetRenovation;
use App\Models\ReseauEau;
use App\Models\Zone;
use App\Services\Renovation\CandidatsWhatIf;
use App\Services\Renovation\DetectionRisquesFinanciers;
use App\Services\Renovation\ScorePriorite;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// Binôme 3 — CRUD des projets de rénovation
class ProjetRenovationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', ProjetRenovation::class);

        $projets = CandidatsWhatIf::requete()
            ->withCount('financements')
            ->when($request->q, fn ($query, $q) => $query->where(fn ($sous) => $sous
                ->where('titre', 'like', "%{$q}%")
                ->orWhereHas('reseau', fn ($r) => $r->where('code', 'like', "%{$q}%"))))
            ->when($request->statut, fn ($query, $statut) => $query->where('statut', $statut))
            ->when($request->reseau, fn ($query, $reseau) => $query->where('reseau_id', $reseau))
            ->when($request->zone, fn ($query, $zone) => $query->whereHas('reseau', fn ($r) => $r->where('zone_id', $zone)))
            ->when($request->financement === 'sans', fn ($query) => $query->doesntHave('financements'))
            ->when($request->financement === 'avec', fn ($query) => $query->has('financements'))
            ->when($request->filled('publie'), fn ($query) => $query->where('publie', $request->boolean('publie')))
            ->reorder()
            ->when($request->tri === 'titre', fn ($q) => $q->orderBy('titre'))
            ->when($request->tri === 'finance', fn ($q) => $q->orderByDesc('financements_sum_montant'))
            ->when(! in_array($request->tri, ['titre', 'finance'], true), fn ($q) => $q->latest()->latest('id'))
            ->paginate(15)
            ->withQueryString();

        // Score de priorité de chaque projet affiché (15 au plus : pas de surcharge)
        $projets->getCollection()->each(fn (ProjetRenovation $p) => $p->setAttribute('priorite', ScorePriorite::pourProjet($p)));

        $stats = [
            'total'            => ProjetRenovation::count(),
            'en_cours'         => ProjetRenovation::where('statut', 'en_cours')->count(),
            'finance'          => (float) Financement::sum('montant'),
            'sans_financement' => ProjetRenovation::where('statut', '!=', 'termine')->doesntHave('financements')->count(),
        ];

        return view('admin.renovation.projets.index', [
            'projets' => $projets,
            'stats'   => $stats,
            'reseaux' => $this->optionsReseaux(),
            'zones'   => Zone::orderBy('commune')->orderBy('nom')->get()->mapWithKeys(fn (Zone $z) => [$z->id => $z->libelle()])->all(),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', ProjetRenovation::class);

        return view('admin.renovation.projets.create', [
            'projet'  => new ProjetRenovation(['statut' => 'planifie', 'reseau_id' => $request->integer('reseau') ?: null]),
            'reseaux' => $this->optionsReseaux(),
            'estimations' => $this->estimations(),
        ]);
    }

    public function store(ProjetRenovationRequest $request)
    {
        Gate::authorize('create', ProjetRenovation::class);

        $projet = ProjetRenovation::create($request->validated());

        return redirect()->route('admin.projets.show', $projet)
            ->with('success', "Projet « {$projet->titre} » créé. Vous pouvez maintenant lui associer des financements.");
    }

    public function show(ProjetRenovation $projet)
    {
        Gate::authorize('view', $projet);

        $projet = CandidatsWhatIf::requete()->withCount('financements')->findOrFail($projet->id);
        $projet->load(['financements' => fn ($q) => $q->latest()->latest('id')->with('simulation:id,nom')]);

        $priorite = ScorePriorite::pourProjet($projet);
        $parSource = $projet->financements->groupBy('source')
            ->map(fn ($lignes, $source) => [
                'source'  => $source,
                'libelle' => Financement::libelleSourceDe($source),
                'montant' => round((float) $lignes->sum('montant'), 2),
                'couleur' => Financement::couleurSourceDe($source),
            ])->sortByDesc('montant')->values();

        return view('admin.renovation.projets.show', [
            'projet'    => $projet,
            'priorite'  => $priorite,
            'cout'      => $priorite['cout'],
            'finance'   => $projet->totalFinance(),
            'parSource' => $parSource,
            'alertes'   => DetectionRisquesFinanciers::pourProjet($projet->id),
            // Participation citoyenne (si la migration correspondante a été exécutée)
            'participation' => $participation = \App\Services\Renovation\ObservatoireCitoyen::participationDisponible(),
            'soutiens'  => $participation ? $projet->soutiens()->count() : 0,
            'questions' => $participation ? $projet->questions()->with('repondant:id,nom')
                ->orderByRaw('reponse IS NOT NULL')->latest()->get() : collect(),
        ]);
    }

    // Panneau de chantier imprimable (A4) avec un QR code vers la fiche publique du projet
    public function panneau(ProjetRenovation $projet)
    {
        Gate::authorize('view', $projet);

        $projet->load('reseau.zone')->loadSum('financements', 'montant');
        $sources = $projet->financements()->selectRaw('source, SUM(montant) as total')->groupBy('source')
            ->orderByDesc('total')->pluck('total', 'source');

        return view('admin.renovation.projets.panneau', [
            'projet'  => $projet,
            'finance' => $projet->totalFinance(),
            'sources' => $sources,
            'url'     => route('renovation.show', $projet),
        ]);
    }

    public function edit(ProjetRenovation $projet)
    {
        Gate::authorize('update', $projet);

        return view('admin.renovation.projets.edit', [
            'projet'      => $projet,
            'reseaux'     => $this->optionsReseaux(),
            'estimations' => $this->estimations(),
        ]);
    }

    public function update(ProjetRenovationRequest $request, ProjetRenovation $projet)
    {
        Gate::authorize('update', $projet);

        $projet->update($request->validated());

        return redirect()->route('admin.projets.show', $projet)->with('success', 'Projet mis à jour.');
    }

    public function destroy(ProjetRenovation $projet)
    {
        $reponse = Gate::inspect('delete', $projet);
        if ($reponse->denied()) {
            return back()->withErrors(['projet' => $reponse->message()]);
        }

        try {
            $projet->delete();
        } catch (QueryException) {
            // Filet de sécurité : la clé étrangère protège aussi les financements
            return back()->withErrors(['projet' => 'Ce projet est lié à des financements : suppression impossible.']);
        }

        return redirect()->route('admin.projets.index')->with('success', "Projet « {$projet->titre} » supprimé.");
    }

    // Réseaux pour les listes déroulantes : id => "CAN-TUN-001 — Centre-ville (Dégradé)"
    private function optionsReseaux(): array
    {
        return ReseauEau::with('zone')->orderBy('code')->get()
            ->mapWithKeys(fn (ReseauEau $r) => [$r->id => "{$r->code} — {$r->zone?->nom} ({$r->libelleEtat()})"])
            ->all();
    }

    // Coût estimé de chaque réseau (affiché dans le formulaire tant qu'aucun devis n'est saisi)
    private function estimations(): array
    {
        return ReseauEau::all()->mapWithKeys(fn (ReseauEau $r) => [$r->id => \App\Services\PlanRenovation::cout($r)])->all();
    }
}
