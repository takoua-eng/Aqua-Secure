@extends('layouts.admin')

@section('title', 'Financement de ' . number_format((float) $financement->montant, 2, ',', ' ') . ' DT')
@section('pretitle', 'Rénovation · Financement')

@section('actions')
    <div class="btn-list">
        @can('update', $financement)
            <a href="{{ route('admin.financements.edit', $financement) }}" class="btn"><i class="ti ti-edit me-1"></i> Modifier</a>
        @endcan
        <a href="{{ route('admin.projets.show', $financement->projet_id) }}" class="btn btn-primary"><i class="ti ti-building me-1"></i> Voir le projet</a>
    </div>
@endsection

@section('content')
    @include('admin.renovation._nav')

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Détail</h3>
                    <div class="card-actions"><x-renovation.origine type="enregistre" /></div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Montant</dt>
                        <dd class="col-7 h3 mb-2"><x-renovation.montant :valeur="$financement->montant" /></dd>
                        <dt class="col-5">Source</dt>
                        <dd class="col-7">
                            <span class="badge bg-{{ \App\Models\Financement::couleurSourceDe($financement->source) }}-lt">
                                <i class="ti ti-{{ \App\Models\Financement::iconeSourceDe($financement->source) }} me-1"></i>{{ $financement->libelleSource() }}
                            </span>
                        </dd>
                        <dt class="col-5">Projet</dt>
                        <dd class="col-7"><a href="{{ route('admin.projets.show', $financement->projet_id) }}">{{ $financement->projet?->titre }}</a></dd>
                        <dt class="col-5">Réseau</dt>
                        <dd class="col-7 font-monospace">{{ $financement->projet?->reseau?->code }}</dd>
                        <dt class="col-5">Enregistré le</dt>
                        <dd class="col-7">{{ $financement->created_at->format('d/m/Y à H:i') }}</dd>
                        <dt class="col-5">Dernière modification</dt>
                        <dd class="col-7">{{ $financement->updated_at->format('d/m/Y à H:i') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-route me-1"></i> Origine</h3></div>
                <div class="card-body">
                    @if ($financement->simulation)
                        <p>Créé automatiquement lors de l'application du scénario What If?
                            <a href="{{ route('admin.simulations.show', $financement->simulation) }}">« {{ $financement->simulation->nom }} »</a>.</p>
                        <p class="small text-secondary mb-0">
                            Décision : {{ $financement->simulation->decideur?->nom ?? '—' }},
                            le {{ $financement->simulation->decide_le?->format('d/m/Y') }}.
                            Seul un administrateur peut modifier ou supprimer ce financement.
                        </p>
                    @else
                        <p class="mb-0">Saisie manuelle par un gestionnaire.</p>
                    @endif
                </div>
            </div>

            @can('delete', $financement)
                <form method="POST" action="{{ route('admin.financements.destroy', $financement) }}" class="card card-body">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-outline-danger w-100"
                            data-bs-toggle="modal" data-bs-target="#modal-confirm"
                            data-confirm-type="danger"
                            data-confirm-title="Supprimer ce financement ?"
                            data-confirm-message="Le financement sera définitivement supprimé."
                            data-confirm-button="Oui, supprimer">
                        <i class="ti ti-trash me-1"></i> Supprimer le financement
                    </button>
                </form>
            @endcan
        </div>
    </div>
@endsection
