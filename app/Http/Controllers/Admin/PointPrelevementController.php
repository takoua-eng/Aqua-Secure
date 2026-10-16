<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PointPrelevementRequest;
use App\Models\PointPrelevement;
use App\Models\ReseauEau;
use App\Models\Zone;
use Illuminate\Http\Request;

class PointPrelevementController extends Controller
{
    public function index(Request $request)
    {
        $points = PointPrelevement::query()
            ->with('reseau.zone')
            ->withCount([
                'analyses',
                'analyses as analyses_conformes_count' => fn ($q) => $q->where('conforme', true),
            ])
            ->withMax('analyses as derniere_analyse', 'date_prelevement')
            ->when($request->q, function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('code', 'like', "%{$recherche}%")
                      ->orWhere('nom', 'like', "%{$recherche}%")
                      ->orWhere('adresse', 'like', "%{$recherche}%");
                });
            })
            ->when($request->reseau, fn ($query, $reseau) => $query->where('reseau_id', $reseau))
            ->when($request->type, fn ($query, $type) => $query->where('type_point', $type))
            ->when(in_array($request->actif, ['0', '1'], true), fn ($query) => $query->where('actif', $request->actif === '1'))
            ->when($request->boolean('retard'), fn ($query) => $query->enRetard())
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.prelevements.index', [
            'points'  => $points,
            'reseaux' => $this->optionsReseaux(),
        ]);
    }

    // Carte de tous les points, colorés selon leur dernière analyse
    public function carte()
    {
        $points = PointPrelevement::with(['reseau.zone', 'derniereAnalyse'])
            ->withCount([
                'analyses',
                'analyses as analyses_conformes_count' => fn ($q) => $q->where('conforme', true),
            ])
            ->orderBy('code')
            ->get();

        $carte = $points
            ->filter(fn (PointPrelevement $p) => $p->latitude !== null && $p->longitude !== null)
            ->map(fn (PointPrelevement $p) => $p->pourCarte() + [
                'taux' => $p->analyses_count ? round($p->analyses_conformes_count / $p->analyses_count * 100, 1) : null,
                'url'  => route('admin.prelevements.show', $p),
            ])
            ->values()
            ->all();

        return view('admin.prelevements.carte', [
            'carte'       => $carte,
            'sansPosition' => $points->filter(fn (PointPrelevement $p) => $p->latitude === null || $p->longitude === null),
        ]);
    }

    public function create()
    {
        return view('admin.prelevements.create', [
            'point'   => new PointPrelevement(['actif' => true]),
            'reseaux' => $this->optionsReseaux(),
            'positionsReseaux' => $this->positionsReseaux(),
        ]);
    }

    // PointPrelevementRequest a déjà tout vérifié : on n'arrive ici que si les données sont valides
    public function store(PointPrelevementRequest $request)
    {
        $point = PointPrelevement::create($request->validated());

        return redirect()->route('admin.prelevements.show', $point)->with('success', 'Point de prélèvement créé.');
    }

    // Fiche du point : informations, carte, taux de conformité, évolution des paramètres, historique
    public function show(PointPrelevement $point)
    {
        $point->load(['reseau.zone', 'analyses' => fn ($q) => $q->orderByDesc('date_prelevement')->orderByDesc('id')]);

        return view('admin.prelevements.show', [
            'point'     => $point,
            'taux'      => $point->tauxConformite(),
            'evolution' => $point->evolution(),
        ]);
    }

    public function edit(PointPrelevement $point)
    {
        return view('admin.prelevements.edit', [
            'point'   => $point,
            'reseaux' => $this->optionsReseaux(),
            'positionsReseaux' => $this->positionsReseaux(),
        ]);
    }

    public function update(PointPrelevementRequest $request, PointPrelevement $point)
    {
        $point->update($request->validated());

        return redirect()->route('admin.prelevements.show', $point)->with('success', 'Point de prélèvement modifié.');
    }

    public function destroy(PointPrelevement $point)
    {
        // Un point qui a des analyses ne peut pas être supprimé : on garde l'historique de la qualité de l'eau
        if ($point->analyses()->exists()) {
            return back()
                ->withErrors([
                    'point' => "Le point {$point->code} a des analyses enregistrées : il ne peut pas être supprimé. Mettez-le plutôt hors service.",
                ])
                ->with('point_a_desactiver', $point->id);
        }

        $point->delete();

        return redirect()->route('admin.prelevements.index')->with('success', 'Point de prélèvement supprimé.');
    }

    // Réseaux pour les listes déroulantes : id => "CAN-TUN-001 — Canalisation (Centre-ville)"
    private function optionsReseaux(): array
    {
        return ReseauEau::with('zone')->orderBy('code')->get()
            ->mapWithKeys(fn (ReseauEau $r) => [$r->id => "{$r->code} — {$r->libelleType()} ({$r->zone->nom})"])
            ->all();
    }

    // Position de chaque réseau (ou centre de sa commune) : la carte du formulaire s'y place quand on le choisit
    private function positionsReseaux(): array
    {
        return ReseauEau::with('zone')->get()
            ->mapWithKeys(fn (ReseauEau $r) => [
                $r->id => $r->latitude !== null && $r->longitude !== null
                    ? [$r->latitude, $r->longitude]
                    : Zone::coordonneesCommune($r->zone->commune),
            ])
            ->all();
    }
}
