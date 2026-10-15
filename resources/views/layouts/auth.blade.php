<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title') — AquaSecure</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3/dist/tabler-icons.min.css">
</head>
<body class="d-flex flex-column">
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <a href="{{ route('home') }}" class="navbar-brand h1 text-reset d-inline-flex align-items-center gap-2">
                <img src="{{ asset('favicon.svg') }}" alt="" width="36" height="36"> AquaSecure
            </a>
        </div>

        @yield('content')
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1/dist/js/tabler.min.js"></script>
<script src="{{ asset('js/validation.js') }}"></script>
</body>
</html>