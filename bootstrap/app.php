<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
                // Derrière l'Ingress NGINX (NodePort 30080) : on ne fait PAS confiance à X-Forwarded-Port
        // (il vaut 80, le port interne du contrôleur). Le port 30080 est lu dans X-Forwarded-Host.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();