<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse;

// Après l'inscription : page "Vérifiez votre email"
class InscriptionReussie implements RegisterResponse
{
    public function toResponse($request)
    {
        return redirect()->route('verification.notice');
    }
}