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
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#projets">Projets</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#carte">Carte</a></li>
                @auth
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('qualite.*') ? 'active' : '' }}" href="{{ route('qualite.index') }}">Qualité de l'eau</a>
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
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> {{ auth()->user()->nom }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if (auth()->user()->peutAccederBackOffice())
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Espace de gestion</a></li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
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