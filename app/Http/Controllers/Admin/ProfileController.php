<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // Page "Mon profil" (lecture)
    public function show(Request $request)
    {
        return view('admin.profil.show', ['user' => $request->user()]);
    }

    // Paramètres > Mon compte
    public function edit(Request $request)
    {
        return view('admin.profil.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'nom'   => ['required', 'string', 'min:3', 'max:100', "regex:/^[\pL\s'\-]+$/u"],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'nom.required'   => 'Le nom est obligatoire.',
            'nom.min'        => 'Le nom doit contenir au moins 3 caractères.',
            'nom.max'        => 'Le nom ne peut pas dépasser 100 caractères.',
            'nom.regex'      => 'Le nom ne peut contenir que des lettres, des espaces, des tirets et des apostrophes.',
            'email.required' => "L'email est obligatoire.",
            'email.email'    => "L'adresse email n'est pas valide.",
            'email.unique'   => 'Cet email est déjà utilisé par un autre compte.',
        ]);

        // Le rôle n'est jamais modifiable ici : seul un admin le change dans "Utilisateurs"
        $user->update($data);

        return redirect()->route('admin.parametres')->with('success', 'Vos informations ont été mises à jour.');
    }

    // Paramètres > Mot de passe
    public function password()
    {
        return view('admin.profil.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', 'confirmed', 'different:current_password'],
        ], [
            'current_password.required'         => 'Saisissez votre mot de passe actuel.',
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.required'                 => 'Le nouveau mot de passe est obligatoire.',
            'password.min'                      => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.regex'                    => 'Le mot de passe doit contenir au moins une lettre et un chiffre.',
            'password.confirmed'                => 'Les deux mots de passe ne correspondent pas.',
            'password.different'                => "Le nouveau mot de passe doit être différent de l'actuel.",
        ]);

        $request->user()->update(['password' => $data['password']]);

        return redirect()->route('admin.parametres.password')->with('success', 'Votre mot de passe a été modifié.');
    }
}