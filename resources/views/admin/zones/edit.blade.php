@extends('layouts.admin')

@section('title', 'Modifier ' . $zone->nom)
@section('pretitle', 'Infrastructure')

@section('content')
    <form method="POST" action="{{ route('admin.zones.update', $zone) }}" class="card" data-validate>
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('admin.zones._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.zones.index') }}" class="btn btn-link">Annuler</a>

            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="primary"
                    data-confirm-title="Enregistrer les modifications ?"
                    data-confirm-message="Les informations de la zone {{ $zone->nom }} vont être mises à jour."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Mettre à jour
            </button>
        </div>
    </form>
@endsection
