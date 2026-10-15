<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        // Données d'exemple : à remplacer par des requêtes Eloquent
        $stats = [
            ['libelle' => 'Incidents ouverts', 'valeur' => 12, 'icone' => 'alert-triangle', 'couleur' => 'red'],
            ['libelle' => 'Fuites signalées', 'valeur' => 7, 'icone' => 'droplet', 'couleur' => 'azure'],
            ['libelle' => 'Infrastructures suivies', 'valeur' => 48, 'icone' => 'building-factory-2', 'couleur' => 'green'],
            ['libelle' => 'Projets en cours', 'valeur' => 3, 'icone' => 'coin', 'couleur' => 'orange'],
        ];

        $projets = [
            ['nom' => 'Rénovation conduite principale', 'budget' => 450000, 'finance' => 340000],
            ['nom' => 'Décontamination réservoir R2', 'budget' => 180000, 'finance' => 60000],
            ['nom' => 'Forage de secours sécheresse', 'budget' => 300000, 'finance' => 150000],
        ];

        $incidents = [
            ['date' => '15/10/2026', 'type' => 'fuite', 'localite' => 'Bab Saadoun', 'gravite' => 'moyenne', 'statut' => 'nouveau', 'lat' => 36.8090, 'lng' => 10.1590],
            ['date' => '14/10/2026', 'type' => 'contamination', 'localite' => 'Ariana Ville', 'gravite' => 'haute', 'statut' => 'en cours', 'lat' => 36.8625, 'lng' => 10.1956],
            ['date' => '12/10/2026', 'type' => 'coupure', 'localite' => 'Ben Arous', 'gravite' => 'faible', 'statut' => 'résolu', 'lat' => 36.7531, 'lng' => 10.2189],
        ];

        return view('admin.dashboard', compact('stats', 'projets', 'incidents'));
    }
}
