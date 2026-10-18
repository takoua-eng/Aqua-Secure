@extends('layouts.admin')

@section('title', 'Modifier l\'intervention n° ' . $intervention->id)
@section('pretitle', 'Exploitation · Incident n° ' . $incident->id)

@section('actions')
    <a href="{{ route('admin.interventions.show', $intervention) }}" class="btn btn-link">
        <i class="ti ti-arrow-left me-1"></i> Retour au suivi
    </a>
@endsection

@section('content')
    @include('admin.incidents._navigation')
    @include('admin.interventions._incident')

    @php
        $aujourdhui = today()->format('Y-m-d');
        $signalement = $incident->date_signalement->format('Y-m-d');
        $planifiee = $intervention->statut === 'planifiee';
        $aide = $planifiee
            ? "Reporter ou avancer l'intervention : aujourd'hui ou plus tard."
            : 'Entre le signalement (' . $incident->date_signalement->format('d/m/Y') . ") et aujourd'hui.";
    @endphp

    <form method="POST" action="{{ route('admin.interventions.update', $intervention) }}" class="card" data-validate>
        @csrf
        @method('PUT')

        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger"><i class="ti ti-alert-circle me-1"></i> {{ $errors->has('regle') ? $errors->first('regle') : 'Veuillez corriger les champs en rouge.' }}</div>
            @endif

            <div class="mb-3">
                <label class="form-label">Statut actuel</label>
                <div>
                    <span class="badge bg-{{ $intervention->couleurStatut() }}-lt fs-4"><i class="ti ti-{{ $intervention->iconeStatut() }} me-1"></i>{{ $intervention->libelleStatut() }}</span>
                </div>
                <small class="form-hint">Le statut se change depuis la page de suivi (bouton « Changer le statut »), pour que chaque changement soit contrôlé et historisé.</small>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <x-input name="date_intervention" type="date" :label="$planifiee ? 'Date prévue' : 'Date de début'" icon="calendar"
                             :value="$intervention->date_intervention->format('Y-m-d')" required
                             min="{{ $planifiee ? max($aujourdhui, $signalement) : $signalement }}"
                             max="{{ $planifiee ? '' : $aujourdhui }}"
                             :hint="$aide"
                             data-msg-required="La date d'intervention est obligatoire."
                             data-msg-min="{{ $planifiee ? 'Une intervention planifiée doit avoir une date à venir.' : 'La date ne peut pas précéder le signalement.' }}"
                             data-msg-max="Une intervention en cours ne peut pas être datée dans le futur."
                             valid-message="Date valide." />
                </div>
            </div>
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.interventions.show', $intervention) }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="primary"
                    data-confirm-title="Enregistrer la nouvelle date ?"
                    data-confirm-message="La date de l'intervention n° {{ $intervention->id }} sera modifiée."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection
