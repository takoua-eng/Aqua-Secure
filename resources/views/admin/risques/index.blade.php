@extends('layouts.admin')

@section('title', 'Analyse des risques')
@section('pretitle', 'Infrastructure · Intelligence artificielle')

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.risques.plan') }}" class="btn btn-primary">
            <i class="ti ti-coin me-1"></i> Plan de renouvellement
        </a>
        <a href="{{ route('admin.risques.pdf') }}" class="btn">
            <i class="ti ti-file-type-pdf me-1"></i> Exporter en PDF
        </a>
    </div>
@endsection

@section('content')
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row row-deck row-cards">

    {{-- Nombre d'éléments par niveau de risque --}}
    @foreach (\App\Services\ScoreRisque::NIVEAUX as [$cle, $libelle, $couleur])
        <div class="col-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body d-flex align-items-center">
                    <span class="avatar bg-{{ $couleur }} text-white me-3"><i class="ti ti-gauge"></i></span>
                    <div>
                        <div class="h2 mb-0">{{ $parNiveau[$cle] ?? 0 }}</div>
                        <div class="text-secondary">Risque {{ mb_strtolower($libelle) }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Groupes de zones (K-means) --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i class="ti ti-chart-dots-3 me-1"></i> Groupes de zones similaires</h3>
                    <div class="card-subtitle">
                        Regroupement automatique (algorithme K-means) selon la vétusté, l'état, le risque,
                        la population et les matériaux de chaque zone.
                    </div>
                </div>
            </div>
            <div class="card-body">
                @if (empty($groupes))
                    <p class="text-secondary mb-0">Aucune zone ne contient encore de réseau : rien à regrouper.</p>
                @else
                    <div class="row g-3">
                        @foreach ($groupes as $groupe)
                            <div class="col-md-{{ 12 / max(count($groupes), 1) }}">
                                <div class="card h-100 border-{{ $groupe['couleur'] }}">
                                    <div class="card-status-top bg-{{ $groupe['couleur'] }}"></div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <h3 class="card-title mb-0">{{ $groupe['libelle'] }}</h3>
                                            <span class="badge bg-{{ $groupe['couleur'] }}-lt ms-auto">
                                                {{ $groupe['zones']->count() }} zone{{ $groupe['zones']->count() > 1 ? 's' : '' }}
                                            </span>
                                        </div>

                                        {{-- Profil moyen du groupe --}}
                                        <dl class="row small mb-3">
                                            <dt class="col-7 fw-normal text-secondary">Vétusté moyenne</dt>
                                            <dd class="col-5 text-end mb-1">{{ round($groupe['moyenne']['vetuste'] * 100) }} % de la durée de vie</dd>
                                            <dt class="col-7 fw-normal text-secondary">En mauvais état</dt>
                                            <dd class="col-5 text-end mb-1">{{ round($groupe['moyenne']['degradee'] * 100) }} %</dd>
                                            <dt class="col-7 fw-normal text-secondary">Risque moyen</dt>
                                            <dd class="col-5 text-end mb-1">{{ round($groupe['moyenne']['risque']) }} / 100</dd>
                                            <dt class="col-7 fw-normal text-secondary">Population moyenne</dt>
                                            <dd class="col-5 text-end mb-1">{{ number_format($groupe['moyenne']['population'], 0, ',', ' ') }} hab.</dd>
                                            <dt class="col-7 fw-normal text-secondary">Matériaux à risque</dt>
                                            <dd class="col-5 text-end mb-0">{{ round($groupe['moyenne']['materiaux'] * 100) }} %</dd>
                                        </dl>

                                        <div class="list-group list-group-flush border rounded">
                                            @foreach ($groupe['zones'] as $zone)
                                                <div class="list-group-item d-flex align-items-center py-2">
                                                    <div>
                                                        <div class="fw-medium">{{ $zone->nom }}</div>
                                                        <div class="small text-secondary">{{ $zone->commune }}</div>
                                                    </div>
                                                    <div class="btn-list flex-nowrap ms-auto">
                                                        <button type="button" class="btn btn-sm btn-ghost-primary"
                                                                data-bs-toggle="modal" data-bs-target="#modal-analyse"
                                                                data-analyse-url="{{ route('admin.zones.analyse-ia', $zone) }}"
                                                                data-pdf-url="{{ route('admin.zones.rapport-pdf', $zone) }}"
                                                                data-zone="{{ $zone->nom }} — {{ $zone->commune }}">
                                                            <i class="ti ti-sparkles me-1"></i> Analyser
                                                        </button>
                                                        <a href="{{ route('admin.zones.rapport-pdf', $zone) }}" class="btn btn-sm btn-ghost-secondary btn-icon" title="Rapport PDF de la zone">
                                                            <i class="ti ti-file-type-pdf"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($sansReseau->isNotEmpty())
                        <p class="text-secondary small mt-3 mb-0">
                            Non classées (aucun réseau enregistré) : {{ $sansReseau->pluck('nom')->join(', ') }}.
                        </p>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Classement des éléments par score de risque --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i class="ti ti-list-numbers me-1"></i> Éléments les plus à risque</h3>
                    <div class="card-subtitle">
                        Score sur 100 : vétusté (35), état (30), population touchée (15), durée du problème (10), matériau (10).
                        Probabilités de panne : loi de Weibull selon le matériau et l'âge, risque ×3 si l'élément est déjà dégradé.
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th class="w-1">#</th>
                            <th>Code</th>
                            <th>Zone</th>
                            <th>État</th>
                            <th style="min-width: 12rem">Score de risque</th>
                            <th title="Probabilité de panne (modèle de Weibull)">Panne 1 an</th>
                            <th title="Probabilité de panne (modèle de Weibull)">Panne 5 ans</th>
                            <th title="Durée médiane avant panne">Vie restante</th>
                            <th>Facteur principal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($classement as $rang => ['reseau' => $reseau, 'risque' => $risque, 'prediction' => $prediction])
                            @php $principal = collect($risque['facteurs'])->sortByDesc('points')->first(); @endphp
                            <tr>
                                <td class="text-secondary">{{ $rang + 1 }}</td>
                                <td>
                                    <a href="{{ route('admin.reseaux.edit', $reseau) }}" class="font-monospace fw-medium">{{ $reseau->code }}</a>
                                    <div class="small text-secondary">{{ $reseau->libelleType() }}</div>
                                </td>
                                <td>
                                    {{ $reseau->zone->nom }}
                                    <div class="small text-secondary">{{ $reseau->zone->commune }}</div>
                                </td>
                                <td><span class="badge bg-{{ $reseau->couleurEtat() }}-lt">{{ $reseau->libelleEtat() }}</span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold" style="width: 2rem">{{ $risque['score'] }}</span>
                                        <div class="progress progress-sm flex-fill">
                                            <div class="progress-bar bg-{{ $risque['couleur'] }}" style="width: {{ $risque['score'] }}%"></div>
                                        </div>
                                        <span class="badge bg-{{ $risque['couleur'] }}-lt">{{ $risque['libelle'] }}</span>
                                    </div>
                                </td>
                                @if ($prediction['horsService'])
                                    <td colspan="3"><span class="badge bg-red-lt">Déjà hors service</span></td>
                                @else
                                    <td style="font-variant-numeric: tabular-nums">{{ number_format($prediction['un_an'], 1, ',', ' ') }} %</td>
                                    <td>
                                        <span class="badge bg-{{ \App\Services\ProbabiliteDefaillance::couleur($prediction['cinq_ans']) }}-lt" style="font-variant-numeric: tabular-nums">
                                            {{ number_format($prediction['cinq_ans'], 1, ',', ' ') }} %
                                        </span>
                                    </td>
                                    <td class="text-secondary">≈ {{ $prediction['vie_restante'] }} ans</td>
                                @endif
                                <td class="small text-secondary">{{ $principal['libelle'] }} ({{ $principal['points'] }} pts)</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-secondary py-4">Aucun réseau enregistré.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Fenêtre du rapport IA --}}
<div class="modal modal-blur fade" id="modal-analyse" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti ti-sparkles me-1"></i> Analyse IA — <span id="modal-analyse-zone"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="modal-analyse-contenu"></div>
            <div class="modal-footer">
                <a href="#" id="modal-analyse-pdf" class="btn"><i class="ti ti-file-type-pdf me-1"></i> Télécharger en PDF</a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
    <style>
        .rapport-ia h2 { font-size: 1rem; margin-top: 1rem; margin-bottom: .5rem; }
        .rapport-ia h2:first-child { margin-top: 0; }
        .rapport-ia ul, .rapport-ia ol { padding-left: 1.25rem; margin-bottom: .5rem; }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/analyse-ia.js') }}"></script>
    <script>
        document.getElementById('modal-analyse').addEventListener('show.bs.modal', function (e) {
            const bouton = e.relatedTarget;
            document.getElementById('modal-analyse-zone').textContent = bouton.dataset.zone;
            document.getElementById('modal-analyse-pdf').href = bouton.dataset.pdfUrl;
            analyserZone(bouton.dataset.analyseUrl, document.getElementById('modal-analyse-contenu'), bouton);
        });
    </script>
@endpush
