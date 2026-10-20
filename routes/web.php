<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\QualiteEauController;
use App\Http\Controllers\Front\SignalementController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\AnalyseQualiteController;
use App\Http\Controllers\Admin\DetectionContaminationController;
use App\Http\Controllers\Admin\PointPrelevementController;
use App\Http\Controllers\Admin\ReseauEauController;
use App\Http\Controllers\Admin\ReseauImportController;
use App\Http\Controllers\Admin\RisqueController;
use App\Http\Controllers\Admin\ZoneController;
use App\Http\Controllers\Admin\CommentaireController;
use App\Http\Controllers\Admin\CoupureController;
use App\Http\Controllers\Front\CoupureController as FrontCoupureController;
use App\Http\Controllers\Admin\CapteurController;
use App\Http\Controllers\Admin\ReleveController;
use App\Http\Controllers\Front\SurveillanceController;
use App\Http\Controllers\Front\IncidentPublicController;

// ---------- Front office (citoyens) ----------
Route::get('/', [HomeController::class, 'index'])->name('home');

// Signalement : réservé aux citoyens connectés (email vérifié)
Route::middleware(['auth', 'verified', 'role:citoyen'])->group(function () {
    Route::get('/signaler', [SignalementController::class, 'create'])->name('signalement.create');
    Route::post('/signaler', [SignalementController::class, 'store'])->name('signalement.store');
});

// Coupures programmées (module Infrastructure) : page publique, les commentaires s'affichent à tous ;
// écrire un commentaire et aimer : citoyens connectés uniquement
Route::get('/coupures', [FrontCoupureController::class, 'index'])->name('coupures.index');
Route::middleware(['auth', 'verified', 'role:citoyen', 'throttle:20,1'])->group(function () {
    Route::post('/coupures/{coupure}/commentaires', [FrontCoupureController::class, 'commenter'])->name('coupures.commenter');
    Route::post('/commentaires/{commentaire}/like', [FrontCoupureController::class, 'aimer'])->name('commentaires.like');
});

// Qualité de l'eau : consultation par les utilisateurs connectés (citoyens compris)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/qualite-eau', [QualiteEauController::class, 'index'])->name('qualite.index');
    Route::get('/qualite-eau/{point}', [QualiteEauController::class, 'show'])->name('qualite.show');
        // Surveillance du réseau (binôme 4) : état des capteurs, consultation uniquement
    Route::get('/surveillance', [SurveillanceController::class, 'index'])->name('surveillance.index');
    Route::get('/surveillance/{capteur}', [SurveillanceController::class, 'show'])->name('surveillance.show');
    // Incidents et travaux (binôme 2) : consultation uniquement
    Route::get('/incidents', [IncidentPublicController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/{incident}', [IncidentPublicController::class, 'show'])->name('incidents.show');
    // Coupures d'eau programmées (module Infrastructure) : choix de sa zone pour recevoir les avis par email
    Route::put('/mon-compte/zone', [FrontCoupureController::class, 'choisirZone'])->name('compte.zone');

});

// ---------- Authentification : gérée par Laravel Fortify ----------
// Connexion, déconnexion, inscription, mot de passe oublié, vérification de l'email et 2FA :
// les routes sont créées par Fortify (adresses en français dans config/fortify.php).

// ---------- Mon compte (citoyens) ----------
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/mon-compte', [ProfileController::class, 'compte'])->name('compte');
    Route::put('/mon-compte', [ProfileController::class, 'update'])->name('compte.update');
    Route::put('/mon-compte/mot-de-passe', [ProfileController::class, 'updatePassword'])->name('compte.password');
    // Sécurité (2FA + suppression du compte) : mot de passe redemandé (valable 3 h)
    Route::get('/mon-compte/securite', [ProfileController::class, 'securite'])
        ->middleware('password.confirm')->name('compte.securite');
    Route::delete('/mon-compte', [ProfileController::class, 'destroy'])->name('compte.destroy');
});

// ---------- Back office (gestionnaires et admins) ----------
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'role:gestionnaire,admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Compte de l'utilisateur connecté
    Route::get('/profil', [ProfileController::class, 'show'])->name('profil');
    Route::get('/parametres', [ProfileController::class, 'edit'])->name('parametres');
    Route::put('/parametres', [ProfileController::class, 'update'])->name('parametres.update');
    Route::get('/parametres/mot-de-passe', [ProfileController::class, 'password'])->name('parametres.password');
    Route::put('/parametres/mot-de-passe', [ProfileController::class, 'updatePassword'])->name('parametres.password.update');
    Route::get('/parametres/securite', [ProfileController::class, 'securite'])
        ->middleware('password.confirm')->name('parametres.securite');

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

    // Coupures programmées (travaux) : les citoyens de la zone sont prévenus
    Route::post('coupures/{coupure}/annuler', [CoupureController::class, 'annuler'])->name('coupures.annuler');
    // Modération des commentaires des citoyens (affichés dans "Voir détails" de chaque coupure)
    Route::delete('commentaires/{commentaire}', [CommentaireController::class, 'destroy'])->name('commentaires.destroy');
    Route::resource('coupures', CoupureController::class)
        ->except('show')
        ->parameters(['coupures' => 'coupure']);
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

    // Exploitation (binôme 2) : incidents et interventions
    require __DIR__.'/incidents.php';


});

// ---------- Rénovation et financement (binôme 3) : CRUD, What If? Engine, page citoyenne ----------
require __DIR__.'/renovation.php';