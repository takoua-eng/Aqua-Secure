@extends('layouts.admin')

@section('title', 'Modifier l\'analyse du ' . $analyse->date_prelevement->format('d/m/Y'))
@section('pretitle', "Qualité de l'eau")

@section('content')
    <form method="POST" action="{{ route('admin.analyses.update', $analyse) }}" class="card" data-validate>
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('admin.analyses._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.analyses.show', $analyse) }}" class="btn btn-link">Annuler</a>

            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="primary"
                    data-confirm-title="Enregistrer les modifications ?"
                    data-confirm-message="L'analyse va être mise à jour et sa conformité recalculée."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Mettre à jour
            </button>
        </div>
    </form>
@endsection
