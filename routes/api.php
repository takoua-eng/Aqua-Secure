<?php

use App\Http\Controllers\Api\TelemetrieController;
use Illuminate\Support\Facades\Route;

/*
 * Routes de l'API (préfixe /api ajouté automatiquement par Laravel).
 * Télémétrie des capteurs (binôme 4) : protégée par la clé X-Api-Key, 120 requêtes par minute au plus.
 */
Route::middleware(['cle.telemetrie', 'throttle:120,1'])
    ->prefix('telemetrie')
    ->group(function () {
        Route::get('capteurs', [TelemetrieController::class, 'capteurs']);
        Route::post('releves', [TelemetrieController::class, 'store']);
    });