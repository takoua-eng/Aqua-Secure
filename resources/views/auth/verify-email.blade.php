@extends('layouts.auth')

@section('title', 'Vérifiez votre email')

@section('content')
    <div class="card card-md">
        <div class="card-body text-center">
            <div class="mb-3"><i class="ti ti-mail-check text-primary" style="font-size: 3rem"></i></div>
            <h2 class="card-title mb-3">Vérifiez votre adresse email</h2>

            @if (session('status') === 'verification-link-sent')
                <div class="alert alert-success text-start">
                    <i class="ti ti-circle-check me-1"></i> Un nouveau lien vient d'être envoyé à <strong>{{ auth()->user()->email }}</strong>.
                </div>
            @elseif (session('success'))
                <div class="alert alert-success text-start">{{ session('success') }}</div>
            @endif

            <p class="text-secondary">
                Nous avons envoyé un lien de confirmation à <strong>{{ auth()->user()->email }}</strong>.
                Cliquez dessus pour activer votre compte. Pensez à regarder dans les spams.
            </p>

            {{-- Fortify : renvoi du lien (6 demandes par minute au plus) --}}
            <form method="POST" action="{{ route('verification.send') }}" class="mt-4">
                @csrf
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-send me-1"></i> Renvoyer le lien
                </button>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="text-center mt-3">
        @csrf
        <button type="submit" class="btn btn-link text-secondary">Se déconnecter</button>
    </form>
@endsection