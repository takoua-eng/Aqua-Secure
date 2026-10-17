<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

/*
 * Après la connexion (avec ou sans code 2FA) : redirection selon le rôle.
 * Gestionnaire / admin → tableau de bord ; citoyen → site public.
 */
class ConnexionReussie implements LoginResponse, TwoFactorLoginResponse
{
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user->peutAccederBackOffice()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('home'))->with('success', 'Bienvenue ' . $user->nom . ' !');
    }
}