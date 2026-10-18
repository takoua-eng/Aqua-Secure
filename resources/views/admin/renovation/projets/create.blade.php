@extends('layouts.admin')

@section('title', 'Nouveau projet de rénovation')
@section('pretitle', 'Rénovation')

@section('content')
    @include('admin.renovation._nav')

    <form method="POST" action="{{ route('admin.projets.store') }}" class="card" data-validate>
        @csrf

        <div class="card-body">
            @include('admin.renovation.projets._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.projets.index') }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="success"
                    data-confirm-title="Créer ce projet ?"
                    data-confirm-message="Le projet pourra ensuite recevoir des financements."
                    data-confirm-button="Oui, créer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection
