@extends('layouts.front')

@section('title', 'Mon compte')

@section('content')
<section class="section section-clair">
    <div class="container" style="max-width: 760px">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 fw-bold mb-0">Mon compte</h1>
            <a href="{{ route('compte.securite') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-shield-lock"></i> Sécurité (2FA, suppression)
            </a>
        </div>

        {{-- 1. Informations et photo --}}
        <div class="card border-0 shadow-sm p-4 mb-4">
            <h2 class="h5 fw-semibold mb-3">Mes informations</h2>

            <form method="POST" action="{{ route('compte.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row align-items-center mb-3">
                    <div class="col-auto">
                        <span id="apercu-photo">
                            @if ($user->photo)
                                <img src="{{ $user->photoUrl() }}" alt="Photo de profil" class="rounded-circle"
                                     style="width: 96px; height: 96px; object-fit: cover">
                            @else
                                <span class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-white"
                                      style="width: 96px; height: 96px; background: var(--eau-bleu); font-size: 2rem">{{ $user->initiales() }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="col">
                        @include('compte._photo')
                    </div>
                </div>

                <x-front-input name="nom" label="Nom complet" icon="person" :value="$user->nom" required
                               minlength="3" maxlength="100" pattern="[A-Za-zÀ-ÿ' \-]+"
                               data-msg-required="Le nom est obligatoire."
                               data-msg-minlength="Le nom doit contenir au moins 3 caractères."
                               data-msg-pattern="Lettres, espaces, tirets et apostrophes uniquement."
                               valid-message="Nom valide." />

                <x-front-input name="email" type="email" label="Email" icon="envelope" :value="$user->email" required
                               maxlength="255"
                               data-msg-required="L'email est obligatoire."
                               data-msg-type="L'adresse email n'est pas valide."
                               valid-message="Si vous changez d'email, un lien de confirmation sera envoyé à la nouvelle adresse." />

                <button type="submit" class="btn btn-eau">Enregistrer</button>
            </form>
        </div>

        {{-- Ma zone : avis de coupure d'eau programmée --}}
        <div class="card border-0 shadow-sm p-4 mb-4" id="ma-zone">
            <h2 class="h5 fw-semibold mb-3">Ma zone</h2>
            @include('front.partials.ma-zone')
        </div>

        {{-- 2. Mot de passe --}}
        <div class="card border-0 shadow-sm p-4">
            <h2 class="h5 fw-semibold mb-3">Mot de passe</h2>

            <form method="POST" action="{{ route('compte.password') }}">
                @csrf
                @method('PUT')

                <x-front-input name="current_password" type="password" label="Mot de passe actuel" icon="lock" required
                               autocomplete="current-password"
                               data-msg-required="Saisissez votre mot de passe actuel."
                               valid-message="" />

                <div class="row">
                    <div class="col-md-6">
                        <x-front-input name="password" type="password" label="Nouveau mot de passe" icon="lock" required
                                       minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).+" autocomplete="new-password"
                                       data-msg-required="Le nouveau mot de passe est obligatoire."
                                       data-msg-minlength="Au moins 8 caractères."
                                       data-msg-pattern="Il faut au moins une lettre et un chiffre."
                                       valid-message="Mot de passe valide." />
                    </div>
                    <div class="col-md-6">
                        <x-front-input name="password_confirmation" type="password" label="Confirmer" icon="lock-fill" required
                                       data-match="password" autocomplete="new-password"
                                       data-msg-required="Confirmez le nouveau mot de passe."
                                       data-msg-match="Les deux mots de passe ne correspondent pas."
                                       valid-message="Les mots de passe correspondent." />
                    </div>
                </div>

                <button type="submit" class="btn btn-eau">Changer le mot de passe</button>
            </form>
        </div>
    </div>
</section>
@endsection