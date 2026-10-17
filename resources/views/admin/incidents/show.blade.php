@extends('layouts.admin')

@section('title', 'Incident n° ' . $incident->id . ' · ' . $incident->libelleType())
@section('pretitle', 'Exploitation · Incident')

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.incidents.index') }}" class="btn btn-link">
            <i class="ti ti-arrow-left me-1"></i> Retour à la liste
        </a>
        <a href="{{ route('admin.incidents.edit', $incident) }}" class="btn">
            <i class="ti ti-edit me-1"></i> Modifier
        </a>
        @can('delete', $incident)
            @if ($incident->interventions->isEmpty())
                <form method="POST" action="{{ route('admin.incidents.destroy', $incident) }}">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-ghost-danger"
                            data-bs-toggle="modal" data-bs-target="#modal-confirm"
                            data-confirm-type="danger"
                            data-confirm-title="Supprimer l'incident n° {{ $incident->id }} ?"
                            data-confirm-message="Cet incident n'a aucune intervention. Sa suppression est définitive."
                            data-confirm-button="Oui, supprimer">
                        <i class="ti ti-trash me-1"></i> Supprimer
                    </button>
                </form>
            @endif
        @endcan
        <a href="{{ route('admin.incidents.interventions.create', $incident) }}" class="btn btn-primary">
            <i class="ti ti-tool me-1"></i> Nouvelle intervention
        </a>
    </div>
@endsection

@section('content')
@php
    $statut = $incident->statutActuel();
@endphp

@include('admin.incidents._navigation')

