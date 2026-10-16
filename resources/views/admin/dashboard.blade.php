@extends('layouts.admin')

@section('title', 'Tableau de bord')
@section('pretitle', "Vue d'ensemble")

@section('actions')
    <a href="{{ route('admin.incidents.index') }}" class="btn btn-primary">
        <i class="ti ti-list me-1"></i> Voir les incidents
    </a>
@endsection

@section('content')
<div class="row row-deck row-cards">

    {{-- Indicateurs --}}
    @foreach ($stats as $stat)
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-{{ $stat['couleur'] }} text-white avatar">
                                <i class="ti ti-{{ $stat['icone'] }}"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium h2 mb-0">{{ $stat['valeur'] }}</div>
                            <div class="text-secondary">{{ $stat['libelle'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Carte des zones : un clic sur une zone affiche ses réseaux d'eau --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header flex-wrap gap-2">
                <h3 class="card-title">Zones et réseaux d'eau</h3>
                <div class="card-actions d-flex flex-wrap align-items-center gap-3">
                    {{-- Légende remplie par carte-zones.js selon le mode --}}
                    <div id="legende-zones" class="d-flex flex-wrap gap-3 small text-secondary"></div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Couleur des zones">
                        <input type="radio" class="btn-check" name="mode-carte" id="mode-etat" value="etat" checked>
                        <label class="btn" for="mode-etat">État</label>
                        <input type="radio" class="btn-check" name="mode-carte" id="mode-risque" value="risque">
                        <label class="btn" for="mode-risque">Risque</label>
                        <input type="radio" class="btn-check" name="mode-carte" id="mode-groupe" value="groupe">
                        <label class="btn" for="mode-groupe">Groupe</label>
                    </div>
                </div>
            </div>
            <div class="row g-0">
                <div class="col-lg-8">
                    <div id="carte-zones" style="height: 420px"></div>
                </div>
                <div class="col-lg-4 border-start">
                    <div class="card-body" id="panneau-zone" style="max-height: 420px; overflow-y: auto">
                        <div class="text-center text-secondary py-5">
                            <i class="ti ti-click mb-2" style="font-size: 2.5rem"></i>
                            <p class="mb-0">Cliquez sur une zone de la carte pour voir ses réseaux d'eau.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Qualité de l'eau : conformité du mois et points à surveiller --}}
    @include('admin.partials.widgets-qualite')

    {{-- Graphiques du parc (états, matériaux, âges, évolution) --}}
    @include('admin.partials.graphiques-reseau')

    {{-- Carte des incidents --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Carte des incidents</h3></div>
            <div class="card-body p-0">
                <div id="map" style="height: 380px"></div>
            </div>
        </div>
    </div>

    {{-- Projets & financement --}}
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Financement des projets</h3></div>
            <div class="card-body">
                @foreach ($projets as $projet)
                    @php $pct = round($projet['finance'] / $projet['budget'] * 100); @endphp
                    <div class="mb-3">
                        <div class="d-flex mb-1">
                            <span>{{ $projet['nom'] }}</span>
                            <span class="ms-auto text-secondary">{{ $pct }} %</span>
                        </div>
                        <div class="progress progress-sm">
                            <div class="progress-bar bg-{{ $pct >= 75 ? 'green' : ($pct >= 40 ? 'azure' : 'orange') }}"
                                 style="width: {{ $pct }}%"></div>
                        </div>
                        <div class="small text-secondary mt-1">
                            {{ number_format($projet['finance'], 0, ',', ' ') }} / {{ number_format($projet['budget'], 0, ',', ' ') }} TND
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Derniers incidents --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Derniers signalements</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr><th>Date</th><th>Type</th><th>Localité</th><th>Gravité</th><th>Statut</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($incidents as $incident)
                            <tr>
                                <td class="text-secondary">{{ $incident['date'] }}</td>
                                <td>{{ ucfirst($incident['type']) }}</td>
                                <td>{{ $incident['localite'] }}</td>
                                <td>
                                    <span class="badge bg-{{ ['haute' => 'red', 'moyenne' => 'orange', 'faible' => 'yellow'][$incident['gravite']] }}-lt">
                                        {{ ucfirst($incident['gravite']) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ ['nouveau' => 'blue', 'en cours' => 'azure', 'résolu' => 'green'][$incident['statut']] }}-lt">
                                        {{ ucfirst($incident['statut']) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('map').setView([36.8065, 10.1815], 11);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        const incidents = @json($incidents);
        const couleurs = { fuite: '#0ea5e9', contamination: '#d63939', coupure: '#f59f00' };

        incidents.forEach(i => {
            L.circleMarker([i.lat, i.lng], {
                radius: 9, color: couleurs[i.type], fillOpacity: 0.7
            }).addTo(map).bindPopup(`<b>${i.type}</b><br>${i.localite}<br>${i.statut}`);
        });
    </script>
    <script src="{{ asset('js/analyse-ia.js') }}"></script>
    <script src="{{ asset('js/carte-zones.js') }}"></script>
    <script>
        const carteDesZones = carteZones({
            legende: 'legende-zones',
            carte: 'carte-zones',
            panneau: 'panneau-zone',
            zones: @json($zonesCarte),
            badges: { bon: 'bg-green-lt', degrade: 'bg-yellow-lt', hors_service: 'bg-red-lt' },
            icones: { canalisation: 'ti ti-line', reservoir: 'ti ti-cylinder', station: 'ti ti-building-factory-2' },
            lienReseau: @json(route('admin.reseaux.edit', '__ID__')),
            lienZone: @json(route('admin.reseaux.index', ['zone' => '__ID__'])),
            lienAnalyse: @json(route('admin.zones.analyse-ia', '__ID__')),
        });
        document.querySelectorAll('[name="mode-carte"]').forEach(bouton =>
            bouton.addEventListener('change', () => carteDesZones.changerMode(bouton.value)));
    </script>
    <style>
        .rapport-ia h2 { font-size: .95rem; margin-top: .75rem; margin-bottom: .35rem; }
        .rapport-ia h2:first-child { margin-top: 0; }
        .rapport-ia ul, .rapport-ia ol { padding-left: 1.1rem; margin-bottom: .5rem; }
    </style>
@endpush
