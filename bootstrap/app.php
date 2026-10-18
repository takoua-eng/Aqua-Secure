<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',          // API de télémétrie des capteurs (binôme 4)
        commands: __DIR__.'/../routes/console.php',
        health: '/up',                               // sonde de santé utilisée par Kubernetes et Jenkins
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'cle.telemetrie' => \App\Http\Middleware\VerifierCleTelemetrie::class,
        ]);

        // Derrière l'Ingress Nginx de Kubernetes : faire confiance aux en-têtes X-Forwarded-*
        // (adresse IP du visiteur, protocole et port d'origine)
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();