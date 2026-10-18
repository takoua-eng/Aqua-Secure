@extends('layouts.admin')

@section('title', 'Nouvel incident')
@section('pretitle', 'Exploitation')
@section('subtitle', 'Problème technique confirmé sur un réseau : fuite, contamination ou coupure.')

@section('content')
    @include('admin.incidents._navigation')

    <form method="POST" action="{{ route('admin.incidents.store') }}" class="card" data-validate>
        @csrf

        <div class="card-body">
            @include('admin.incidents._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.incidents.index') }}" class="btn btn-link">Annuler</a>

            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="success"
                    data-confirm-title="Enregistrer cet incident ?"
                    data-confirm-message="L'incident sera ajouté au réseau choisi avec le statut « Non traité »."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection
