<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function show()
    {
        return view('front.register');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom'      => ['required', 'string', 'min:3', 'max:100', "regex:/^[\pL\s'\-]+$/u"],
            'email'    => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', 'confirmed'],
        ], [
            'nom.required'       => 'Le nom est obligatoire.',
            'nom.min'            => 'Le nom doit contenir au moins 3 caractères.',
            'nom.max'            => 'Le nom ne peut pas dépasser 100 caractères.',
            'nom.regex'          => 'Le nom ne peut contenir que des lettres, des espaces, des tirets et des apostrophes.',
            'email.required'     => "L'email est obligatoire.",
            'email.email'        => "L'adresse email n'est pas valide.",
            'email.unique'       => 'Cet email est déjà utilisé.',
            'password.required'  => 'Le mot de passe est obligatoire.',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.regex'     => 'Le mot de passe doit contenir au moins une lettre et un chiffre.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        // Le rôle est imposé : un citoyen ne peut pas s'inscrire comme admin
        $user = User::create($data + ['role' => 'citoyen']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Votre compte a été créé. Bienvenue ' . $user->nom . ' !');
    }
}