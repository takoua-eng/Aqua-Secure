@extends('layouts.admin')

@section('title', 'Modifier l\'incident n° ' . $incident->id)
@section('pretitle', 'Exploitation')

@section('actions')
    <a href="{{ route('admin.incidents.show', $incident) }}" class="btn btn-link">
        <i class="ti ti-arrow-left me-1"></i> Retour à la fiche
    </a>
@endsection

@section('content')
    @include('admin.incidents._navigation')

    <form method="POST" action="{{ route('admin.incidents.update', $incident) }}" class="card" data-validate>
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('admin.incidents._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.incidents.show', $incident) }}" class="btn btn-link">Annuler</a>

            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="primary"
                    data-confirm-title="Enregistrer les modifications ?"
                    data-confirm-message="L'incident n° {{ $incident->id }} sera mis à jour. Son score de priorité sera recalculé."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection
