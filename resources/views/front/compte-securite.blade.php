@extends('layouts.front')

@section('title', 'Sécurité du compte')

@section('content')
<section class="section section-clair">
    <div class="container" style="max-width: 760px">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 fw-bold mb-0">Sécurité du compte</h1>
            <a href="{{ route('compte') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Mon compte</a>
        </div>

        <div class="card border-0 shadow-sm p-4 mb-4">
            <h2 class="h5 fw-semibold mb-3"><i class="bi bi-phone"></i> Double authentification (2FA)</h2>
            @include('compte._deux-facteurs')
        </div>

        <div class="card border-0 shadow-sm p-4 border-danger">
            <h2 class="h5 fw-semibold text-danger mb-3"><i class="bi bi-trash"></i> Supprimer mon compte</h2>
            @include('compte._suppression')
        </div>
    </div>
</section>
@endsection