@extends('layouts.admin')

@section('title', 'Utilisateurs')
@section('pretitle', 'Administration')
@section('subtitle')
    {{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }} sur {{ $users->total() }} personnes
@endsection

{{-- Recherche + bouton, en haut à droite comme dans le modèle Tabler --}}
@section('actions')
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <select name="role" class="form-select" onchange="this.form.submit()">
                <option value="">Tous les rôles</option>
                @foreach (\App\Models\User::ROLES as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(request('role') === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Rechercher…">
        </form>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Nouvel utilisateur
        </a>
    </div>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @php
        // Couleur de l'avatar et du badge selon le rôle
        $couleurs = ['admin' => 'red', 'gestionnaire' => 'blue', 'citoyen' => 'green'];
    @endphp

    <div class="row row-cards">
        @forelse ($users as $user)
            @php
                // Initiales : premières lettres des 2 premiers mots du nom
                $initiales = collect(explode(' ', $user->nom))
                    ->filter()
                    ->take(2)
                    ->map(fn ($mot) => mb_strtoupper(mb_substr($mot, 0, 1)))
                    ->implode('');
                $couleur = $couleurs[$user->role] ?? 'secondary';
            @endphp

            <div class="col-md-6 col-lg-3">
                <div class="card">
                    <div class="card-body p-4 text-center">
                        <x-avatar :user="$user" taille="xl" class="mb-3" />
                        <h3 class="m-0 mb-1">
                            <a href="{{ route('admin.users.edit', $user) }}">{{ $user->nom }}</a>
                        </h3>
                        <div class="text-secondary">{{ $user->email }}</div>
                        <div class="mt-3">
                            <span class="badge bg-{{ $couleur }}-lt">{{ $user->libelleRole() }}</span>
                            @if ($user->is(auth()->user()))
                                <span class="badge bg-secondary-lt">Vous</span>
                            @endif
                        </div>
                    </div>

                    {{-- Les 2 boutons en bas de carte (classe Tabler "card-btn") --}}
                    <div class="d-flex">
                        <a href="{{ route('admin.users.edit', $user) }}" class="card-btn">
                            <i class="ti ti-edit me-2"></i> Modifier
                        </a>

                        @if ($user->is(auth()->user()))
                            {{-- Pas de suppression de son propre compte : bouton Email à la place --}}
                            <a href="mailto:{{ $user->email }}" class="card-btn">
                                <i class="ti ti-mail me-2"></i> Email
                            </a>
                        @else
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-flex flex-fill">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="card-btn text-danger border-0 bg-transparent w-100"
                                        data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                        data-confirm-type="danger"
                                        data-confirm-title="Supprimer cet utilisateur ?"
                                        data-confirm-message="Le compte de {{ $user->nom }} ({{ $user->email }}) sera définitivement supprimé."
                                        data-confirm-button="Oui, supprimer">
                                    <i class="ti ti-trash me-2"></i> Supprimer
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="empty">
                    <div class="empty-icon"><i class="ti ti-users" style="font-size: 3rem"></i></div>
                    <p class="empty-title">Aucun utilisateur trouvé</p>
                    <p class="empty-subtitle text-secondary">Modifiez la recherche ou le filtre de rôle.</p>
                </div>
            </div>
        @endforelse
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $users->links() }}
    </div>
@endsection