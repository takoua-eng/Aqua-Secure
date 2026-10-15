<header class="navbar navbar-expand-md d-none d-lg-flex d-print-none">
    <div class="container-xl">
        <div class="navbar-nav flex-row order-md-last ms-auto">
            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown">
                    <span class="avatar avatar-sm bg-azure-lt">
                        {{ strtoupper(substr(auth()->user()->name ?? 'G', 0, 1)) }}
                    </span>
                    <div class="d-none d-xl-block ps-2">
                        <div>{{ auth()->user()->name ?? 'Gestionnaire' }}</div>
                        <div class="mt-1 small text-secondary">Gestionnaire</div>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="ti ti-logout me-2"></i> Déconnexion
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
