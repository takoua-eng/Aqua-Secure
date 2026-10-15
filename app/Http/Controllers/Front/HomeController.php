<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    public function index()
    {
        // Données d'exemple : à remplacer par Projet::all() et Incident::where(...)->get()
        $projets = [
            ['nom' => 'Rénovation conduite principale', 'localite' => 'Tunis Centre', 'budget' => 450000, 'finance' => 340000],
            ['nom' => 'Décontamination réservoir R2', 'localite' => 'Ariana', 'budget' => 180000, 'finance' => 60000],
            ['nom' => 'Forage de secours sécheresse', 'localite' => 'Ben Arous', 'budget' => 300000, 'finance' => 150000],
        ];

        $incidents = [
            ['type' => 'fuite', 'localite' => 'Bab Saadoun', 'lat' => 36.8090, 'lng' => 10.1590],
            ['type' => 'contamination', 'localite' => 'Ariana Ville', 'lat' => 36.8625, 'lng' => 10.1956],
            ['type' => 'coupure', 'localite' => 'Ben Arous', 'lat' => 36.7531, 'lng' => 10.2189],
        ];

        return view('front.home', compact('projets', 'incidents'));
    }
}
