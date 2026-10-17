@extends('layouts.admin')

@section('title', 'Mon profil')
@section('pretitle', 'Mon compte')

@section('actions')
    <a href="{{ route('admin.parametres') }}" class="btn btn-primary">
        <i class="ti ti-settings me-1"></i> Paramètres
    </a>
@endsection

@section('content')
<div class="row row-cards">
    {{-- Carte d'identité, même style que la page Utilisateurs --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body p-4 text-center">
                <x-avatar :user="$user" taille="xl" class="mb-3" />                <h3 class="m-0 mb-1">{{ $user->nom }}</h3>
                <div class="text-secondary">{{ $user->email }}</div>
                <div class="mt-3">
                    <span class="badge bg-{{ $user->couleurRole() }}-lt">{{ $user->libelleRole() }}</span>
                </div>
            </div>
            <div class="d-flex">
                <a href="{{ route('admin.parametres') }}" class="card-btn">
                    <i class="ti ti-edit me-2"></i> Modifier
                </a>
                <a href="{{ route('admin.parametres.password') }}" class="card-btn">
                    <i class="ti ti-lock me-2"></i> Mot de passe
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Informations du compte</h3></div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Nom complet</div>
                        <div class="datagrid-content">{{ $user->nom }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Email</div>
                        <div class="datagrid-content">{{ $user->email }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Rôle</div>
                        <div class="datagrid-content">
                            <span class="status status-{{ $user->couleurRole() }}">{{ $user->libelleRole() }}</span>
                        </div>
                    </div>
                                            <div class="datagrid-item">
                            <div class="datagrid-title">Email vérifié</div>
                            <div class="datagrid-content">
                                @if ($user->hasVerifiedEmail())
                                    <span class="status status-green">Oui, le {{ $user->email_verified_at->format('d/m/Y') }}</span>
                                @else
                                    <span class="status status-yellow">Non</span>
                                @endif
                            </div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Double authentification</div>
                            <div class="datagrid-content">
                                @if ($user->aDoubleAuthentification())
                                    <span class="status status-green">Activée</span>
                                @else
                                    <a href="{{ route('admin.parametres.securite') }}" class="status status-secondary">Désactivée</a>
                                @endif
                            </div>
                        </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Membre depuis</div>
                        <div class="datagrid-content">{{ $user->created_at?->format('d/m/Y') }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Dernière modification</div>
                        <div class="datagrid-content">{{ $user->updated_at?->diffForHumans() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Droits d'accès</h3></div>
            <div class="list-group list-group-flush">
                <div class="list-group-item d-flex align-items-center">
                    <i class="ti ti-circle-check text-success me-2"></i> Tableau de bord et modules métier
                </div>
                <div class="list-group-item d-flex align-items-center">
                    @if ($user->isAdmin())
                        <i class="ti ti-circle-check text-success me-2"></i> Gestion des utilisateurs
                    @else
                        <i class="ti ti-circle-x text-secondary me-2"></i>
                        <span class="text-secondary">Gestion des utilisateurs (réservée aux administrateurs)</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection