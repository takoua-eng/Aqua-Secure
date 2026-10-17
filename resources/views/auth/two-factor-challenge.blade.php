@extends('layouts.auth')

@section('title', 'Double authentification')

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <div class="text-center mb-3"><i class="ti ti-device-mobile-message text-primary" style="font-size: 3rem"></i></div>
            <h2 class="card-title text-center mb-3">Double authentification</h2>

            {{-- 1. Code à 6 chiffres de l'application (Google Authenticator, Microsoft Authenticator…) --}}
            <form method="POST" action="{{ route('two-factor.login') }}" id="form-code"
                  class="{{ $errors->has('recovery_code') ? 'd-none' : '' }}" data-validate>
                @csrf
                <p class="text-secondary text-center">Ouvrez votre application d'authentification et saisissez le code à 6 chiffres.</p>

                <x-input name="code" label="Code de vérification" icon="key" autofocus
                         inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}"
                         placeholder="123456" class="text-center fs-2 font-monospace"
                         data-msg-pattern="Le code contient exactement 6 chiffres."
                         valid-message="" />

                <button type="submit" class="btn btn-primary w-100">Valider</button>
                <div class="text-center mt-3">
                    <a href="#" class="small" data-basculer>Je n'ai pas mon téléphone : utiliser un code de récupération</a>
                </div>
            </form>

            {{-- 2. Code de récupération (affiché lors de l'activation de la 2FA, utilisable une seule fois) --}}
            <form method="POST" action="{{ route('two-factor.login') }}" id="form-recuperation"
                  class="{{ $errors->has('recovery_code') ? '' : 'd-none' }}" data-validate>
                @csrf
                <p class="text-secondary text-center">Saisissez l'un de vos codes de récupération.</p>

                <x-input name="recovery_code" label="Code de récupération" icon="lifebuoy"
                         autocomplete="one-time-code" placeholder="abcde12345-fghij67890" class="font-monospace"
                         valid-message="" />

                <button type="submit" class="btn btn-primary w-100">Valider</button>
                <div class="text-center mt-3">
                    <a href="#" class="small" data-basculer>Utiliser le code de l'application</a>
                </div>
            </form>
        </div>
    </div>

    <div class="text-center text-secondary mt-3">
        <a href="{{ route('login') }}">← Retour à la connexion</a>
    </div>

    <script>
        // Bascule entre le code de l'application et le code de récupération
        document.querySelectorAll('[data-basculer]').forEach(lien => lien.addEventListener('click', e => {
            e.preventDefault();
            document.getElementById('form-code').classList.toggle('d-none');
            document.getElementById('form-recuperation').classList.toggle('d-none');
        }));
    </script>
@endsection