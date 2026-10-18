<?php

use App\Http\Controllers\Admin\IncidentAnalyseController;
use App\Http\Controllers\Admin\IncidentController;
use App\Http\Controllers\Admin\IncidentIAController;
use App\Http\Controllers\Admin\InterventionController;
use Illuminate\Support\Facades\Route;

/*
 * Exploitation (binôme 2) : incidents et interventions.
 * Ce fichier est chargé depuis routes/web.php, À L'INTÉRIEUR du groupe "admin" :
 * préfixe /admin, noms "admin.*" et middlewares auth + verified + role:gestionnaire,admin déjà appliqués.
 */

// Routes fixes avant les resources, sinon "priorites" serait lu comme un {incident}
Route::get('incidents/priorites', [IncidentAnalyseController::class, 'priorites'])->name('incidents.priorites');
Route::get('incidents/statistiques', [IncidentAnalyseController::class, 'statistiques'])->name('incidents.statistiques');
Route::get('incidents/statistiques/donnees', [IncidentAnalyseController::class, 'donnees'])->name('incidents.statistiques.donnees');
Route::get('incidents/analyse-ia', [IncidentIAController::class, 'index'])->name('incidents.ia');
Route::post('incidents/analyse-ia', [IncidentIAController::class, 'lancer'])
    ->middleware('throttle:5,1')   // 5 analyses par minute au plus
    ->name('incidents.ia.lancer');
Route::resource('incidents', IncidentController::class);

// Une intervention se crée depuis la fiche de son incident
Route::get('incidents/{incident}/interventions/create', [InterventionController::class, 'create'])->name('incidents.interventions.create');
Route::post('incidents/{incident}/interventions', [InterventionController::class, 'store'])->name('incidents.interventions.store');

Route::get('interventions/historique', [InterventionController::class, 'historique'])->name('interventions.historique');
Route::patch('interventions/{intervention}/statut', [InterventionController::class, 'changerStatut'])->name('interventions.statut');
Route::resource('interventions', InterventionController::class)->except(['create', 'store']);
