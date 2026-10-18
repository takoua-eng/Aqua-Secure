<?php

use App\Http\Controllers\Admin\Renovation\EchangeFinancementsController;
use App\Http\Controllers\Admin\Renovation\FinancementController;
use App\Http\Controllers\Admin\Renovation\ModelesIAController;
use App\Http\Controllers\Admin\Renovation\ProjetRenovationController;
use App\Http\Controllers\Admin\Renovation\QuestionCitoyenneController;
use App\Http\Controllers\Admin\Renovation\SimulationController;
use App\Http\Controllers\Admin\Renovation\TableauFinancierController;
use App\Http\Controllers\Admin\Renovation\TerritoiresController;
use App\Http\Controllers\Admin\Renovation\WhatIfController;
use App\Http\Controllers\Front\RenovationPubliqueController;
use Illuminate\Support\Facades\Route;

/*
 * Binôme 3 — Financement des rénovations.
 * Chargé depuis routes/web.php (groupe « web » : sessions, CSRF) par :  require __DIR__.'/renovation.php';
 * Les droits fins (auteur, administrateur, statut) sont vérifiés par les Policies dans les contrôleurs.
 */

// ---------- Citoyens : page de transparence (projets publiés uniquement, consultation) ----------
Route::get('/renovation', [RenovationPubliqueController::class, 'index'])->name('renovation.index');
Route::get('/renovation/{projet}', [RenovationPubliqueController::class, 'show'])
    ->whereNumber('projet')->name('renovation.show');

// Participation (citoyens connectés, email vérifié) : soutien et questions publiques après réponse
Route::middleware(['auth', 'verified', 'role:citoyen'])->group(function () {
    Route::post('/renovation/{projet}/soutien', [RenovationPubliqueController::class, 'soutenir'])
        ->whereNumber('projet')->middleware('throttle:20,1')->name('renovation.soutien');
    Route::post('/renovation/{projet}/questions', [RenovationPubliqueController::class, 'poser'])
        ->whereNumber('projet')->middleware('throttle:5,1')->name('renovation.questions.store');
});

// ---------- Back office : gestionnaires et administrateurs ----------
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'role:gestionnaire,admin'])->group(function () {
    // Smart Budget Monitoring
    Route::get('financement', [TableauFinancierController::class, 'index'])->name('renovation.tableau');
    Route::post('financement/alertes-ia', [TableauFinancierController::class, 'expliquerAlertes'])
        ->middleware('throttle:10,1')->name('renovation.alertes-ia');
    Route::get('financement/rapport-mensuel', [TableauFinancierController::class, 'rapportMensuel'])
        ->middleware('throttle:10,1')->name('renovation.rapport-mensuel');

    // Modèles IA entraînés sur les données (coût, source de financement)
    Route::get('financement/modeles-ia', [ModelesIAController::class, 'index'])->name('renovation.modeles');
    Route::post('financement/modeles-ia/entrainer', [ModelesIAController::class, 'entrainer'])
        ->middleware('throttle:5,1')->name('renovation.modeles.entrainer');

    // Équité territoriale et comparatif des 24 gouvernorats
    Route::get('financement/territoires', [TerritoiresController::class, 'index'])->name('renovation.territoires');

    // Échanges Excel (CSV) des financements — avant la resource, sinon « export » serait lu comme un {financement}
    Route::get('financements/export', [EchangeFinancementsController::class, 'export'])->name('financements.export');
    Route::get('financements/import', [EchangeFinancementsController::class, 'formulaire'])->name('financements.import');
    Route::get('financements/import/modele', [EchangeFinancementsController::class, 'modele'])->name('financements.import.modele');
    Route::post('financements/import', [EchangeFinancementsController::class, 'importer'])
        ->middleware('throttle:10,1')->name('financements.import.store');

    // CRUD (remplace la page provisoire « admin.projets.index » de routes/web.php)
    Route::resource('projets', ProjetRenovationController::class)->parameters(['projets' => 'projet']);
    Route::get('projets/{projet}/panneau', [ProjetRenovationController::class, 'panneau'])->name('projets.panneau');
    Route::resource('financements', FinancementController::class)->parameters(['financements' => 'financement']);

    // Questions des citoyens : réponse publique ou suppression (la question doit appartenir au projet)
    Route::post('projets/{projet}/questions/{question}/reponse', [QuestionCitoyenneController::class, 'repondre'])
        ->scopeBindings()->name('projets.questions.repondre');
    Route::delete('projets/{projet}/questions/{question}', [QuestionCitoyenneController::class, 'supprimer'])
        ->scopeBindings()->name('projets.questions.destroy');

    // What If? Engine : calculs sans écriture en base
    Route::get('what-if', [WhatIfController::class, 'index'])->name('whatif.index');
    Route::get('what-if/pluriannuel', [WhatIfController::class, 'pluriannuel'])->name('whatif.pluriannuel');
    Route::post('what-if/simuler', [WhatIfController::class, 'simuler'])
        ->middleware('throttle:60,1')->name('whatif.simuler');
    Route::post('what-if/analyse-ia', [WhatIfController::class, 'analyser'])
        ->middleware('throttle:10,1')->name('whatif.analyser');

    // Scénarios enregistrés : historique, comparaison, validation, application
    Route::get('simulations/comparer', [SimulationController::class, 'comparer'])->name('simulations.comparer');
    Route::resource('simulations', SimulationController::class)
        ->only(['index', 'store', 'show', 'destroy'])
        ->parameters(['simulations' => 'simulation']);
    Route::post('simulations/{simulation}/analyse-ia', [SimulationController::class, 'analyser'])
        ->middleware('throttle:10,1')->name('simulations.analyser');
    Route::post('simulations/{simulation}/soumettre', [SimulationController::class, 'soumettre'])->name('simulations.soumettre');
    Route::post('simulations/{simulation}/decision', [SimulationController::class, 'decider'])->name('simulations.decider');
    Route::post('simulations/{simulation}/appliquer', [SimulationController::class, 'appliquer'])->name('simulations.appliquer');
    Route::get('simulations/{simulation}/rapport', [SimulationController::class, 'rapport'])->name('simulations.rapport');
});
