@extends('layouts.admin')

@section('title', 'Programmer une coupure')
@section('pretitle', 'Infrastructure')

@section('content')
    <form method="POST" action="{{ route('admin.coupures.store') }}" class="card" data-validate>
        @csrf

        <div class="card-body">
            @include('admin.coupures._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.coupures.index') }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="success"
                    data-confirm-title="Programmer cette coupure ?"
                    data-confirm-message="Les citoyens de la zone seront prévenus par email."
                    data-confirm-button="Oui, programmer">
                <i class="ti ti-calendar-plus me-1"></i> Programmer
            </button>
        </div>
    </form>
@endsection
