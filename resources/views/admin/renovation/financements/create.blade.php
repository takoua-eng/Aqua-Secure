@extends('layouts.admin')

@section('title', 'Nouveau financement')
@section('pretitle', 'Rénovation')

@section('content')
    @include('admin.renovation._nav')

    <form method="POST" action="{{ route('admin.financements.store') }}" class="card" data-validate>
        @csrf

        <div class="card-body">
            @include('admin.renovation.financements._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ url()->previous() === url()->current() ? route('admin.financements.index') : url()->previous() }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="success"
                    data-confirm-title="Enregistrer ce financement ?"
                    data-confirm-message="Le montant sera ajouté aux financements réels du projet."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection
