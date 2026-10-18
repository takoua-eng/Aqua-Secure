@extends('layouts.admin')

@section('title', 'Intervention n° ' . $intervention->id)
@section('pretitle', 'Exploitation · Suivi d\'intervention')

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.incidents.show', $intervention->incident) }}" class="btn btn-link">
            <i class="ti ti-arrow-left me-1"></i> Retour à l'incident
        </a>
        @if ($intervention->estModifiable())
            <a href="{{ route('admin.interventions.edit', $intervention) }}" class="btn">
                <i class="ti ti-edit me-1"></i> Modifier la date
            </a>
        @endif
        @can('delete', $intervention)
            @if ($intervention->peutEtreSupprimee())
                <form method="POST" action="{{ route('admin.interventions.destroy', $intervention) }}">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-ghost-danger"
                            data-bs-toggle="modal" data-bs-target="#modal-confirm"
                            data-confirm-type="danger"
                            data-confirm-title="Annuler et supprimer cette intervention ?"
                            data-confirm-message="L'intervention planifiée n° {{ $intervention->id }} sera définitivement supprimée."
                            data-confirm-button="Oui, supprimer">
                        <i class="ti ti-trash me-1"></i> Supprimer
                    </button>
                </form>
            @endif
        @endcan
    </div>
@endsection

@section('content')
@include('admin.incidents._navigation')

@if ($errors->any())
    <div class="alert alert-danger"><i class="ti ti-alert-circle me-1"></i> {{ $errors->first() }}</div>
@endif

@if ($intervention->estEnRetard())
    <div class="alert alert-warning">
        <i class="ti ti-clock-exclamation me-1"></i>
        Intervention prévue le {{ $intervention->date_intervention->format('d/m/Y') }} et toujours pas démarrée :
        démarrez-la, ou <a href="{{ route('admin.interventions.edit', $intervention) }}" class="alert-link">replanifiez-la</a>.
    </div>
@endif

@include('admin.interventions._incident', ['incident' => $intervention->incident])

<div class="row row-deck row-cards">

    {{-- Informations --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-info-circle me-1"></i> Informations</h3></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-secondary">N°</dt>
                    <dd class="col-7 font-monospace">#{{ $intervention->id }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Statut</dt>
                    <dd class="col-7"><span class="badge bg-{{ $intervention->couleurStatut() }}-lt"><i class="ti ti-{{ $intervention->iconeStatut() }} me-1"></i>{{ $intervention->libelleStatut() }}</span></dd>
                    <dt class="col-5 fw-normal text-secondary">{{ $intervention->statut === 'planifiee' ? 'Date prévue' : "Date d'exécution" }}</dt>
                    <dd class="col-7">{{ $intervention->date_intervention->format('d/m/Y') }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Créée le</dt>
                    <dd class="col-7">{{ $intervention->created_at?->format('d/m/Y H:i') }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Modifiable</dt>
                    <dd class="col-7 mb-0">{{ $intervention->estModifiable() ? 'Oui' : 'Non (terminée)' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Changement de statut (règle T5) --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-arrows-right-left me-1"></i> Changer le statut</h3></div>
            <div class="card-body">
                {{-- Schéma des transitions --}}
                <div class="d-flex align-items-center justify-content-between mb-3 small">
                    @foreach (\App\Models\Intervention::STATUTS as $valeur => $libelle)
                        <span class="badge {{ $intervention->statut === $valeur ? 'bg-' . \App\Models\Intervention::couleurStatutDe($valeur) . ' text-' . \App\Models\Intervention::couleurStatutDe($valeur) . '-fg' : 'bg-secondary-lt' }}">{{ $libelle }}</span>
                        @unless ($loop->last)<i class="ti ti-arrow-right text-secondary"></i>@endunless
                    @endforeach
                </div>

                @if (empty($intervention->statutsSuivants()))
                    <div class="text-secondary"><i class="ti ti-lock me-1"></i> Intervention terminée : son statut est définitif.</div>
                @else
                    <div class="d-flex flex-column gap-2">
                        @foreach ($intervention->statutsSuivants() as $suivant)
                            <form method="POST" action="{{ route('admin.interventions.statut', $intervention) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="statut" value="{{ $suivant }}">
                                <button type="button" class="btn w-100 {{ $suivant === 'terminee' ? 'btn-success' : 'btn-primary' }}"
                                        data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                        data-confirm-type="{{ $suivant === 'terminee' ? 'success' : 'primary' }}"
                                        data-confirm-title="Passer à « {{ \App\Models\Intervention::libelleStatutDe($suivant) }} » ?"
                                        data-confirm-message="{{ $suivant === 'terminee' ? 'Une intervention terminée ne peut plus être modifiée.' : 'Le changement sera enregistré dans l\'historique.' }}{{ $intervention->date_intervention->gt(today()) ? ' La date prévue étant dans le futur, elle sera remplacée par la date du jour.' : '' }}"
                                        data-confirm-button="Confirmer">
                                    <i class="ti ti-{{ $suivant === 'terminee' ? 'circle-check' : 'player-play' }} me-1"></i>
                                    {{ $suivant === 'terminee' ? 'Marquer comme terminée' : 'Démarrer l\'intervention' }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                    <small class="form-hint d-block mt-2">Pas de retour en arrière : pour reporter, modifiez la date tant que l'intervention est planifiée.</small>
                @endif
            </div>
        </div>
    </div>

    {{-- Historique des statuts --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-history me-1"></i> Historique</h3></div>
            <div class="list-group list-group-flush">
                @forelse ($intervention->historiques as $ligne)
                    <div class="list-group-item">
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            @if ($ligne->ancien_statut)
                                <span class="badge bg-secondary-lt">{{ \App\Models\Intervention::libelleStatutDe($ligne->ancien_statut) }}</span>
                                <i class="ti ti-arrow-right text-secondary"></i>
                            @else
                                <span class="text-secondary small me-1">Création :</span>
                            @endif
                            <span class="badge bg-{{ \App\Models\Intervention::couleurStatutDe($ligne->nouveau_statut) }}-lt">{{ \App\Models\Intervention::libelleStatutDe($ligne->nouveau_statut) }}</span>
                        </div>
                        <div class="small text-secondary mt-1">
                            {{ $ligne->created_at?->format('d/m/Y à H:i') }} · {{ $ligne->user?->nom ?? 'Système' }}
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-secondary">Aucun changement enregistré.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
