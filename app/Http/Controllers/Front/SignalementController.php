<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SignalementController extends Controller
{
    public function create()
    {
        return view('front.signalement');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'        => ['required', 'in:fuite,contamination,coupure'],
            'localite'    => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'email'       => ['nullable', 'email'],
            'photo'       => ['nullable', 'image', 'max:4096'],
            'latitude'    => ['required', 'numeric', 'between:-90,90'],
            'longitude'   => ['required', 'numeric', 'between:-180,180'],
        ], [
            'latitude.required' => "Cliquez sur la carte pour indiquer l'emplacement.",
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('signalements', 'public');
        }

        // Étape suivante : Incident::create($data);

        return redirect()->route('home')->with('success', 'Merci ! Votre signalement a bien été envoyé.');
    }
}
