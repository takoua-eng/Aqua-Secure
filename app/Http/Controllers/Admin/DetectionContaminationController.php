<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DetectionContamination;
use RuntimeException;

// Page "Détection de contamination" du module Qualité de l'eau :
// performances du modèle IA (arbre de décision) et réentraînement
class DetectionContaminationController extends Controller
{
    public function index()
    {
        $modele = DetectionContamination::charger();

        return view('admin.detection.index', [
            'modele' => $modele,
            'regles' => $modele ? DetectionContamination::regles($modele) : [],
        ]);
    }

    // Réentraîne le modèle de détection sur toutes les analyses actuelles
    public function entrainer()
    {
        try {
            $modele = DetectionContamination::entrainer();
        } catch (RuntimeException $e) {
            return back()->withErrors(['modele' => $e->getMessage()]);
        }

        return redirect()->route('admin.detection.index')->with('success', sprintf(
            'Modèle réentraîné sur %d analyses : %s %% des contaminations de test détectées.',
            $modele['donnees']['total'],
            number_format(($modele['metriques']['rappel'] ?? 0) * 100, 1, ',', ' '),
        ));
    }
}
