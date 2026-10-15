<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    // Utilisation dans les routes : ->middleware('role:gestionnaire,admin')
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles)) {
            abort(403, 'Accès non autorisé pour votre rôle.');
        }

        return $next($request);
    }
}