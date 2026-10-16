@extends('layouts.admin')

@section('title', 'Nouveau point de prélèvement')
@section('pretitle', "Qualité de l'eau")

@section('content')
    <form method="POST" action="{{ route('admin.prelevements.store') }}" class="card" data-validate>
        @csrf

        <div class="card-body">
            @include('admin.prelevements._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.prelevements.index') }}" class="btn btn-link">Annuler</a>

            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="success"
                    data-confirm-title="Créer ce point ?"
                    data-confirm-message="Le point de prélèvement sera ajouté au réseau choisi."
                    data-confirm-button="Oui, créer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection
