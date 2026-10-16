@extends('layouts.admin')

@section('title', 'Modifier un relevé')
@section('pretitle', 'Surveillance')

@section('content')
    <form method="POST" action="{{ route('admin.releves.update', $releve) }}" class="card" data-validate>
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('admin.releves._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.capteurs.show', $releve->capteur_id) }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="primary"
                    data-confirm-title="Enregistrer les modifications ?"
                    data-confirm-message="L'état normal ou hors seuil sera recalculé."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Mettre à jour
            </button>
        </div>
    </form>
@endsection