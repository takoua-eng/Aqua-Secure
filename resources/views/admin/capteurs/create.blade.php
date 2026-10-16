@extends('layouts.admin')

@section('title', 'Nouveau capteur')
@section('pretitle', 'Surveillance')

@section('content')
    <form method="POST" action="{{ route('admin.capteurs.store') }}" class="card" data-validate>
        @csrf

        <div class="card-body">
            @include('admin.capteurs._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.capteurs.index') }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="success"
                    data-confirm-title="Créer ce capteur ?"
                    data-confirm-message="Le capteur pourra recevoir des relevés dès son enregistrement."
                    data-confirm-button="Oui, créer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection