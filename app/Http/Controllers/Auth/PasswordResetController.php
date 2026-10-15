<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    // 1. Page "Mot de passe oublié"
    public function demande()
    {
        return view('auth.forgot-password');
    }

    // 2. Envoi du lien par email
    public function envoyerLien(Request $request)
    {
        $request->validate(
            ['email' => ['required', 'email']],
            [
                'email.required' => "Saisissez l'email de votre compte.",
                'email.email'    => "L'adresse email n'est pas valide.",
            ]
        );

        $statut = Password::sendResetLink($request->only('email'));

        if ($statut === Password::RESET_THROTTLED) {
            return back()->withInput()
                ->withErrors(['email' => 'Un lien vient déjà d\'être envoyé. Patientez une minute avant de réessayer.']);
        }

        // Même message que l'email existe ou non : on ne révèle pas quels emails sont inscrits
        return back()->with('success', 'Si un compte existe avec cet email, un lien de réinitialisation vient de vous être envoyé.');
    }

    // 3. Page "Nouveau mot de passe" (ouverte depuis le lien de l'email)
    public function formulaire(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    // 4. Enregistrement du nouveau mot de passe
    public function reinitialiser(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', 'confirmed'],
        ], [
            'email.required'     => "L'email est obligatoire.",
            'email.email'        => "L'adresse email n'est pas valide.",
            'password.required'  => 'Le nouveau mot de passe est obligatoire.',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.regex'     => 'Le mot de passe doit contenir au moins une lettre et un chiffre.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $statut = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill(['password' => $password])   // chiffré automatiquement (cast "hashed")
                     ->setRememberToken(Str::random(60));     // déconnecte les autres appareils
                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($statut === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
        }

        $message = match ($statut) {
            Password::INVALID_TOKEN => 'Ce lien est invalide ou a expiré. Faites une nouvelle demande.',
            Password::INVALID_USER  => 'Aucun compte ne correspond à cet email.',
            default                 => 'La réinitialisation a échoué. Réessayez.',
        };

        return back()->withInput($request->only('email'))->withErrors(['email' => $message]);
    }
}