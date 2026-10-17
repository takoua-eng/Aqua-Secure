<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/*
 * Inscription d'un citoyen (formulaire /inscription).
 * Remplace l'ancien RegisterController.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'nom'      => ['required', 'string', 'min:3', 'max:100', "regex:/^[\pL\s'\-]+$/u"],
            'email'    => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
        ], [
            'nom.required'   => 'Le nom est obligatoire.',
            'nom.min'        => 'Le nom doit contenir au moins 3 caractères.',
            'nom.max'        => 'Le nom ne peut pas dépasser 100 caractères.',
            'nom.regex'      => 'Le nom ne peut contenir que des lettres, des espaces, des tirets et des apostrophes.',
            'email.required' => "L'email est obligatoire.",
            'email.email'    => "L'adresse email n'est pas valide.",
            'email.unique'   => 'Cet email est déjà utilisé.',
            ...$this->passwordMessages(),
        ])->validate();

        // Le rôle est imposé : on ne peut pas s'inscrire comme admin.
        // L'email de vérification est envoyé automatiquement par Laravel (MustVerifyEmail).
        return User::create([
            'nom'      => $input['nom'],
            'email'    => $input['email'],
            'password' => $input['password'],
            'role'     => 'citoyen',
        ]);
    }
}