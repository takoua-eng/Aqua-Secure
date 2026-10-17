<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/*
 * Nouveau mot de passe choisi depuis le lien "mot de passe oublié".
 * Remplace l'ancien PasswordResetController (le jeton et son expiration sont gérés par Laravel).
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ], $this->passwordMessages())->validate();

        $user->forceFill(['password' => $input['password']])->save();   // chiffré par le cast "hashed"
    }
}