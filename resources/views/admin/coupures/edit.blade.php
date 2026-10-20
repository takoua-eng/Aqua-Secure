@extends('layouts.admin')

@section('title', 'Modifier la coupure')
@section('pretitle', 'Infrastructure')

@section('content')
    <form method="POST" action="{{ route('admin.coupures.update', $coupure) }}" class="card" data-validate>
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('admin.coupures._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.coupures.index') }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="primary"
                    data-confirm-title="Enregistrer les modifications ?"
                    data-confirm-message="Si les dates ou le réseau changent, les citoyens de la zone seront prévenus."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Mettre à jour
            </button>
        </div>
    </form>
@endsection