@if (session('avertissement'))
    <div class="alert alert-warning"><i class="ti ti-copy me-1"></i> {{ session('avertissement') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger"><i class="ti ti-alert-circle me-1"></i> {{ $errors->first() }}</div>
@endif

@if ($incident->estEnAlerteCritique())
    <div class="alert alert-danger">
        <div class="d-flex align-items-center">
            <i class="ti ti-urgent me-2" style="font-size: 1.5rem"></i>
            <div class="flex-fill">
                <strong>Incident critique non pris en charge</strong> depuis {{ $incident->joursDepuisSignalement() }} jour{{ $incident->joursDepuisSignalement() > 1 ? 's' : '' }} :
                aucune intervention n'est planifiée.
            </div>
            <a href="{{ route('admin.incidents.interventions.create', $incident) }}" class="btn btn-danger ms-2">Planifier maintenant</a>
        </div>
    </div>
@endif

<div class="row row-deck row-cards">

    {{-- Informations --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-info-circle me-1"></i> Informations</h3>
                <div class="card-actions">
                    <span class="badge bg-{{ $incident->couleurStatut() }}-lt">{{ \App\Models\Incident::STATUTS[$statut] }}</span>
                </div>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-secondary">N°</dt>
                    <dd class="col-7 font-monospace">#{{ $incident->id }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Type</dt>
                    <dd class="col-7"><i class="ti ti-{{ $incident->iconeType() }} me-1"></i>{{ $incident->libelleType() }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Gravité</dt>
                    <dd class="col-7"><span class="badge bg-{{ $incident->couleurGravite() }}-lt">{{ $incident->libelleGravite() }}</span></dd>
                    <dt class="col-5 fw-normal text-secondary">Signalé le</dt>
                    <dd class="col-7">
                        {{ $incident->date_signalement->format('d/m/Y') }}
                        <div class="small text-secondary">il y a {{ $incident->joursDepuisSignalement() }} jour{{ $incident->joursDepuisSignalement() > 1 ? 's' : '' }}</div>
                    </dd>
                    <dt class="col-5 fw-normal text-secondary">Réseau</dt>
                    <dd class="col-7">
                        <span class="font-monospace">{{ $incident->reseau->code }}</span>
                        <div class="small text-secondary">{{ $incident->reseau->libelleType() }}</div>
                    </dd>
                    <dt class="col-5 fw-normal text-secondary">État du réseau</dt>
                    <dd class="col-7"><span class="badge bg-{{ $incident->reseau->couleurEtat() }}-lt">{{ $incident->reseau->libelleEtat() }}</span></dd>
                    <dt class="col-5 fw-normal text-secondary">Zone</dt>
                    <dd class="col-7 mb-0">{{ $incident->reseau->zone->libelle() }}</dd>
                </dl>
            </div>
            <div class="card-footer small text-secondary">
                <i class="ti ti-info-circle me-1"></i>
                @switch($statut)
                    @case('non_traite') Statut calculé : aucune intervention n'a encore été enregistrée. @break
                    @case('en_traitement') Statut calculé : au moins une intervention est planifiée ou en cours. @break
                    @default Statut calculé : toutes les interventions sont terminées.
                @endswitch
            </div>
        </div>
    </div>

    {{-- Priorité (moteur de règles explicable) --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i class="ti ti-list-numbers me-1"></i> Priorité de traitement</h3>
                    <div class="card-subtitle">Score sur 100 calculé par des règles métier explicables (pas un modèle d'apprentissage).</div>
                </div>
            </div>
            <div class="card-body">
                @if ($priorite['resolu'])
                    <div class="empty py-3">
                        <div class="empty-icon"><i class="ti ti-circle-check text-green" style="font-size: 3rem"></i></div>
                        <p class="empty-title">Incident résolu</p>
                        <p class="empty-subtitle text-secondary">Toutes les interventions sont terminées : l'incident sort du classement des priorités.</p>
                    </div>
                @else
                    <div class="row align-items-center g-4">
                        <div class="col-md-4 text-center">
                            <div class="display-5 fw-bold text-{{ $priorite['niveau']['couleur'] }}" style="font-variant-numeric: tabular-nums">
                                {{ $priorite['score'] }}<span class="fs-3 text-secondary fw-normal">/100</span>
                            </div>
                            <span class="badge bg-{{ $priorite['niveau']['couleur'] }} text-{{ $priorite['niveau']['couleur'] }}-fg fs-4 mt-1">
                                Priorité {{ mb_strtolower($priorite['niveau']['libelle']) }}
                            </span>
                        </div>
                        <div class="col-md-8">
                            @include('admin.incidents._facteurs')
                        </div>
                    </div>
                    <div class="alert alert-info mt-4 mb-0">
                        <div class="d-flex">
                            <i class="ti ti-bulb me-2" style="font-size: 1.25rem"></i>
                            <div><strong>Action recommandée :</strong> {{ $priorite['action'] }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Interventions --}}
    <div class="col-12" id="interventions">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-tool me-1"></i> Interventions <span class="text-secondary fw-normal">· {{ $incident->interventions->count() }}</span></h3>
                <div class="card-actions">
                    <a href="{{ route('admin.incidents.interventions.create', $incident) }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-plus me-1"></i> Nouvelle intervention
                    </a>
                </div>
            </div>
            @if ($incident->interventions->isEmpty())
                <div class="card-body">
                    <div class="empty">
                        <div class="empty-icon"><i class="ti ti-tool" style="font-size: 3rem"></i></div>
                        <p class="empty-title">Aucune intervention pour cet incident</p>
                        <p class="empty-subtitle text-secondary">Planifiez une intervention de réparation ou de réponse.</p>
                        <div class="empty-action">
                            <a href="{{ route('admin.incidents.interventions.create', $incident) }}" class="btn btn-primary">
                                <i class="ti ti-calendar-plus me-1"></i> Planifier une intervention
                            </a>
                        </div>
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th class="w-1">N°</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Créée le</th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($incident->interventions as $intervention)
                                <tr>
                                    <td><a href="{{ route('admin.interventions.show', $intervention) }}" class="font-monospace">#{{ $intervention->id }}</a></td>
                                    <td>
                                        {{ $intervention->date_intervention->format('d/m/Y') }}
                                        <div class="small text-secondary">{{ $intervention->statut === 'planifiee' ? 'date prévue' : "date d'exécution" }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $intervention->couleurStatut() }}-lt"><i class="ti ti-{{ $intervention->iconeStatut() }} me-1"></i>{{ $intervention->libelleStatut() }}</span>
                                        @if ($intervention->estEnRetard())
                                            <span class="badge bg-orange-lt"><i class="ti ti-clock-exclamation me-1"></i>En retard</span>
                                        @endif
                                    </td>
                                    <td class="text-secondary">{{ $intervention->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('admin.interventions.show', $intervention) }}" class="btn btn-sm">
                                            <i class="ti ti-eye me-1"></i> Suivi
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Règle I6 : incidents similaires non résolus --}}
    @if ($doublons->isNotEmpty())
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-copy me-1 text-warning"></i> Incidents similaires non résolus</h3>
                    <div class="card-subtitle ms-2">Même type, même réseau, à moins de {{ \App\Models\Incident::JOURS_DOUBLON }} jours : doublon possible.</div>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($doublons as $doublon)
                        <a href="{{ route('admin.incidents.show', $doublon) }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <span class="font-monospace me-3">#{{ $doublon->id }}</span>
                            <span class="me-3">{{ $doublon->libelleType() }} · {{ $doublon->reseau->code }}</span>
                            <span class="badge bg-{{ $doublon->couleurGravite() }}-lt me-3">{{ $doublon->libelleGravite() }}</span>
                            <span class="text-secondary ms-auto">signalé le {{ $doublon->date_signalement->format('d/m/Y') }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
