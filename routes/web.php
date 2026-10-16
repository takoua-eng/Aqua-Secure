<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\QualiteEauController;
use App\Http\Controllers\Front\SignalementController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\AnalyseQualiteController;
use App\Http\Controllers\Admin\DetectionContaminationController;
use App\Http\Controllers\Admin\PointPrelevementController;
use App\Http\Controllers\Admin\ReseauEauController;
use App\Http\Controllers\Admin\ReseauImportController;
use App\Http\Controllers\Admin\RisqueController;
use App\Http\Controllers\Admin\ZoneController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Admin\CapteurController;
use App\Http\Controllers\Admin\ReleveController;


// ---------- Front office (citoyens) ----------
Route::get('/', [HomeController::class, 'index'])->name('home');
// Signalement : réservé aux citoyens connectés
Route::middleware(['auth', 'role:citoyen'])->group(function () {
    Route::get('/signaler', [SignalementController::class, 'create'])->name('signalement.create');
    Route::post('/signaler', [SignalementController::class, 'store'])->name('signalement.store');
});

// Qualité de l'eau : consultation par les utilisateurs connectés (citoyens compris)
Route::middleware('auth')->group(function () {
    Route::get('/qualite-eau', [QualiteEauController::class, 'index'])->name('qualite.index');
    Route::get('/qualite-eau/{point}', [QualiteEauController::class, 'show'])->name('qualite.show');
});

// ---------- Connexion des gestionnaires ----------
// ---------- Authentification (tous les rôles) ----------
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'show'])->name('login');
    Route::post('/connexion', [LoginController::class, 'login'])->name('login.submit');
    Route::get('/inscription', [RegisterController::class, 'show'])->name('register');
    Route::post('/inscription', [RegisterController::class, 'store'])->name('register.submit');
        // Mot de passe oublié
    Route::get('/mot-de-passe-oublie', [PasswordResetController::class, 'demande'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetController::class, 'envoyerLien'])->name('password.email');
    Route::get('/reinitialiser-mot-de-passe/{token}', [PasswordResetController::class, 'formulaire'])->name('password.reset');
    Route::post('/reinitialiser-mot-de-passe', [PasswordResetController::class, 'reinitialiser'])->name('password.update');
});

Route::post('/deconnexion', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
// ---------- Back office (gestionnaires) ----------
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:gestionnaire,admin'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        // Compte de l'utilisateur connecté
    Route::get('/profil', [ProfileController::class, 'show'])->name('profil');
    Route::get('/parametres', [ProfileController::class, 'edit'])->name('parametres');
    Route::put('/parametres', [ProfileController::class, 'update'])->name('parametres.update');
    Route::get('/parametres/mot-de-passe', [ProfileController::class, 'password'])->name('parametres.password');
    Route::put('/parametres/mot-de-passe', [ProfileController::class, 'updatePassword'])->name('parametres.password.update');
    // Gestion des utilisateurs : admin uniquement
Route::resource('users', UserController::class)
    ->except('show')
    ->middleware('role:admin');

    // Infrastructure : zones et éléments du réseau d'eau
    Route::resource('zones', ZoneController::class)->except('show');
    Route::get('reseaux/import', [ReseauImportController::class, 'formulaire'])->name('reseaux.import');
    Route::get('reseaux/import/modele', [ReseauImportController::class, 'modele'])->name('reseaux.import.modele');
    Route::post('reseaux/import', [ReseauImportController::class, 'importer'])->name('reseaux.import.store');
    Route::resource('reseaux', ReseauEauController::class)
        ->except('show')
        ->parameters(['reseaux' => 'reseau']);

    // Intelligence artificielle : score de risque, groupes de zones (K-means) et rapport IA
    Route::get('risques', [RisqueController::class, 'index'])->name('risques.index');
    Route::get('risques/pdf', [RisqueController::class, 'pdf'])->name('risques.pdf');
    Route::get('risques/plan', [RisqueController::class, 'plan'])->name('risques.plan');
    Route::get('zones/{zone}/rapport-pdf', [RisqueController::class, 'rapportZonePdf'])
        ->middleware('throttle:10,1')
        ->name('zones.rapport-pdf');
    Route::post('zones/{zone}/analyse-ia', [RisqueController::class, 'analyserZone'])
        ->middleware('throttle:10,1')   // 10 analyses par minute au plus
        ->name('zones.analyse-ia');
     
    // Qualité de l'eau : points de prélèvement et analyses de laboratoire
    // (les routes fixes "carte" et "export" avant les resources, sinon elles seraient lues comme un {point} / {analyse})
    Route::get('prelevements/carte', [PointPrelevementController::class, 'carte'])->name('prelevements.carte');
    Route::resource('prelevements', PointPrelevementController::class)
        ->parameters(['prelevements' => 'point']);
    Route::get('analyses/export', [AnalyseQualiteController::class, 'export'])->name('analyses.export');
    Route::get('analyses/{analyse}/pdf', [AnalyseQualiteController::class, 'pdf'])->name('analyses.pdf');
    Route::resource('analyses', AnalyseQualiteController::class)
        ->parameters(['analyses' => 'analyse']);
    // Détection de contamination (modèle IA) : performances et réentraînement
    Route::get('detection-contamination', [DetectionContaminationController::class, 'index'])->name('detection.index');
    Route::post('detection-contamination/entrainer', [DetectionContaminationController::class, 'entrainer'])
        ->middleware('throttle:10,1')
        ->name('detection.entrainer');
        // Surveillance (binôme 4) : capteurs et relevés
    Route::get('releves/export', [ReleveController::class, 'export'])->name('releves.export');
    Route::post('capteurs/{capteur}/diagnostic-ia', [CapteurController::class, 'diagnostic'])
        ->middleware('throttle:10,1')   // 10 diagnostics par minute au plus
        ->name('capteurs.diagnostic');
        Route::post('capteurs/analyse-globale', [CapteurController::class, 'analyseGlobale'])
        ->middleware('throttle:10,1')
        ->name('capteurs.analyse-globale');
    Route::get('capteurs/{capteur}/rapport-pdf', [CapteurController::class, 'rapportPdf'])
        ->name('capteurs.rapport-pdf');
    Route::resource('capteurs', CapteurController::class)
        ->parameters(['capteurs' => 'capteur']);
    Route::resource('releves', ReleveController::class)
        ->except('show')
        ->parameters(['releves' => 'releve']);
    // Pages provisoires : chaque ligne sera remplacée par un contrôleur CRUD
    // (ex : Route::resource('zones', ZoneController::class);)
    $modules = [
        // url             nom de route      titre                     section             icône           description
        ['incidents',      'incidents',      'Incidents',              'Exploitation',     'alert-triangle', 'Fuites, contaminations et coupures signalées.'],
        ['interventions',  'interventions',  'Interventions',          'Exploitation',     'tool',           'Équipes envoyées sur le terrain et suivi des réparations.'],
        ['projets',        'projets',        'Projets de rénovation',  'Rénovation',       'building',       'Projets de rénovation et de décontamination, budget et financement.'],
    ];

    foreach ($modules as [$url, $nom, $titre, $section, $icone, $description]) {
        Route::view($url, 'admin.placeholder', compact('titre', 'section', 'icone', 'description'))
            ->name($nom . '.index');
    }
});
