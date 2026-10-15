<div class="card-body">
    <h4 class="subheader">Paramètres du compte</h4>
    <div class="list-group list-group-transparent">
        <a href="{{ route('admin.parametres') }}"
           class="list-group-item list-group-item-action d-flex align-items-center {{ request()->routeIs('admin.parametres') ? 'active' : '' }}">
            <i class="ti ti-user-circle me-2"></i> Mon compte
        </a>
        <a href="{{ route('admin.parametres.password') }}"
           class="list-group-item list-group-item-action d-flex align-items-center {{ request()->routeIs('admin.parametres.password') ? 'active' : '' }}">
            <i class="ti ti-lock me-2"></i> Mot de passe
        </a>
    </div>

    <h4 class="subheader mt-4">Raccourcis</h4>
    <div class="list-group list-group-transparent">
        <a href="{{ route('admin.profil') }}" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="ti ti-id me-2"></i> Voir mon profil
        </a>
        <a href="{{ route('home') }}" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="ti ti-world me-2"></i> Voir le site public
        </a>
    </div>
</div>