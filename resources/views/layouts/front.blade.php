<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Accueil') — AquaSecure</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    {{-- Bootstrap 5 + icônes, chargés par CDN : rien à copier dans public/ --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">

    <style>
        :root {
            --eau-bleu: #0b6fb8;
            --eau-fonce: #0a3d62;
            --eau-clair: #e8f4fc;
        }
        body { font-family: 'Poppins', sans-serif; color: #33475b; }
        .navbar-eau { background: var(--eau-fonce); }
        .btn-eau { background: var(--eau-bleu); color: #fff; border: none; }
        .btn-eau:hover { background: #095c99; color: #fff; }
        .hero {
            background: linear-gradient(135deg, var(--eau-fonce) 0%, var(--eau-bleu) 100%);
            color: #fff; padding: 110px 0 90px;
        }
        .section { padding: 70px 0; }
        .section-clair { background: var(--eau-clair); }
        .carte-service { border: none; border-radius: 14px; transition: transform .2s; }
        .carte-service:hover { transform: translateY(-4px); }
        .icone-rond {
            width: 58px; height: 58px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem;
        }
        footer { background: var(--eau-fonce); color: #c9d6e2; }
        footer a { color: #fff; }
    </style>
    @stack('styles')
</head>
<body>
    @include('front.partials.header')

    <main>
        @if (session('success'))
            <div class="container mt-4">
                <div class="alert alert-success">{{ session('success') }}</div>
            </div>
        @endif
        @yield('content')
    </main>

    @include('front.partials.footer')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
