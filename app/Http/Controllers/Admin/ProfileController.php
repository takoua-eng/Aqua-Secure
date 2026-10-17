<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/*
 * Compte de l'utilisateur connecté : profil, photo, mot de passe, sécurité (2FA) et suppression.
 * Utilisé par le back office (pages Tabler /admin/parametres) ET par les citoyens (/mon-compte).
 */
class ProfileController extends Controller
{
    // Page "Mon profil" (lecture, back office)
    public function show(Request $request)
    {
        return view('admin.profil.show', ['user' => $request->user()]);
    }

    // Paramètres > Mon compte (back office)
    public function edit(Request $request)
    {
        return view('admin.profil.edit', ['user' => $request->user()]);
    }

    // Mon compte (citoyens, site public). Un gestionnaire est renvoyé vers ses paramètres.
    public function compte(Request $request)
    {
        if ($request->user()->peutAccederBackOffice()) {
            return redirect()->route('admin.parametres');
        }

        return view('front.compte', ['user' => $request->user()]);
    }

    // Sécurité : double authentification (2FA) et suppression du compte
    public function securite(Request $request)
    {
        $vue = $request->user()->peutAccederBackOffice() ? 'admin.profil.securite' : 'front.compte-securite';

        return view($vue, ['user' => $request->user()]);
    }

    // Nom, email et photo de profil
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'nom'             => ['required', 'string', 'min:3', 'max:100', "regex:/^[\pL\s'\-]+$/u"],
            'email'           => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'photo'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100'],
            'supprimer_photo' => ['nullable', 'boolean'],
        ], [
            'nom.required'     => 'Le nom est obligatoire.',
            'nom.min'          => 'Le nom doit contenir au moins 3 caractères.',
            'nom.max'          => 'Le nom ne peut pas dépasser 100 caractères.',
            'nom.regex'        => 'Le nom ne peut contenir que des lettres, des espaces, des tirets et des apostrophes.',
            'email.required'   => "L'email est obligatoire.",
            'email.email'      => "L'adresse email n'est pas valide.",
            'email.unique'     => 'Cet email est déjà utilisé par un autre compte.',
            'photo.image'      => 'Le fichier doit être une image.',
            'photo.mimes'      => 'La photo doit être au format JPG, PNG ou WEBP.',
            'photo.max'        => 'La photo ne doit pas dépasser 2 Mo.',
            'photo.dimensions' => 'La photo doit mesurer au moins 100 × 100 pixels.',
        ]);

        // Photo : nouvelle image (l'ancienne est supprimée) ou suppression demandée
        if ($request->hasFile('photo')) {
            $this->supprimerPhoto($user);
            $user->photo = $request->file('photo')->store('photos', 'public');
        } elseif ($request->boolean('supprimer_photo')) {
            $this->supprimerPhoto($user);
            $user->photo = null;
        }

        // Le rôle n'est jamais modifiable ici : seul un admin le change dans "Utilisateurs"
        $user->fill(['nom' => $data['nom'], 'email' => $data['email']]);

        // Nouvel email : il faut le vérifier à nouveau (lien envoyé à la nouvelle adresse)
        $emailChange = $user->isDirty('email');
        if ($emailChange) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChange) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')
                ->with('success', 'Email modifié : cliquez sur le lien envoyé à votre nouvelle adresse pour la confirmer.');
        }

        return back()->with('success', 'Vos informations ont été mises à jour.');
    }

    // Paramètres > Mot de passe (back office)
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

        return back()->with('success', 'Votre mot de passe a été modifié.');
    }

    // Suppression définitive de son propre compte (mot de passe obligatoire)
    public function destroy(Request $request)
    {
        $user = $request->user();

        $request->validateWithBag('suppression', [
            'mot_de_passe' => ['required', 'current_password'],
        ], [
            'mot_de_passe.required'         => 'Saisissez votre mot de passe pour confirmer.',
            'mot_de_passe.current_password' => 'Le mot de passe est incorrect.',
        ]);

        // Il doit toujours rester au moins un administrateur
        if ($user->isAdmin() && User::where('role', 'admin')->count() === 1) {
            return back()->withErrors(['mot_de_passe' => 'Vous êtes le seul administrateur : nommez-en un autre avant de supprimer votre compte.'], 'suppression');
        }

        Auth::logout();
        $this->supprimerPhoto($user);
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Votre compte a été supprimé. Au revoir !');
    }

    private function supprimerPhoto(User $user): void
    {
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }
    }
}