@extends('layouts.admin')

@section('title', $point->code . ' · ' . $point->nom)
@section('pretitle', "Qualité de l'eau · Point de prélèvement")

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.prelevements.index') }}" class="btn btn-link">
            <i class="ti ti-arrow-left me-1"></i> Retour à la liste
        </a>
        <a href="{{ route('admin.prelevements.edit', $point) }}" class="btn">
            <i class="ti ti-edit me-1"></i> Modifier
        </a>
        @if ($point->actif)
            <a href="{{ route('admin.analyses.create', ['point' => $point->id]) }}" class="btn btn-primary">
                <i class="ti ti-flask me-1"></i> Nouvelle analyse
            </a>
        @endif
    </div>
@endsection

@section('content')
@php
    $nbConformes = $point->analyses->where('conforme', true)->count();
    $derniere = $point->analyses->first();
@endphp

@if ($retard = \App\Models\PointPrelevement::retardAnalyse($point->actif, $derniere?->date_prelevement))
    <div class="alert alert-warning">
        <i class="ti ti-clock-exclamation me-1"></i> {{ $retard }} : un nouveau prélèvement est à programmer.
        <a href="{{ route('admin.analyses.create', ['point' => $point->id]) }}" class="alert-link ms-1">Saisir une analyse</a>
    </div>
@endif

@if (! $point->actif)
    <div class="alert alert-warning">
        <i class="ti ti-plug-off me-1"></i> Ce point est hors service : son historique est conservé, mais il ne reçoit plus de nouvelles analyses.
    </div>
@endif

<div class="row row-deck row-cards">

    {{-- Informations du point --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-info-circle me-1"></i> Informations</h3>
                <div class="card-actions">
                    <span class="badge bg-{{ $point->actif ? 'green' : 'secondary' }}-lt">{{ $point->libelleActif() }}</span>
                </div>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-secondary">Code</dt>
                    <dd class="col-7 font-monospace">{{ $point->code }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Type</dt>
                    <dd class="col-7"><i class="ti ti-{{ $point->iconeType() }} me-1"></i>{{ $point->libelleType() }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Adresse</dt>
                    <dd class="col-7">{{ $point->adresse }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Réseau</dt>
                    <dd class="col-7">
                        <a href="{{ route('admin.reseaux.edit', $point->reseau) }}" class="font-monospace">{{ $point->reseau->code }}</a>
                        <div class="small text-secondary">{{ $point->reseau->libelleType() }}</div>
                    </dd>
                    <dt class="col-5 fw-normal text-secondary">Zone</dt>
                    <dd class="col-7">{{ $point->reseau->zone->libelle() }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Position</dt>
                    <dd class="col-7 mb-0">
                        @if ($point->latitude !== null)
                            {{ number_format($point->latitude, 5, ',', '') }} ; {{ number_format($point->longitude, 5, ',', '') }}
                        @else
                            <span class="text-secondary">Non renseignée</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Carte --}}
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-map-pin me-1"></i> Emplacement</h3></div>
            @if ($point->latitude !== null && $point->longitude !== null)
                <div id="carte-point" class="card-body p-0" style="min-height: 260px"></div>
            @else
                <div class="card-body">
                    <div class="empty">
                        <div class="empty-icon"><i class="ti ti-map-off" style="font-size: 3rem"></i></div>
                        <p class="empty-title">Position non renseignée</p>
                        <p class="empty-subtitle text-secondary">Modifiez le point et cliquez sur la carte pour le placer.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Taux de conformité --}}
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-shield-check me-1"></i> Conformité</h3></div>
            <div class="card-body text-center d-flex flex-column justify-content-center">
                @if ($taux === null)
                    <div class="text-secondary">Aucune analyse pour ce point.</div>
                @else
                    <div class="display-6 fw-bold text-{{ \App\Models\PointPrelevement::couleurTaux($taux) }}">{{ number_format($taux, 1, ',', ' ') }} %</div>
                    <div class="text-secondary mb-3">des analyses sont conformes</div>
                    <div class="progress progress-sm mb-3">
                        <div class="progress-bar bg-{{ \App\Models\PointPrelevement::couleurTaux($taux) }}" style="width: {{ $taux }}%"></div>
                    </div>
                    <div class="small">
                        <span class="text-green">{{ $nbConformes }} conforme{{ $nbConformes > 1 ? 's' : '' }}</span> ·
                        <span class="text-red">{{ $point->analyses->count() - $nbConformes }} non conforme{{ $point->analyses->count() - $nbConformes > 1 ? 's' : '' }}</span>
                    </div>
                    @if ($derniere)
                        <div class="mt-3 small text-secondary">Dernière analyse le {{ $derniere->date_prelevement->format('d/m/Y') }}</div>
                        <span class="badge bg-{{ $derniere->couleurConformite() }}-lt mt-1">{{ $derniere->libelleConformite() }}</span>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Évolution d'un paramètre, avec les limites de la norme --}}
    @if ($point->analyses->isNotEmpty())
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title"><i class="ti ti-chart-line me-1"></i> Évolution d'un paramètre</h3>
                        <div class="card-subtitle">Les lignes rouges en pointillés marquent les limites de la norme ; les points rouges sont hors norme.</div>
                    </div>
                    <div class="card-actions">
                        <select id="choix-parametre" class="form-select">
                            @foreach (\App\Models\AnalyseQualite::PARAMETRES as $cle => $infos)
                                <option value="{{ $cle }}">{{ $infos['libelle'] }}{{ $infos['unite'] ? ' (' . $infos['unite'] . ')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div id="graphique-parametre" style="min-height: 280px"></div>
                </div>
            </div>
        </div>
    @endif

    {{-- Historique des analyses --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-history me-1"></i> Historique des analyses</h3>
                <div class="card-actions">
                    <a href="{{ route('admin.analyses.index', ['point' => $point->id]) }}" class="btn btn-sm">Voir dans la liste des analyses</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            @foreach (\App\Models\AnalyseQualite::PARAMETRES as $infos)
                                <th class="text-end">{{ $infos['libelle'] }}</th>
                            @endforeach
                            <th>Résultat</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($point->analyses as $analyse)
                            <tr>
                                <td>{{ $analyse->date_prelevement->format('d/m/Y') }}</td>
                                @foreach (array_keys(\App\Models\AnalyseQualite::PARAMETRES) as $parametre)
                                    <td class="text-end {{ $analyse->estHorsNorme($parametre) ? 'text-red fw-bold' : '' }}" style="font-variant-numeric: tabular-nums">
                                        {{ \App\Models\AnalyseQualite::formater($parametre, $analyse->{$parametre}) }}
                                    </td>
                                @endforeach
                                <td><span class="badge bg-{{ $analyse->couleurConformite() }}-lt">{{ $analyse->libelleConformite() }}</span></td>
                                <td>
                                    <a href="{{ route('admin.analyses.show', $analyse) }}" class="btn btn-sm">
                                        <i class="ti ti-file-description me-1"></i> Bulletin
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty">
                                        <div class="empty-icon"><i class="ti ti-flask" style="font-size: 3rem"></i></div>
                                        <p class="empty-title">Aucune analyse</p>
                                        <p class="empty-subtitle text-secondary">Les résultats de laboratoire de ce point apparaîtront ici.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
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
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
    <script src="{{ asset('js/graphique-qualite.js') }}"></script>
    <script>
        (function () {
            // Carte du point
            const zoneCarte = document.getElementById('carte-point');
            if (zoneCarte) {
                const position = [{{ $point->latitude ?? 0 }}, {{ $point->longitude ?? 0 }}];
                const carte = L.map(zoneCarte).setView(position, 15);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(carte);
                L.marker(position).addTo(carte).bindPopup(@json($point->code . ' — ' . $point->nom));
            }

            // Graphique : un paramètre à la fois, avec les limites de la norme
            graphiqueQualite({
                element: 'graphique-parametre',
                choix: 'choix-parametre',
                evolution: @json($evolution),
                seuils: @json(\App\Models\AnalyseQualite::SEUILS),
                parametres: @json(\App\Models\AnalyseQualite::PARAMETRES),            });
        })();
    </script>
@endpush
