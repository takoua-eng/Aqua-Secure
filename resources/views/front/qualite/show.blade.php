@extends('layouts.front')

@section('title', $point->nom . " — Qualité de l'eau")

@section('content')
@php
    $horsNorme = $derniere?->parametresHorsNorme() ?? [];
@endphp

{{-- Bandeau --}}
<section class="hero" style="padding: 60px 0 50px">
    <div class="container">
        <a href="{{ route('qualite.index') }}" class="text-white-50 text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Qualité de l'eau
        </a>
        <h1 class="fw-bold mt-2 mb-1">{{ $point->nom }}</h1>
        <p class="opacity-75 mb-0">
            <i class="bi bi-geo-alt"></i> {{ $point->adresse }} · {{ $point->reseau->zone->commune }}
            · {{ $point->libelleType() }}
        </p>
    </div>
</section>

<section class="section pt-5">
    <div class="container">

        {{-- Résultat de la dernière analyse --}}
        @if ($derniere)
            <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-5 border-{{ $derniere->conforme ? 'success' : 'danger' }}">
                <div class="card-body p-4 d-flex flex-wrap align-items-center gap-3">
                    <span class="icone-rond bg-{{ $derniere->conforme ? 'success' : 'danger' }} bg-opacity-10 text-{{ $derniere->conforme ? 'success' : 'danger' }}">
                        <i class="bi {{ $derniere->conforme ? 'bi-check-circle-fill' : 'bi-exclamation-octagon-fill' }}"></i>
                    </span>
                    <div class="flex-grow-1">
                        <h2 class="h4 fw-bold mb-1">
                            {{ $derniere->conforme ? 'Eau conforme aux normes de potabilité' : 'Eau non conforme' }}
                        </h2>
                        <div class="text-secondary">
                            Dernière analyse le {{ $derniere->date_prelevement->format('d/m/Y') }} · {{ $derniere->laboratoire }}
                        </div>
                        @unless ($derniere->conforme)
                            <div class="mt-2 small">
                                <i class="bi bi-info-circle"></i>
                                Par précaution, faites bouillir l'eau avant de la boire ou utilisez de l'eau en bouteille,
                                surtout pour les nourrissons. La régie des eaux a été informée.
                            </div>
                        @endunless
                    </div>
                    @if ($taux !== null)
                        <div class="text-center px-3">
                            <div class="fs-2 fw-bold lh-1">{{ number_format($taux, 0, ',', ' ') }} %</div>
                            <div class="small text-secondary">analyses conformes<br>({{ $point->analyses->count() }} au total)</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Détail des 5 paramètres, expliqués simplement --}}
            <h2 class="fw-bold h4 mb-3">Détail de la dernière analyse</h2>
            <div class="row g-3 mb-5">
                @foreach (\App\Models\AnalyseQualite::PARAMETRES as $cle => $infos)
                    @php $hors = isset($horsNorme[$cle]); @endphp
                    <div class="col-md-6 col-lg">
                        <div class="card carte-service border-0 shadow-sm h-100 p-3">
                            <div class="d-flex align-items-center mb-1">
                                <h6 class="fw-semibold mb-0">{{ $infos['libelle'] }}</h6>
                                <i class="bi {{ $hors ? 'bi-x-circle-fill text-danger' : 'bi-check-circle-fill text-success' }} ms-auto"></i>
                            </div>
                            <div class="fs-4 fw-bold {{ $hors ? 'text-danger' : '' }}">
                                {{ \App\Models\AnalyseQualite::formater($cle, $derniere->{$cle}) }}
                            </div>
                            <div class="small text-secondary mb-2">Norme : {{ \App\Models\AnalyseQualite::norme($cle) }}</div>
                            <p class="small text-secondary mb-0">{{ \App\Models\AnalyseQualite::EXPLICATIONS[$cle] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Évolution d'un paramètre --}}
            <div class="row g-4 mb-5">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold"><i class="bi bi-graph-up me-1"></i> Évolution</span>
                            <select id="choix-parametre" class="form-select form-select-sm ms-auto" style="max-width: 16rem">
                                @foreach (\App\Models\AnalyseQualite::PARAMETRES as $cle => $infos)
                                    <option value="{{ $cle }}">{{ $infos['libelle'] }}{{ $infos['unite'] ? ' (' . $infos['unite'] . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="card-body">
                            <div id="graphique-parametre" style="min-height: 280px"></div>
                            <div class="small text-secondary">Lignes rouges en pointillés : limites de la norme. Points rouges : mesures hors norme.</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-1"></i> Emplacement</div>
                        @if ($point->latitude !== null && $point->longitude !== null)
                            <div id="carte-point" style="min-height: 300px; height: 100%"></div>
                        @else
                            <div class="card-body text-center text-secondary">Position non renseignée.</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Historique --}}
            <h2 class="fw-bold h4 mb-3">Historique des analyses</h2>
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Résultat</th>
                                <th>Paramètres hors norme</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($point->analyses as $analyse)
                                <tr>
                                    <td class="text-nowrap">{{ $analyse->date_prelevement->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $analyse->conforme ? 'success' : 'danger' }}">{{ $analyse->libelleConformite() }}</span>
                                    </td>
                                    <td class="small">
                                        @forelse ($analyse->parametresHorsNorme() as $message)
                                            <div class="text-danger">{{ $message }}</div>
                                        @empty
                                            <span class="text-secondary">Aucun</span>
                                        @endforelse
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="text-center text-secondary py-5">
                <i class="bi bi-hourglass-split d-block mb-2" style="font-size: 2.5rem"></i>
                Ce point n'a pas encore été analysé. Les résultats apparaîtront ici dès la première analyse.
            </div>
        @endif
    </div>
</section>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
    <script src="{{ asset('js/graphique-qualite.js') }}"></script>
    <script>
        (function () {
            const zoneCarte = document.getElementById('carte-point');
            if (zoneCarte) {
                const position = [{{ $point->latitude ?? 0 }}, {{ $point->longitude ?? 0 }}];
                const carte = L.map(zoneCarte).setView(position, 15);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(carte);
                L.marker(position).addTo(carte).bindPopup(@json($point->nom));
            }

            graphiqueQualite({
                element: 'graphique-parametre',
                choix: 'choix-parametre',
                evolution: @json($evolution),
                seuils: @json(\App\Models\AnalyseQualite::SEUILS),
                parametres: @json(\App\Models\AnalyseQualite::PARAMETRES),
            });
        })();
    </script>
@endpush
