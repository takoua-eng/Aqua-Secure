@extends('layouts.front')

@section('title', 'Créer un compte')

@section('content')
<section class="section section-clair">
    <div class="container" style="max-width: 520px">
        <div class="card border-0 shadow-sm p-4">
            <h1 class="h3 fw-bold mb-1">Créer un compte citoyen</h1>
            <p class="text-secondary mb-4">Pour signaler et suivre les incidents de votre quartier.</p>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-1"></i> Veuillez corriger les champs en rouge.
                </div>
            @endif

            <form method="POST" action="{{ route('register.submit') }}" data-validate>
                @csrf

                <x-front-input name="nom" label="Nom complet" icon="person" required
                               placeholder="Ex : Salma Ben Ali"
                               minlength="3" maxlength="100" pattern="[A-Za-zÀ-ÿ' \-]+"
                               data-msg-required="Le nom est obligatoire."
                               data-msg-minlength="Le nom doit contenir au moins 3 caractères."
                               data-msg-pattern="Lettres, espaces, tirets et apostrophes uniquement."
                               valid-message="Nom valide." />

                <x-front-input name="email" type="email" label="Email" icon="envelope" required
                               placeholder="nom@exemple.com" maxlength="255"
                               data-msg-required="L'email est obligatoire."
                               data-msg-type="L'adresse email n'est pas valide (ex : nom@exemple.com)."
                               valid-message="Cet email semble correct." />

                <x-front-input name="password" type="password" label="Mot de passe" icon="lock" required
                               minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).+" autocomplete="new-password"
                               data-msg-required="Le mot de passe est obligatoire."
                               data-msg-minlength="Au moins 8 caractères."
                               data-msg-pattern="Il faut au moins une lettre et un chiffre."
                               valid-message="Mot de passe valide." />

                <x-front-input name="password_confirmation" type="password" label="Confirmer le mot de passe"
                               icon="lock-fill" required data-match="password" autocomplete="new-password"
                               data-msg-required="Confirmez le mot de passe."
                               data-msg-match="Les deux mots de passe ne correspondent pas."
                               valid-message="Les mots de passe correspondent." />

                <button type="submit" class="btn btn-eau w-100 mt-2">Créer mon compte</button>
            </form>

            <p class="text-center small mt-3 mb-0">
                Déjà inscrit ? <a href="{{ route('login') }}">Se connecter</a>
            </p>
        </div>
    </div>
</section>
@endsection