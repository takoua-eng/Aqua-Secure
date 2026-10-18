@extends('layouts.admin')

@section('title', 'Modifier le financement')
@section('pretitle', 'Rénovation')

@section('content')
    @include('admin.renovation._nav')

    <form method="POST" action="{{ route('admin.financements.update', $financement) }}" class="card" data-validate>
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('admin.renovation.financements._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.financements.show', $financement) }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="primary"
                    data-confirm-title="Enregistrer les modifications ?"
                    data-confirm-message="Le financement réel du projet sera modifié."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection
