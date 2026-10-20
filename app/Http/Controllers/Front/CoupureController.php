<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\CommentaireCoupure;
use App\Models\CoupureProgrammee;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoupureController extends Controller
{
    /*
     * Coupures en cours, à venir et récentes, avec les commentaires des citoyens (visibles par tous).
     * Un citoyen qui a choisi sa zone voit d'abord celles de sa zone.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $toutes = $request->boolean('toutes') || ! $user?->zone_id;

        $coupures = CoupureProgrammee::with([
                'reseau.zone',
                // Commentaires les plus aimés d'abord, puis les plus récents
                'commentaires' => fn ($q) => $q->with('auteur')->withCount('likes')->orderByDesc('likes_count')->latest(),
            ])
            ->visibles()
            ->when(! $toutes, fn ($q) => $q->dansZone($user->zone_id))
            ->orderByRaw('fin < ? asc', [now()])   // en cours et à venir d'abord, terminées ensuite
            ->orderBy('debut')
            ->get();

        // Commentaires déjà aimés par le citoyen connecté (pour afficher le cœur plein)
        $aimes = $user
            ? $user->belongsToMany(CommentaireCoupure::class, 'likes_commentaire', 'user_id', 'commentaire_coupure_id')
                ->pluck('commentaires_coupure.id')->all()
            : [];

        return view('front.coupures', [
            'coupures' => $coupures,
            'aimes'    => $aimes,
            'toutes'   => $toutes,
            'zone'     => $user?->zone,
        ]);
    }

    // Un citoyen écrit un commentaire sur une coupure
    public function commenter(Request $request, CoupureProgrammee $coupure)
    {
        if (! $coupure->accepteCommentaires()) {
            return $this->retour($coupure)->withErrors(['contenu' => 'Cette coupure ne peut plus être commentée.']);
        }

        $donnees = $request->validateWithBag('commentaire' . $coupure->id, [
            'contenu' => ['required', 'string', 'min:' . CommentaireCoupure::LONGUEUR_MIN, 'max:' . CommentaireCoupure::LONGUEUR_MAX],
        ], [
            'contenu.required' => 'Écrivez votre commentaire.',
            'contenu.min'      => 'Le commentaire doit contenir au moins ' . CommentaireCoupure::LONGUEUR_MIN . ' caractères.',
            'contenu.max'      => 'Le commentaire ne peut pas dépasser ' . CommentaireCoupure::LONGUEUR_MAX . ' caractères.',
        ]);

        $coupure->commentaires()->create(['user_id' => $request->user()->id, 'contenu' => trim($donnees['contenu'])]);

        return $this->retour($coupure)->with('succes_commentaire', 'Merci, votre commentaire est publié.');
    }

    // J'aime / je n'aime plus (on ne peut pas aimer son propre commentaire)
    public function aimer(Request $request, CommentaireCoupure $commentaire)
    {
        if ($commentaire->user_id === $request->user()->id) {
            return $this->retour($commentaire->coupure)->withErrors(['like' => 'Vous ne pouvez pas aimer votre propre commentaire.']);
        }

        $commentaire->likes()->toggle($request->user()->id);

        return $this->retour($commentaire->coupure);
    }

    // "Ma zone" : le citoyen choisit la zone où il habite pour être prévenu des coupures
    public function choisirZone(Request $request)
    {
        $donnees = $request->validate([
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')],
        ], [
            'zone_id.integer' => "La zone choisie n'est pas valide.",
            'zone_id.exists'  => "La zone choisie n'existe pas.",
        ]);

        $request->user()->forceFill(['zone_id' => $donnees['zone_id'] ?? null])->save();

        return back()->with('succes_zone', $donnees['zone_id'] ?? null
            ? 'Zone enregistrée : vous serez prévenu(e) par email des coupures programmées dans votre zone.'
            : "Zone retirée : vous ne recevrez plus d'avis de coupure.");
    }

    // Zones groupées par commune : ['Tunis' => [id => 'Centre-ville'], ...]
    public static function optionsZones(): array
    {
        return Zone::orderBy('commune')->orderBy('nom')->get()
            ->groupBy('commune')
            ->map(fn ($zones) => $zones->mapWithKeys(fn ($z) => [$z->id => $z->nom])->all())
            ->all();
    }

    // Retour sur la page, à la hauteur de la coupure concernée (en gardant le filtre "toutes les zones")
    private function retour(CoupureProgrammee $coupure)
    {
        $parametres = request()->boolean('toutes') ? ['toutes' => 1] : [];

        return redirect()->to(route('coupures.index', $parametres) . '#coupure-' . $coupure->id);
    }
}
