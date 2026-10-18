<?php

namespace App\Http\Controllers\Admin\Renovation;

use App\Http\Controllers\Controller;
use App\Models\ProjetRenovation;
use App\Services\Renovation\EquiteTerritoriale;
use Illuminate\Support\Facades\Gate;

// Binôme 3 — équité territoriale et comparatif des 24 gouvernorats (lecture seule)
class TerritoiresController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', ProjetRenovation::class);

        $territoires = collect(EquiteTerritoriale::parGouvernorat());
        $avecPopulation = $territoires->filter(fn ($t) => $t['population'] > 0);
        $lorenz = EquiteTerritoriale::lorenz($avecPopulation->values()->all());
        $moyenne = $avecPopulation->sum('population') > 0
            ? round($avecPopulation->sum('finance') / $avecPopulation->sum('population'), 2) : null;

        return view('admin.renovation.territoires', [
            'territoires' => $territoires->sortByDesc(fn ($t) => $t['par_habitant'] ?? -1)->values(),
            'lorenz'      => $lorenz,
            'lecture'     => EquiteTerritoriale::lecture($lorenz['gini']),
            'moyenne'     => $moyenne,
            'sansProjet'  => $territoires->where('projets', 0)->pluck('gouvernorat'),
            'sansZone'    => $territoires->where('population', 0)->pluck('gouvernorat'),
        ]);
    }
}
