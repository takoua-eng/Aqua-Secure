@extends('layouts.admin')

@section('title', 'Modifier ' . $point->code)
@section('pretitle', "Qualité de l'eau")

@section('content')
    <form method="POST" action="{{ route('admin.prelevements.update', $point) }}" class="card" data-validate>
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('admin.prelevements._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.prelevements.show', $point) }}" class="btn btn-link">Annuler</a>

            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="primary"
                    data-confirm-title="Enregistrer les modifications ?"
                    data-confirm-message="Le point {{ $point->code }} va être mis à jour."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Mettre à jour
            </button>
        </div>
    </form>
@endsection
