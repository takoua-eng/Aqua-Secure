@extends('layouts.front')

@section('title', 'Accueil')

@section('content')

{{-- Avis de coupure d'eau dans la zone du citoyen (module Infrastructure) --}}
@include('front.partials.avis-coupure')

{{-- Héros --}}
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <h1 class="display-5 fw-bold mb-3">Ensemble, protégeons notre eau potable</h1>
                <p class="lead mb-4 opacity-75">
                    Signalez une fuite, une contamination ou une coupure en quelques clics,
                    et suivez l'avancement des projets de rénovation de votre réseau.
                </p>
                @auth
    @if (auth()->user()->isCitoyen())
        <a href="{{ route('signalement.create') }}" class="btn btn-light btn-lg me-2">
            <i class="bi bi-exclamation-triangle"></i> Signaler un incident
        </a>
    @endif
@else
    <a href="{{ route('login') }}" class="btn btn-light btn-lg me-2">
        <i class="bi bi-box-arrow-in-right"></i> Se connecter pour signaler
    </a>
@endauth
                <a href="#projets" class="btn btn-outline-light btn-lg">Voir les projets</a>
            </div>
            <div class="col-lg-5 text-center">
                <i class="bi bi-droplet-half" style="font-size: 11rem; opacity: .85"></i>
            </div>
        </div>
    </div>
</section>

{{-- Types de signalement --}}
<section class="section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Que pouvez-vous signaler ?</h2>
            <p class="text-secondary">Chaque signalement est transmis à la régie des eaux concernée.</p>
        </div>
        <div class="row g-4">
            @foreach ([
                ['bi-moisture', 'Fuite', 'Canalisation percée, eau qui jaillit sur la chaussée, compteur qui fuit.', 'primary'],
                ['bi-virus', 'Contamination', "Eau trouble, odeur ou goût anormal, couleur suspecte.", 'danger'],
                ['bi-sun', 'Coupure (sécheresse)', "Absence d'eau, pression très faible, coupures répétées.", 'warning'],
            ] as [$icone, $titre, $texte, $couleur])
                <div class="col-md-4">
                    <div class="card carte-service shadow-sm h-100 p-4 text-center">
                        <div class="mb-3">
                            <span class="icone-rond bg-{{ $couleur }} bg-opacity-10 text-{{ $couleur }}">
                                <i class="bi {{ $icone }}"></i>
                            </span>
                        </div>
                        <h5 class="fw-semibold">{{ $titre }}</h5>
                        <p class="text-secondary small mb-0">{{ $texte }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Projets et financement --}}
<section class="section section-clair" id="projets">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Projets de rénovation et de décontamination</h2>
            <p class="text-secondary">Suivez en toute transparence où en est le financement.</p>
        </div>
        <div class="row g-4">
            @foreach ($projets as $projet)
                @php $pct = round($projet['finance'] / $projet['budget'] * 100); @endphp
                <div class="col-md-6 col-lg-4">
                    <div class="card carte-service shadow-sm h-100 p-4">
                        <h6 class="fw-semibold">{{ $projet['nom'] }}</h6>
                        <p class="small text-secondary">{{ $projet['localite'] }}</p>
                        <div class="progress mb-2" style="height: 10px">
                            <div class="progress-bar" style="width: {{ $pct }}%; background: var(--eau-bleu)"></div>
                        </div>
                        <div class="d-flex small">
                            <span>{{ $pct }} % financé</span>
                            <span class="ms-auto text-secondary">{{ number_format($projet['budget'], 0, ',', ' ') }} TND</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Carte des zones (citoyens connectés) : un clic sur une zone affiche ses réseaux d'eau --}}
@auth
<section class="section section-clair" id="zones">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Zones et réseaux d'eau</h2>
            <p class="text-secondary">Cliquez sur une zone pour voir les réseaux d'eau disponibles et leur état.</p>
        </div>
        <div class="card shadow-sm rounded-4 overflow-hidden">
            <div class="row g-0">
                <div class="col-lg-8">
                    <div id="carte-zones" style="height: 420px"></div>
                </div>
                <div class="col-lg-4 border-start">
                    <div class="p-4" id="panneau-zone" style="max-height: 420px; overflow-y: auto">
                        <div class="text-center text-secondary py-5">
                            <i class="bi bi-hand-index d-block mb-2" style="font-size: 2.5rem"></i>
                            <p class="mb-0">Sélectionnez une zone sur la carte.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-3 small text-secondary">
                <span><span class="badge text-bg-success me-1">&nbsp;</span> Bon</span>
                <span><span class="badge text-bg-warning me-1">&nbsp;</span> Dégradé</span>
                <span><span class="badge text-bg-danger me-1">&nbsp;</span> Hors service</span>
                <span><span class="badge text-bg-secondary me-1">&nbsp;</span> Sans réseau</span>
            </div>
        </div>
    </div>
</section>
@endauth

{{-- Carte publique --}}
<section class="section" id="carte">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Incidents en cours</h2>
            <p class="text-secondary">Les signalements validés par les gestionnaires.</p>
        </div>
        <div id="map" class="rounded-4 shadow-sm" style="height: 420px"></div>
    </div>
</section>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('map').setView([36.8065, 10.1815], 11);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
        @json($incidents).forEach(i => {
            L.marker([i.lat, i.lng]).addTo(map).bindPopup(`<b>${i.type}</b><br>${i.localite}`);
        });
    </script>
    @auth
        <script src="{{ asset('js/carte-zones.js') }}"></script>
        <script>
            carteZones({
                carte: 'carte-zones',
                panneau: 'panneau-zone',
                zones: @json($zonesCarte),
                badges: { bon: 'text-bg-success', degrade: 'text-bg-warning', hors_service: 'text-bg-danger' },
                icones: { canalisation: 'bi bi-water', reservoir: 'bi bi-database', station: 'bi bi-buildings' },
            });
        </script>
    @endauth
@endpush
