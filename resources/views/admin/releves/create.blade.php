@extends('layouts.admin')

@section('title', 'Nouveau relevé')
@section('pretitle', 'Surveillance')

@section('content')
    <form method="POST" action="{{ route('admin.releves.store') }}" class="card" data-validate>
        @csrf

        <div class="card-body">
            @include('admin.releves._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ url()->previous() }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="success"
                    data-confirm-title="Enregistrer ce relevé ?"
                    data-confirm-message="L'état normal ou hors seuil sera calculé automatiquement à partir des seuils du capteur."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection