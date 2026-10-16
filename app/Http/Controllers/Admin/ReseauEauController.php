<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReseauEauRequest;
use App\Models\ReseauEau;
use App\Models\Zone;
use App\Services\ProbabiliteDefaillance;
use App\Services\ScoreRisque;
use Illuminate\Http\Request;

class ReseauEauController extends Controller
{
    public function index(Request $request)
    {
        $reseaux = ReseauEau::query()
            ->with('zone')
            ->withMax('historiques as etat_depuis', 'created_at')   // pour le score de risque
            ->when($request->q, fn ($query, $code) => $query->where('code', 'like', "%{$code}%"))
            ->when($request->zone, fn ($query, $zone) => $query->where('zone_id', $zone))
            ->when($request->type, fn ($query, $type) => $query->where('type', $type))
            ->when($request->etat, fn ($query, $etat) => $query->where('etat', $etat))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.reseaux.index', [
            'reseaux' => $reseaux,
            'zones'   => $this->optionsZones(),
        ]);
    }

    public function create()
    {
        return view('admin.reseaux.create', [
            'reseau' => new ReseauEau(['etat' => 'bon']),
            'zones'  => $this->optionsZones(),
            'centresZones' => $this->centresZones(),
        ]);
    }

    // ReseauEauRequest a déjà tout vérifié : on n'arrive ici que si les données sont valides
    public function store(ReseauEauRequest $request)
    {
        ReseauEau::create($request->validated());

        return redirect()->route('admin.reseaux.index')->with('success', 'Réseau créé.');
    }

    public function edit(ReseauEau $reseau)
    {
        $reseau->load('historiques.user', 'zone');

        return view('admin.reseaux.edit', [
            'reseau' => $reseau,
            'risque' => ScoreRisque::pourReseau($reseau),
            'prediction' => ProbabiliteDefaillance::pourReseau($reseau),
            'zones'  => $this->optionsZones(),
            'centresZones' => $this->centresZones(),
        ]);
    }

    // Un changement d'état est ajouté à l'historique par le modèle ReseauEau
    public function update(ReseauEauRequest $request, ReseauEau $reseau)
    {
        $reseau->update($request->validated());

        return redirect()->route('admin.reseaux.index')->with('success', 'Réseau modifié.');
    }

    public function destroy(ReseauEau $reseau)
    {
        $reseau->delete();

        return redirect()->route('admin.reseaux.index')->with('success', 'Réseau supprimé.');
    }

    // Zones pour les listes déroulantes : id => "Nom — Commune"
    private function optionsZones(): array
    {
        return Zone::orderBy('commune')->orderBy('nom')->get()
            ->mapWithKeys(fn (Zone $zone) => [$zone->id => $zone->libelle()])
            ->all();
    }

    // Centre de la commune de chaque zone : la carte du formulaire s'y place quand on choisit la zone
    private function centresZones(): array
    {
        return Zone::all()
            ->mapWithKeys(fn (Zone $zone) => [$zone->id => Zone::coordonneesCommune($zone->commune)])
            ->all();
    }
}
