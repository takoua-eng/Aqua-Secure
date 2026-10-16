<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ZoneRequest;
use App\Models\Zone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index(Request $request)
    {
        $zones = Zone::query()
            ->withCount('reseaux')
            ->when($request->q, function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                      ->orWhere('commune', 'like', "%{$recherche}%");
                });
            })
            ->orderBy('commune')
            ->orderBy('nom')
            ->paginate(12)
            ->withQueryString();

        return view('admin.zones.index', compact('zones'));
    }

    public function create()
    {
        return view('admin.zones.create', ['zone' => new Zone()]);
    }

    // ZoneRequest a déjà tout vérifié : on n'arrive ici que si les données sont valides
    public function store(ZoneRequest $request)
    {
        Zone::create($request->validated());

        return redirect()->route('admin.zones.index')->with('success', 'Zone créée.');
    }

    public function edit(Zone $zone)
    {
        return view('admin.zones.edit', compact('zone'));
    }

    public function update(ZoneRequest $request, Zone $zone)
    {
        $zone->update($request->validated());

        return redirect()->route('admin.zones.index')->with('success', 'Zone modifiée.');
    }

    public function destroy(Zone $zone)
    {
        // Une zone qui contient encore des réseaux ne peut pas être supprimée
        if ($zone->reseaux()->exists()) {
            return back()->withErrors([
                'zone' => "La zone « {$zone->nom} » contient encore des réseaux : supprimez-les ou déplacez-les d'abord.",
            ]);
        }

        $zone->delete();

        return redirect()->route('admin.zones.index')->with('success', 'Zone supprimée.');
    }
}
