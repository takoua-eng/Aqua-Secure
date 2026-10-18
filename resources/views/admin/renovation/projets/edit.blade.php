@extends('layouts.admin')

@section('title', 'Modifier le projet')
@section('pretitle', 'Rénovation')
@section('subtitle', $projet->titre)

@section('content')
    @include('admin.renovation._nav')

    <form method="POST" action="{{ route('admin.projets.update', $projet) }}" class="card" data-validate>
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('admin.renovation.projets._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.projets.show', $projet) }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="primary"
                    data-confirm-title="Enregistrer les modifications ?"
                    data-confirm-message="Les scénarios What If? déjà enregistrés sur ce projet devront être recalculés avant d'être appliqués."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection
