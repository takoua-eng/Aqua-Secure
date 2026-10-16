<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Administration') — AquaSecure</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    {{-- Tabler (back office) + icônes, chargés par CDN — couleurs d'origine du template --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3/dist/tabler-icons.min.css">

    {{-- Restaure l'état du menu (réduit ou non) avant l'affichage, pour éviter un clignotement --}}
    <script>
        try {
            if (localStorage.getItem('sidebar-folded') === '1') {
                document.documentElement.classList.add('sidebar-folded');
            }
        } catch (e) {}
    </script>

    {{-- Menu réduit : seulement la largeur et l'affichage changent, les couleurs restent celles de Tabler --}}
    <style>
        @media (min-width: 992px) {
            .navbar-vertical.navbar-expand-lg,
            .navbar-vertical.navbar-expand-lg ~ .page-wrapper {
                transition: width .2s ease, margin-left .2s ease;
            }
            .sidebar-folded .navbar-vertical.navbar-expand-lg {
                width: 4.5rem;
            }
            .sidebar-folded .navbar-vertical.navbar-expand-lg ~ .page-wrapper {
                margin-left: 4.5rem !important;
            }
            /* On cache les textes, on garde les icônes */
            .sidebar-folded .navbar-vertical .brand-text,
            .sidebar-folded .navbar-vertical .nav-link-title,
            .sidebar-folded .navbar-vertical .sidebar-user-text {
                display: none;
            }
            .sidebar-folded .navbar-vertical .navbar-brand,
            .sidebar-folded .navbar-vertical .nav-link,
            .sidebar-folded .navbar-vertical .sidebar-user {
                justify-content: center;
                text-align: center;
            }
            .sidebar-folded .navbar-vertical .nav-link-icon {
                margin-right: 0;
            }
            /* Les titres de section deviennent un simple trait séparateur */
            .sidebar-folded .navbar-vertical .subheader {
                font-size: 0;
                height: 1px;
                padding: 0 !important;
                margin: .75rem 1.25rem;
                background: var(--tblr-border-color);
            }
                        /* Le compte reste en bas, seuls les liens défilent (comme dans le template) */
            .navbar-vertical.navbar-expand-lg {
                overflow: visible;
            }
            .navbar-vertical.navbar-expand-lg .navbar-collapse {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                height: calc(100vh - 4.5rem);
            }
            .navbar-vertical.navbar-expand-lg .navbar-nav {
                flex: 1 1 auto;
                flex-wrap: nowrap;
                overflow-y: auto;
            }
            .navbar-vertical .sidebar-user {
                margin-top: auto;
            }
            .navbar-vertical .sidebar-user .dropdown-menu {
                min-width: 13rem;
            }

        }
    </style>
    @stack('styles')
</head>
<body>
<div class="page">
    @include('admin.partials.sidebar')

    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    {{-- Bouton pour réduire / agrandir le menu (grand écran uniquement) --}}
                    <div class="col-auto d-none d-lg-block">
                        <button type="button" id="toggle-sidebar" class="btn btn-icon btn-ghost-secondary"
                                title="Réduire / agrandir le menu">
                            <i class="ti ti-layout-sidebar-left-collapse"></i>
                        </button>
                    </div>
                    <div class="col">
                        <div class="page-pretitle">@yield('pretitle', 'AquaSecure')</div>
                        <h2 class="page-title">@yield('title', 'Administration')</h2>
                        @hasSection('subtitle')
                        <div class="text-secondary mt-1">@yield('subtitle')</div>
                        @endif
                    </div>
                    <div class="col-auto ms-auto">
                        @yield('actions')
                    </div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @yield('content')
            </div>
        </div>

        @include('admin.partials.footer')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1/dist/js/tabler.min.js"></script>
<script>
    // Réduire / agrandir le menu et mémoriser le choix dans le navigateur
    (function () {
        const html = document.documentElement;
        const bouton = document.getElementById('toggle-sidebar');
        const icone = bouton?.querySelector('i');

        function majIcone() {
            if (!icone) return;
            const reduit = html.classList.contains('sidebar-folded');
            icone.className = reduit ? 'ti ti-layout-sidebar-left-expand' : 'ti ti-layout-sidebar-left-collapse';
        }

        bouton?.addEventListener('click', function () {
            html.classList.toggle('sidebar-folded');
            try {
                localStorage.setItem('sidebar-folded', html.classList.contains('sidebar-folded') ? '1' : '0');
            } catch (e) {}
            majIcone();
            // Les cartes Leaflet doivent recalculer leur taille
            setTimeout(() => window.dispatchEvent(new Event('resize')), 250);
        });

        majIcone();
    })();
</script>
<script src="{{ asset('js/validation.js') }}"></script>
{{-- Fenêtre de confirmation partagée (suppression, etc.) --}}
@include('admin.partials.confirm-modal')
@stack('scripts')
</body>
</html>