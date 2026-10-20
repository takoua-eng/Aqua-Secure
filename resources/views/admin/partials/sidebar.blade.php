@php
    // Menu de la barre latérale : section => [ [route, icône Tabler, libellé], ... ]
    $menu = [
        '' => [
            ['admin.dashboard', 'home', 'Tableau de bord'],
        ],
        'Infrastructure' => [
            ['admin.zones.index', 'map', 'Zones'],
            ['admin.reseaux.index', 'share', 'Réseaux'],
            ['admin.coupures.index', 'calendar-off', 'Coupures programmées'],
            ['admin.risques.index', 'brain', 'Analyse des risques'],
        ],
        'Exploitation' => [
            ['admin.incidents.index', 'alert-triangle', 'Incidents'],
            ['admin.interventions.index', 'tool', 'Interventions'],
        ],
        'Surveillance' => [
            ['admin.capteurs.index', 'wifi', 'Capteurs'],
            ['admin.releves.index', 'chart-bar', 'Relevés'],
        ],
        "Qualité de l'eau" => [
            ['admin.prelevements.index', 'droplet', 'Points de prélèvement'],
            ['admin.analyses.index', 'flask', 'Analyses'],
            ['admin.detection.index', 'virus-search', 'Détection de contamination'],
        ],
        'Rénovation' => [
            ['admin.projets.index', 'building', 'Projets de rénovation'],
        ],
    ];

    // Section visible uniquement par l'administrateur
    if (auth()->user()?->isAdmin()) {
        $menu['Administration'] = [
            ['admin.users.index', 'users', 'Utilisateurs'],
        ];
    }

    $moi = auth()->user();
@endphp

<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="light">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
            <span class="navbar-toggler-icon"></span>
        </button>

    <a href="{{ route('admin.dashboard') }}"
        class="navbar-brand navbar-brand-autodark d-flex align-items-center justify-content-center gap-2 w-100">
        <img src="{{ asset('favicon.svg') }}" alt="" width="28" height="28">
        <span class="brand-text">AquaSecure</span>
    </a>

        <div class="collapse navbar-collapse" id="sidebar-menu">
            <ul class="navbar-nav pt-lg-3">
                @foreach ($menu as $section => $liens)
                    @if ($section !== '')
                        <li class="nav-item">
                            <div class="subheader px-3 pt-3 pb-1">{{ $section }}</div>
                        </li>
                    @endif

                    @foreach ($liens as [$route, $icone, $libelle])
                        @php
                            $prefixe = \Illuminate\Support\Str::beforeLast($route, '.');
                            $actif = request()->routeIs($route) || request()->routeIs($prefixe . '.*');
                        @endphp
                        <li class="nav-item {{ $actif ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route($route) }}" title="{{ $libelle }}">
                                <span class="nav-link-icon"><i class="ti ti-{{ $icone }}"></i></span>
                                <span class="nav-link-title">{{ $libelle }}</span>
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>

            {{-- Compte connecté : un clic ouvre le menu vers le haut (comme dans le template) --}}
            <div class="sidebar-user border-top p-2 dropup">
                <a href="#" class="d-flex align-items-center gap-2 p-2 rounded text-reset text-decoration-none"
                   data-bs-toggle="dropdown" aria-expanded="false" title="{{ $moi->nom }}">
                    <x-avatar :user="$moi" taille="sm" />
                    <div class="sidebar-user-text lh-sm">
                        <div class="fw-medium">{{ $moi->nom }}</div>
                        <div class="small text-secondary">{{ $moi->libelleRole() }}</div>
                    </div>
                    <i class="ti ti-selector ms-auto text-secondary sidebar-user-text"></i>
                </a>

                <div class="dropdown-menu">
                    <a href="{{ route('admin.profil') }}" class="dropdown-item">
                        <i class="ti ti-user me-2"></i> Mon profil
                    </a>
                    <a href="{{ route('admin.parametres') }}" class="dropdown-item">
                        <i class="ti ti-settings me-2"></i> Paramètres
                    </a>
                    <a href="{{ route('admin.parametres.password') }}" class="dropdown-item">
                        <i class="ti ti-lock me-2"></i> Mot de passe
                    </a>
                                            <a href="{{ route('admin.parametres.securite') }}" class="dropdown-item">
                            <i class="ti ti-shield-lock me-2"></i> Sécurité (2FA)
                        </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('home') }}" class="dropdown-item" target="_blank">
                        <i class="ti ti-world me-2"></i> Voir le site public
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="ti ti-logout me-2"></i> Se déconnecter
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</aside>