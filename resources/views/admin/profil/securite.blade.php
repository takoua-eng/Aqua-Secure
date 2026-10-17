@extends('layouts.admin')

@section('title', 'Paramètres du compte')
@section('pretitle', 'Mon compte')

@section('content')
<div class="card">
    <div class="row g-0">
        <div class="col-12 col-md-3 border-end">
            @include('admin.profil._menu')
        </div>

        <div class="col-12 col-md-9">
            <div class="card-body">
                <h2 class="mb-4">Sécurité</h2>

                <h3 class="card-title"><i class="ti ti-device-mobile-message me-1"></i> Double authentification (2FA)</h3>
                @include('compte._deux-facteurs')
            </div>

            <div class="card-body border-top">
                <h3 class="card-title text-danger"><i class="ti ti-trash me-1"></i> Supprimer mon compte</h3>
                @include('compte._suppression')
            </div>
        </div>
    </div>
</div>
@endsection