<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Connexion — AquaSecure</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3/dist/tabler-icons.min.css">
</head>
<body class="d-flex flex-column">
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <a href="{{ route('home') }}" class="navbar-brand h1 text-reset">
<img src="{{ asset('favicon.svg') }}" alt="" width="36" height="36" class="me-1"> AquaSecure            </a>
        </div>

        <div class="card card-md">
            <div class="card-body">
                <h2 class="h2 text-center mb-4">Connexion</h2>
                {{-- Messages de Fortify (ex : "Votre mot de passe a été réinitialisé") ou de l'application --}}
                @if (session('status') || session('success'))
                    <div class="alert alert-success">
                        <i class="ti ti-circle-check me-1"></i> {{ session('status') ?? session('success') }}
                    </div>
                @endif

                <form action="{{ route('login') }}" method="POST" data-validate>
                    @csrf

                    <x-input name="email" type="email" label="Adresse email" icon="mail" required autofocus
                             placeholder="vous@exemple.com" autocomplete="email"
                             data-msg-required="Saisissez votre email."
                             data-msg-type="L'adresse email n'est pas valide."
                             valid-message="Email au bon format." />

                    <x-input name="password" type="password" label="Mot de passe" icon="lock" required
                             placeholder="Votre mot de passe" autocomplete="current-password"
                             data-msg-required="Saisissez votre mot de passe."
                             valid-message="" />
                    <div class="text-end mb-3" style="margin-top: -0.5rem">
    <a href="{{ route('password.request') }}" class="small">Mot de passe oublié ?</a>
</div>

                    <label class="form-check mb-3">
                        <input type="checkbox" name="remember" class="form-check-input">
                        <span class="form-check-label">Se souvenir de moi</span>
                    </label>

                    <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                </form>
            </div>
        </div>

        <div class="text-center text-secondary mt-3">
            Pas encore de compte ? <a href="{{ route('register') }}">Créer un compte citoyen</a>
        </div>
        <div class="text-center text-secondary mt-2">
            <a href="{{ route('home') }}">← Retour au site public</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1/dist/js/tabler.min.js"></script>
<script src="{{ asset('js/validation.js') }}"></script>
</body>
</html>