@extends('layouts.auth')

@section('title', 'Nouveau mot de passe')

@section('content')
    <form action="{{ route('password.update') }}" method="POST" class="card card-md" data-validate>
        @csrf
        {{-- Jeton secret reçu dans le lien de l'email --}}
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="card-body">
            <h2 class="card-title text-center mb-4">Choisir un nouveau mot de passe</h2>

            <x-input name="email" type="email" label="Adresse email" icon="mail" :value="$email" required
                     autocomplete="email"
                     data-msg-required="L'email est obligatoire."
                     data-msg-type="L'adresse email n'est pas valide."
                     valid-message="" />

            <x-input name="password" type="password" label="Nouveau mot de passe" icon="lock-plus" required autofocus
                     minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).+" autocomplete="new-password"
                     hint="8 caractères minimum, avec une lettre et un chiffre."
                     data-msg-required="Le nouveau mot de passe est obligatoire."
                     data-msg-minlength="Au moins 8 caractères."
                     data-msg-pattern="Il faut au moins une lettre et un chiffre."
                     valid-message="Mot de passe valide." />

            <x-input name="password_confirmation" type="password" label="Confirmer le mot de passe" icon="lock-check" required
                     data-match="password" autocomplete="new-password"
                     data-msg-required="Confirmez le mot de passe."
                     data-msg-match="Les deux mots de passe ne correspondent pas."
                     valid-message="Les mots de passe correspondent." />

            <div class="form-footer">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-device-floppy me-1"></i> Enregistrer le mot de passe
                </button>
            </div>
        </div>
    </form>
@endsection