<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * Protège l'API de télémétrie : chaque requête doit envoyer l'en-tête
 *   X-Api-Key: <TELEMETRIE_API_KEY du fichier .env>
 */
class VerifierCleTelemetrie
{
    public function handle(Request $request, Closure $next): Response
    {
        $cleAttendue = (string) config('services.telemetrie.cle');

        if ($cleAttendue === '') {
            return response()->json(['erreur' => "L'API de télémétrie est désactivée : TELEMETRIE_API_KEY est vide dans le .env."], 503);
        }

        // hash_equals : comparaison à temps constant (évite de deviner la clé caractère par caractère)
        if (! hash_equals($cleAttendue, (string) $request->header('X-Api-Key'))) {
            return response()->json(['erreur' => 'Clé API manquante ou invalide (en-tête X-Api-Key).'], 401);
        }

        return $next($request);
    }
}