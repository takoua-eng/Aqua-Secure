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
@endpush
