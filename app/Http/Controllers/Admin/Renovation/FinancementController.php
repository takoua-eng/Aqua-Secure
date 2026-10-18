<?php

namespace App\Http\Controllers\Admin\Renovation;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinancementRequest;
use App\Models\Financement;
use App\Models\ProjetRenovation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

// Binôme 3 — CRUD des financements
class FinancementController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Financement::class);

        $filtre = Financement::query()
            ->when($request->source, fn ($q, $source) => $q->where('source', $source))
            ->when($request->projet, fn ($q, $projet) => $q->where('projet_id', $projet))
            ->when($request->q, fn ($q, $texte) => $q->whereHas('projet', fn ($p) => $p->where('titre', 'like', "%{$texte}%")))
            ->when(is_numeric($request->min), fn ($q) => $q->where('montant', '>=', (float) $request->min))
            ->when(is_numeric($request->max), fn ($q) => $q->where('montant', '<=', (float) $request->max))
            ->when($request->origine === 'scenario', fn ($q) => $q->whereNotNull('simulation_id'))
            ->when($request->origine === 'manuel', fn ($q) => $q->whereNull('simulation_id'));

        $financements = (clone $filtre)
            ->with(['projet.reseau', 'simulation:id,nom'])
            ->latest()->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Totaux sur TOUTE la sélection filtrée (pas seulement la page affichée)
        $totaux = (clone $filtre)->select('source', DB::raw('SUM(montant) as total'), DB::raw('COUNT(*) as nombre'))
            ->groupBy('source')->get()->keyBy('source');

        return view('admin.renovation.financements.index', [
            'financements' => $financements,
            'totaux'       => $totaux,
            'total'        => round((float) $totaux->sum('total'), 2),
            'nombre'       => (int) $totaux->sum('nombre'),
            'projets'      => ProjetRenovation::orderBy('titre')->pluck('titre', 'id')->all(),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Financement::class);

        return view('admin.renovation.financements.create', [
            'financement' => new Financement(['projet_id' => $request->integer('projet') ?: null]),
            'projets'     => $this->optionsProjets(),
            'restes'      => $this->restes(),
        ]);
    }

    public function store(FinancementRequest $request)
    {
        Gate::authorize('create', Financement::class);

        $financement = Financement::create($request->safe()->only(['projet_id', 'source', 'montant']));

        return redirect()->route('admin.projets.show', $financement->projet_id)
            ->with('success', 'Financement de ' . number_format((float) $financement->montant, 2, ',', ' ') . ' DT enregistré.');
    }

    public function show(Financement $financement)
    {
        Gate::authorize('view', $financement);

        $financement->load(['projet.reseau.zone', 'simulation.decideur']);

        return view('admin.renovation.financements.show', compact('financement'));
    }

    public function edit(Financement $financement)
    {
        Gate::authorize('update', $financement);

        return view('admin.renovation.financements.edit', [
            'financement' => $financement,
            'projets'     => $this->optionsProjets($financement->projet_id),
            'restes'      => $this->restes(),
        ]);
    }

    public function update(FinancementRequest $request, Financement $financement)
    {
        Gate::authorize('update', $financement);

        $financement->update($request->safe()->only(['projet_id', 'source', 'montant']));

        return redirect()->route('admin.financements.show', $financement)->with('success', 'Financement mis à jour.');
    }

    public function destroy(Financement $financement)
    {
        $reponse = Gate::inspect('delete', $financement);
        if ($reponse->denied()) {
            return back()->withErrors(['financement' => $reponse->message()]);
        }

        $projetId = $financement->projet_id;
        $financement->delete();

        return redirect()->route('admin.projets.show', $projetId)->with('success', 'Financement supprimé.');
    }

    // Projets qui peuvent recevoir un financement (terminés : administrateur seulement, ou projet déjà choisi)
    private function optionsProjets(?int $actuel = null): array
    {
        return ProjetRenovation::with('reseau')
            ->when(! auth()->user()->isAdmin(), fn ($q) => $q->where(fn ($s) => $s->where('statut', '!=', 'termine')
                ->when($actuel, fn ($s2) => $s2->orWhere('id', $actuel))))
            ->orderBy('titre')->get()
            ->mapWithKeys(fn (ProjetRenovation $p) => [$p->id => "{$p->titre} — {$p->reseau?->code} ({$p->libelleStatut()})"])
            ->all();
    }

    // Coût déclaré et déjà financé de chaque projet (aide à la saisie dans le formulaire)
    private function restes(): array
    {
        return ProjetRenovation::withSum('financements', 'montant')->get()
            ->mapWithKeys(fn (ProjetRenovation $p) => [$p->id => [
                'cout'    => $p->cout_estime !== null ? (float) $p->cout_estime : null,
                'finance' => $p->totalFinance(),
            ]])->all();
    }
}
