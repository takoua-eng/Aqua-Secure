<?php

namespace App\Actions\Fortify;

trait PasswordValidationRules
{
    // Mêmes règles que le reste de l'application : 8 caractères, au moins une lettre et un chiffre
    protected function passwordRules(): array
    {
        return ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', 'confirmed'];
    }

    protected function passwordMessages(): array
    {
        return [
            'password.required'  => 'Le mot de passe est obligatoire.',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.regex'     => 'Le mot de passe doit contenir au moins une lettre et un chiffre.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ];
    }
}