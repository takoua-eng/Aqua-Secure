@extends('layouts.auth')

@section('title', 'Mot de passe oublié')

@section('content')
    {{-- Message de Fortify après l'envoi du lien --}}
    @if (session('status'))
        <div class="alert alert-success">
            <i class="ti ti-mail-check me-1"></i> {{ session('status') }}
        </div>
    @endif

    <form action="{{ route('password.email') }}" method="POST" class="card card-md" data-validate>
        @csrf
        <div class="card-body">
            <h2 class="card-title text-center mb-4">Mot de passe oublié</h2>
            <p class="text-secondary mb-4">
                Saisissez l'adresse email de votre compte : nous vous enverrons un lien pour choisir un nouveau mot de passe.
            </p>

            <x-input name="email" type="email" label="Adresse email" icon="mail" required autofocus
                     placeholder="vous@exemple.com" autocomplete="email"
                     data-msg-required="Saisissez l'email de votre compte."
                     data-msg-type="L'adresse email n'est pas valide."
                     valid-message="" />

            <div class="form-footer">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-send me-1"></i> Envoyer le lien
                </button>
            </div>
        </div>
    </form>

    <div class="text-center text-secondary mt-3">
        Vous vous en souvenez ? <a href="{{ route('login') }}">Retour à la connexion</a>
    </div>
@endsection