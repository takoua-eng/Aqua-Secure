@extends('layouts.admin')

@section('title', 'Nouvelle zone')
@section('pretitle', 'Infrastructure')

@section('content')
    <form method="POST" action="{{ route('admin.zones.store') }}" class="card" data-validate>
        @csrf

        <div class="card-body">
            @include('admin.zones._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.zones.index') }}" class="btn btn-link">Annuler</a>

            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="success"
                    data-confirm-title="Créer cette zone ?"
                    data-confirm-message="La zone pourra ensuite recevoir des réseaux."
                    data-confirm-button="Oui, créer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection
