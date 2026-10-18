<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Financement;
use App\Models\ProjetRenovation;
use App\Models\Zone;
use App\Services\Renovation\ObservatoireCitoyen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/*
 * Binôme 3 — « Observatoire citoyen des rénovations » : consultation et participation.
 * Seuls les projets PUBLIÉS par un gestionnaire sont visibles ; simulations, alertes et scores restent internes.
 * Les montants sont agrégés (aucune donnée personnelle) ; tous les chiffres viennent de la base.
 */
class RenovationPubliqueController extends Controller
{
    public function index(Request $request)
    {
        $langue = self::langue($request);
        // Les projets publiés sont peu nombreux : on les charge une fois pour la carte, les indicateurs et les graphiques
        $publics = ProjetRenovation::publics()
            ->with('reseau.zone')
            ->withSum('financements', 'montant')
            ->orderBy('titre')
            ->get();
        $ids = $publics->pluck('id');

        $sources = Financement::whereIn('projet_id', $ids)->selectRaw('source, SUM(montant) as total')
            ->groupBy('source')->pluck('total', 'source')->map(fn ($v) => (float) $v)->all();
        $total = round(array_sum($sources), 2);

        $communes = $publics->map(fn ($p) => $p->reseau?->zone?->commune)->filter()->unique()->sort()->values();
        $zonesConcernees = $publics->map(fn ($p) => $p->reseau?->zone)->filter()->unique('id');

        // Population de toutes les zones des communes concernées (pour l'investissement par habitant)
        $populationCommunes = Zone::whereIn('commune', $communes)->selectRaw('commune, SUM(population) as habitants')
            ->groupBy('commune')->pluck('habitants', 'commune')->map(fn ($v) => (int) $v);
        $moyenne = $populationCommunes->sum() > 0 ? $total / $populationCommunes->sum() : null;

        // « Mon quartier » : la commune choisie par le visiteur
        $commune = $communes->contains($request->commune) ? $request->commune : null;
        $quartier = null;
        if ($commune) {
            $ici = $publics->filter(fn ($p) => $p->reseau?->zone?->commune === $commune);
            $montant = round($ici->sum(fn ($p) => $p->totalFinance()), 2);
            $habitants = (int) ($populationCommunes[$commune] ?? 0);
            $quartier = [
                'commune'   => $commune,
                'projets'   => $ici->count(),
                'en_cours'  => $ici->where('statut', 'en_cours')->count(),
                'termines'  => $ici->where('statut', 'termine')->count(),
                'montant'   => $montant,
                'habitants' => $habitants,
                'par_habitant' => $habitants > 0 ? round($montant / $habitants, 2) : null,
            ];
        }

        $statut = in_array($request->statut, array_keys(ProjetRenovation::STATUTS), true) ? $request->statut : null;
        $recherche = trim((string) $request->q);
        $liste = $publics
            ->when($commune, fn ($c) => $c->filter(fn ($p) => $p->reseau?->zone?->commune === $commune))
            ->when($statut, fn ($c) => $c->where('statut', $statut))
            ->when($recherche !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p->titre . ' ' . $p->description), mb_strtolower($recherche))))
            ->sortBy(fn ($p) => [['en_cours' => 0, 'planifie' => 1, 'termine' => 2][$p->statut] ?? 3, $p->titre])
            ->values();

        return view('front.renovation.index', [
            'langue'      => $langue,
            'liste'       => $liste,
            'communes'    => $communes,
            'commune'     => $commune,
            'quartier'    => $quartier,
            'moyenne'     => $moyenne,
            'indicateurs' => [
                'projets'   => $publics->count(),
                'en_cours'  => $publics->where('statut', 'en_cours')->count(),
                'termines'  => $publics->where('statut', 'termine')->count(),
                'montant'   => $total,
                'habitants' => (int) $zonesConcernees->sum('population'),
                'communes'  => $communes->count(),
            ],
            'surCent'     => ObservatoireCitoyen::surCent($sources, $langue),
            'carte'       => $publics->map(fn ($p) => self::resume($p, $langue))->filter(fn ($p) => $p['lat'] !== null)->values(),
            'projetsJson' => $publics->mapWithKeys(fn ($p) => [$p->id => self::resume($p, $langue)]),
            // Où va l'argent : communes => projets (montants obtenus)
            'repartition' => $publics->filter(fn ($p) => $p->totalFinance() > 0)
                ->groupBy(fn ($p) => $p->reseau?->zone?->commune ?? 'Autre')
                ->map(fn ($projets, $nom) => ['name' => $nom, 'data' => $projets->map(fn ($p) => ['x' => $p->titre, 'y' => $p->totalFinance()])->values()])
                ->values(),
            'recents'     => $publics->sortByDesc('updated_at')->take(4)->values(),
        ]);
    }

    public function show(Request $request, ProjetRenovation $projet)
    {
        $langue = self::langue($request);
        abort_unless($projet->publie, 404);

        $projet->load('reseau.zone')->loadSum('financements', 'montant');
        $finance = $projet->totalFinance();
        $cout = $projet->cout_estime !== null && (float) $projet->cout_estime > 0 ? (float) $projet->cout_estime : null;
        $population = (int) $projet->reseau?->zone?->population;
        $sources = $projet->financements()->selectRaw('source, SUM(montant) as total')->groupBy('source')
            ->pluck('total', 'source')->map(fn ($v) => (float) $v)->all();

        $participation = ObservatoireCitoyen::participationDisponible();
        $moi = $request->user();

        return view('front.renovation.show', [
            'langue'       => $langue,
            'projet'       => $projet,
            'finance'      => $finance,
            'cout'         => $cout,
            'pct'          => $cout ? min(100, round(100 * $finance / $cout)) : null,
            'parHabitant'  => $population > 0 && $finance > 0 ? round($finance / $population, 2) : null,
            'surCent'      => ObservatoireCitoyen::surCent($sources, $langue),
            'pourquoi'     => ObservatoireCitoyen::pourquoi($projet->reseau, $langue),
            'position'     => ObservatoireCitoyen::position($projet),
            'voisins'      => ProjetRenovation::publics()->whereKeyNot($projet->id)
                ->whereHas('reseau.zone', fn ($q) => $q->where('commune', $projet->reseau?->zone?->commune))
                ->withSum('financements', 'montant')->limit(3)->get(),
            'participation' => $participation,
            'soutiens'     => $participation ? $projet->soutiens()->count() : 0,
            'dejaSoutenu'  => $participation && $moi ? $projet->soutiens()->where('user_id', $moi->id)->exists() : false,
            'questions'    => $participation ? $projet->questions()->publiees()->latest('repondu_le')->get() : collect(),
            'mesQuestions' => $participation && $moi ? $projet->questions()->where('user_id', $moi->id)->enAttente()->latest()->get() : collect(),
            'peutParticiper' => $moi?->isCitoyen() ?? false,
        ]);
    }

    // « Ce projet me concerne » : ajoute ou retire le soutien du citoyen connecté
    public function soutenir(Request $request, ProjetRenovation $projet)
    {
        abort_unless($projet->publie && ObservatoireCitoyen::participationDisponible(), 404);

        $existant = $projet->soutiens()->where('user_id', $request->user()->id)->first();
        $existant ? $existant->delete() : $projet->soutiens()->create(['user_id' => $request->user()->id]);

        $langue = self::langue($request);

        return back()->with('success', __($existant ? 'observatoire.flash_soutien_retire' : 'observatoire.flash_soutien_ajoute', [], $langue));
    }

    // Question d'un citoyen : publiée seulement quand un gestionnaire y a répondu
    public function poser(Request $request, ProjetRenovation $projet)
    {
        abort_unless($projet->publie && ObservatoireCitoyen::participationDisponible(), 404);

        $donnees = $request->validate([
            'question' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'question.required' => 'Écrivez votre question.',
            'question.min'      => 'Votre question doit contenir au moins 10 caractères.',
            'question.max'      => 'Votre question ne peut pas dépasser 500 caractères.',
        ]);

        // Pas plus de 3 questions sans réponse par citoyen et par projet (anti-spam)
        if ($projet->questions()->where('user_id', $request->user()->id)->enAttente()->count() >= 3) {
            return back()->withErrors(['question' => 'Vous avez déjà 3 questions en attente de réponse sur ce projet.'])->withInput();
        }

        $projet->questions()->create([
            'user_id'  => $request->user()->id,
            'question' => trim(strip_tags($donnees['question'])),
        ]);

        return back()->with('success', __('observatoire.flash_question', [], self::langue($request)));
    }

    /**
     * Langue de l'observatoire : « ?langue=ar » ou « ?langue=fr » la change et la mémorise un an (cookie),
     * sinon la langue mémorisée, sinon le français. Seules les pages du binôme 3 sont traduites.
     */
    private static function langue(Request $request): string
    {
        if ($request->has('langue')) {
            $langue = ObservatoireCitoyen::langue($request->query('langue'));
            Cookie::queue('observatoire_langue', $langue, 60 * 24 * 365);

            return $langue;
        }

        return ObservatoireCitoyen::langue($request->cookie('observatoire_langue'));
    }

    // Données minimales d'un projet pour la carte et la liste « Mes projets suivis » (navigateur)
    private static function resume(ProjetRenovation $p, string $langue = 'fr'): array
    {
        $position = ObservatoireCitoyen::position($p);
        [$libelle, , , $couleur] = ObservatoireCitoyen::statut($p->statut, $langue);
        $cout = $p->cout_estime !== null && (float) $p->cout_estime > 0 ? (float) $p->cout_estime : null;

        return [
            'id'       => $p->id,
            'titre'    => $p->titre,
            'statut'   => $libelle,
            'couleur'  => $couleur,
            'commune'  => $p->reseau?->zone?->commune,
            'quartier' => $p->reseau?->zone?->nom,
            'finance'  => $p->totalFinance(),
            'pct'      => $cout ? min(100, (int) round(100 * $p->totalFinance() / $cout)) : null,
            'lat'      => $position[0] ?? null,
            'lng'      => $position[1] ?? null,
            'precision' => $position[2] ?? null,
            'url'      => route('renovation.show', $p),
        ];
    }
}
