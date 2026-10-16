<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// Simulateur de capteurs (binôme 4) : une mesure par capteur actif chaque minute.
// Pour le lancer en continu : php artisan schedule:work
Schedule::command('capteurs:simuler')->everyMinute();