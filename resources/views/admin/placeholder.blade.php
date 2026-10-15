@extends('layouts.admin')

@section('title', $titre)
@section('pretitle', $section)

@section('content')
    <div class="card">
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-{{ $icone }}" style="font-size: 3rem"></i></div>
            <p class="empty-title">{{ $titre }}</p>
            <p class="empty-subtitle text-secondary">{{ $description }}</p>
            <p class="small text-secondary mb-0">Module en cours de développement.</p>
        </div>
    </div>
@endsection
