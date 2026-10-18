@extends('layouts.front')

@section('title', $incident->libelleType() . ' — ' . $incident->reseau->zone->nom)

@section('content')
@php
    [$libelle, $couleur, $icone] = \App\Http\Controllers\Front\IncidentPublicController::ETATS[$etat];
    [$libelleImpact, $couleurImpact] = \App\Http\Controllers\Front\IncidentPublicController::IMPACTS[$incident->gravite];
    $consigne = \App\Http\Controllers\Front\IncidentPublicController::CONSIGNES[$incident->type] ?? null;
    $messages = [
        'signale'       => "L'incident a été constaté par la régie des eaux. Une équipe d'intervention va être programmée.",
        'programme'     => "Une équipe d'intervention est programmée pour réparer.",
        'en_reparation' => "Une équipe travaille actuellement sur le réseau. Des perturbations restent possibles jusqu'à la fin des travaux.",
        'resolu'        => "La réparation est terminée. Si le problème persiste chez vous, signalez-le.",
    ];
    $couleurRepere = ['signale' => '#dc3545', 'programme' => '#0dcaf0', 'en_reparation' => '#ffc107', 'resolu' => '#198754'][$etat];
@endphp

{{-- Bandeau --}}
<section class="hero" style="padding: 60px 0 50px">
    <div class="container">
        <a href="{{ route('incidents.index') }}" class="text-white-50 text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Incidents et travaux
        </a>
        <h1 class="fw-bold mt-2 mb-1">{{ $incident->libelleType() }} — {{ $incident->reseau->zone->nom }}</h1>
        <p class="opacity-75 mb-0">
            <i class="bi bi-geo-alt"></i> {{ $incident->reseau->zone->commune }} · {{ $incident->reseau->libelleType() }}
            · constaté le {{ $incident->date_signalement->format('d/m/Y') }}
        </p>
    </div>
</section>

<section class="section pt-5">
    <div class="container">

        {{-- État actuel --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-5 border-{{ $couleur }}">
            <div class="card-body p-4 d-flex flex-wrap align-items-center gap-3">
                <span class="icone-rond bg-{{ $couleur }} bg-opacity-10 text-{{ $couleur }}"><i class="bi {{ $icone }}"></i></span>
                <div class="flex-grow-1">
                    <h2 class="h4 fw-bold mb-1">{{ $libelle }}</h2>
                    <div class="text-secondary">{{ $detail }} {{ $messages[$etat] }}</div>
                </div>
                <span class="badge text-bg-{{ $couleurImpact }} fs-6">{{ $libelleImpact }}</span>
            </div>
        </div>

        <div class="row g-4">
            {{-- Étapes du suivi --}}
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-1"></i> Suivi de la réparation</div>
                    <div class="card-body">
                        <ol class="list-unstyled mb-0">
                            @foreach ($etapes as $etape)
                                <li class="d-flex align-items-start {{ $loop->last ? '' : 'mb-3' }}">
                                    <span class="icone-rond flex-shrink-0 me-3 {{ $etape['faite'] ? 'bg-success text-white' : 'bg-light text-secondary border' }}"
                                          style="width: 36px; height: 36px; font-size: 1rem">
                                        <i class="bi {{ $etape['faite'] ? 'bi-check-lg' : 'bi-circle' }}"></i>
                                    </span>
                                    <div>
                                        <div class="fw-semibold {{ $etape['faite'] ? '' : 'text-secondary' }}">{{ $etape['libelle'] }}</div>
                                        <div class="small text-secondary">
                                            {{ $etape['faite'] ? ($etape['date'] ? 'Le ' . $etape['date'] : 'Étape franchie') : 'À venir' }}
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </div>

            {{-- Ce que ça change pour vous --}}
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-house-heart me-1"></i> Ce que cela change pour vous</div>
                    <div class="card-body">
                        @if ($consigne && $etat !== 'resolu')
                            <p class="mb-2"><i class="bi bi-info-circle text-primary me-1"></i> {{ $consigne['effet'] }}</p>
                            <p class="text-secondary mb-3"><i class="bi bi-lightbulb text-warning me-1"></i> {{ $consigne['conseil'] }}</p>
                        @else
                            <p class="text-secondary mb-3"><i class="bi bi-check-circle text-success me-1"></i> La situation est revenue à la normale dans ce secteur.</p>
                        @endif

                        @if (auth()->user()->isCitoyen())
                            <a href="{{ route('signalement.create') }}" class="btn btn-eau">
                                <i class="bi bi-exclamation-triangle"></i> Signaler un autre problème
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Emplacement --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-map me-1"></i> Emplacement</div>
                    @if ($position)
                        <div id="carte-incident" style="height: 300px"></div>
                        @if ($position['approximative'])
                            <div class="card-footer bg-white small text-secondary">Position approximative : centre de la commune de {{ $incident->reseau->zone->commune }}.</div>
                        @endif
                    @else
                        <div class="card-body text-center text-secondary py-5">
                            <i class="bi bi-geo d-block mb-2" style="font-size: 2.5rem"></i>
                            Emplacement non disponible : {{ $incident->reseau->zone->nom }}, {{ $incident->reseau->zone->commune }}.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@if ($position)
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    @endpush

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            (function () {
                const position = @json($position);
                const carte = L.map('carte-incident').setView([position.lat, position.lng], position.approximative ? 13 : 15);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(carte);
                L.circleMarker([position.lat, position.lng], { radius: 11, color: '#ffffff', weight: 2, fillColor: @json($couleurRepere), fillOpacity: .9 }).addTo(carte);
            })();
        </script>
    @endpush
@endif
