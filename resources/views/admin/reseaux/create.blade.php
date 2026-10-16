@extends('layouts.admin')

@section('title', 'Nouveau réseau')
@section('pretitle', 'Infrastructure')

@section('content')
    <form method="POST" action="{{ route('admin.reseaux.store') }}" class="card" data-validate>
        @csrf

        <div class="card-body">
            @include('admin.reseaux._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.reseaux.index') }}" class="btn btn-link">Annuler</a>

            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="success"
                    data-confirm-title="Créer ce réseau ?"
                    data-confirm-message="L'élément sera ajouté au réseau d'eau de la zone choisie."
                    data-confirm-button="Oui, créer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection
