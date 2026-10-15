@extends('layouts.admin')

@section('title', 'Nouvel utilisateur')
@section('pretitle', 'Administration')

@section('content')
    <form method="POST" action="{{ route('admin.users.store') }}" class="card" data-validate>
        @csrf

        <div class="card-body">
            @include('admin.users._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.users.index') }}" class="btn btn-link">Annuler</a>

            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="success"
                    data-confirm-title="Créer cet utilisateur ?"
                    data-confirm-message="Le nouveau compte pourra se connecter immédiatement."
                    data-confirm-button="Oui, créer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection