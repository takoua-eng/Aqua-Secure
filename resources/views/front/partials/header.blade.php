<nav class="navbar navbar-expand-lg navbar-dark navbar-eau sticky-top">
    <div class="container">
        <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="{{ route('home') }}">
            <img src="{{ asset('favicon.svg') }}" alt="" width="30" height="30"> AquaSecure
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('renovation.*') ? 'active' : '' }}" href="{{ route('renovation.index') }}">Rénovation</a>
                </li>
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#carte">Carte</a></li>
                @auth
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('qualite.*') ? 'active' : '' }}" href="{{ route('qualite.index') }}">Qualité de l'eau</a>
                    </li>
                                        <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('surveillance.*') ? 'active' : '' }}" href="{{ route('surveillance.index') }}">Surveillance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('incidents.*') ? 'active' : '' }}" href="{{ route('incidents.index') }}">Incidents</a>
                    </li>
                @endauth

                {{-- Visiteur non connecté --}}
                @guest
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Connexion</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">Inscription</a></li>
                @endguest

                {{-- Utilisateur connecté --}}
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                            @if (auth()->user()->photo)
                                <img src="{{ auth()->user()->photoUrl() }}" alt="" class="rounded-circle"
                                     style="width: 28px; height: 28px; object-fit: cover">
                            @else
                                <i class="bi bi-person-circle"></i>
                            @endif
                            {{ auth()->user()->nom }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if (auth()->user()->peutAccederBackOffice())
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Espace de gestion</a></li>
                                <li><a class="dropdown-item" href="{{ route('admin.parametres') }}">Paramètres du compte</a></li>
                            @else
                                <li><a class="dropdown-item" href="{{ route('compte') }}"><i class="bi bi-person-gear me-1"></i> Mon compte</a></li>
                                <li><a class="dropdown-item" href="{{ route('compte.securite') }}"><i class="bi bi-shield-lock me-1"></i> Sécurité (2FA)</a></li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Déconnexion</button>
                                </form>
                            </li>
                        </ul>
                    </li>

                    {{-- Bouton visible UNIQUEMENT pour un citoyen connecté --}}
                    @if (auth()->user()->isCitoyen())
                        <li class="nav-item ms-lg-3">
                            <a class="btn btn-light btn-sm px-3" href="{{ route('signalement.create') }}">
                                <i class="bi bi-exclamation-triangle"></i> Signaler un incident
                            </a>
                        </li>
                    @endif
                @endauth
            </ul>
        </div>
    </div>
</nav>