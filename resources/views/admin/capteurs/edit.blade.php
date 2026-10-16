@extends('layouts.admin')

@section('title', 'Modifier ' . $capteur->code)
@section('pretitle', 'Surveillance')

@section('content')
    <form method="POST" action="{{ route('admin.capteurs.update', $capteur) }}" class="card" data-validate>
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('admin.capteurs._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.capteurs.show', $capteur) }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="primary"
                    data-confirm-title="Enregistrer les modifications ?"
                    data-confirm-message="Si vous changez les seuils, l'état « hors seuil » de tous les relevés de {{ $capteur->code }} sera recalculé."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Mettre à jour
            </button>
        </div>
    </form>
@endsection