{{--
    Double authentification (2FA) avec Laravel Fortify — partagé : back office et site public.
    3 états : désactivée → en cours d'activation (QR code à scanner) → activée (codes de récupération).
--}}
@php
    $statut = session('status');
    $messages = [
        'two-factor-authentication-enabled'   => 'Scannez le QR code puis saisissez le code affiché par votre application pour terminer.',
        'two-factor-authentication-confirmed' => 'La double authentification est activée. Gardez vos codes de récupération en lieu sûr.',
        'two-factor-authentication-disabled'  => 'La double authentification est désactivée.',
        'recovery-codes-generated'            => 'Nouveaux codes de récupération générés : les anciens ne fonctionnent plus.',
    ];
@endphp

@if (isset($messages[$statut]))
    <div class="alert {{ $statut === 'two-factor-authentication-disabled' ? 'alert-warning' : 'alert-success' }}">{{ $messages[$statut] }}</div>
@endif

<p class="text-secondary">
    En plus du mot de passe, un code à 6 chiffres généré par une application
    (Google Authenticator, Microsoft Authenticator…) sera demandé à chaque connexion.
</p>

@if (! $user->two_factor_secret)
    {{-- 1. Désactivée --}}
    <p><span class="badge bg-secondary">Désactivée</span></p>
    <form method="POST" action="{{ route('two-factor.enable') }}">
        @csrf
        <button type="submit" class="btn btn-primary">Activer la double authentification</button>
    </form>

@elseif (! $user->aDoubleAuthentification())
    {{-- 2. Activation en cours : scanner le QR code puis confirmer avec un premier code --}}
    <p><span class="badge bg-warning text-dark">Activation en cours</span></p>
    <ol class="ps-3">
        <li>Ouvrez votre application d'authentification et scannez ce QR code.</li>
        <li>Saisissez le code à 6 chiffres qu'elle affiche.</li>
    </ol>

    <div class="d-inline-block p-2 bg-white border rounded mb-2">{!! $user->twoFactorQrCodeSvg() !!}</div>
    <p class="small text-secondary">
        Pas de caméra ? Saisissez cette clé dans l'application :
        <code class="user-select-all">{{ decrypt($user->two_factor_secret) }}</code>
    </p>

    <form method="POST" action="{{ route('two-factor.confirm') }}" class="row g-2 align-items-start" style="max-width: 420px">
        @csrf
        <div class="col">
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}"
                   required placeholder="123456" aria-label="Code à 6 chiffres"
                   class="form-control font-monospace {{ $errors->confirmTwoFactorAuthentication->has('code') ? 'is-invalid' : '' }}">
            <div class="invalid-feedback">{{ $errors->confirmTwoFactorAuthentication->first('code') }}</div>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Confirmer</button>
        </div>
    </form>

    <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-link px-0 text-secondary">Annuler l'activation</button>
    </form>

@else
    {{-- 3. Activée : codes de récupération (utilisables une seule fois si le téléphone est perdu) --}}
    <p><span class="badge bg-success">Activée</span></p>

    <p class="mb-2">
        <strong>Codes de récupération</strong> : chacun permet de se connecter une seule fois si vous perdez votre téléphone.
        Notez-les ou imprimez-les.
    </p>
    <div class="bg-light border rounded p-3 mb-3 font-monospace" style="max-width: 420px">
        <div class="row">
            @foreach ($user->recoveryCodes() as $code)
                <div class="col-6 py-1 user-select-all">{{ $code }}</div>
            @endforeach
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Générer de nouveaux codes</button>
        </form>
        <form method="POST" action="{{ route('two-factor.disable') }}"
              onsubmit="return confirm('Désactiver la double authentification ? Votre compte sera moins protégé.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Désactiver</button>
        </form>
    </div>
@endif