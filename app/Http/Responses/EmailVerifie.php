<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\VerifyEmailResponse;

// Après le clic sur le lien de l'email : message de confirmation
class EmailVerifie implements VerifyEmailResponse
{
    public function toResponse($request)
    {
        $route = $request->user()->peutAccederBackOffice() ? 'admin.dashboard' : 'home';

        return redirect()->route($route)->with('success', 'Merci ! Votre adresse email est confirmée.');
    }
}