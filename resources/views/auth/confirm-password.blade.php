@extends('layouts.auth')

@section('title', 'Confirmer le mot de passe')

@section('content')
    {{-- Fortify : demandé avant les actions sensibles (2FA, suppression du compte), valable 3 heures --}}
    <form method="POST" action="{{ route('password.confirm') }}" class="card card-md" data-validate>
        @csrf
        <div class="card-body">
            <h2 class="card-title text-center mb-3">Confirmez votre mot de passe</h2>
            <p class="text-secondary text-center mb-4">
                Cette zone est sensible : saisissez à nouveau votre mot de passe pour continuer.
            </p>

            <x-input name="password" type="password" label="Mot de passe" icon="lock" required autofocus
                     autocomplete="current-password"
                     data-msg-required="Saisissez votre mot de passe."
                     valid-message="" />

            <div class="form-footer">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-shield-check me-1"></i> Confirmer
                </button>
            </div>
        </div>
    </form>
@endsection