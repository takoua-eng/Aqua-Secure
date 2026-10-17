@extends('layouts.admin')

@section('title', 'Statistiques des incidents')
@section('pretitle', 'Exploitation')
@section('subtitle')
    Chiffres calculés en direct à partir de la base de données
    @if ($filtreActif)
        · <strong>filtrés</strong> :
        {{ $filtres['type'] ? \App\Models\Incident::TYPES[$filtres['type']] : 'tous les types' }},
        @if ($filtres['du'] && $filtres['au'])
            signalés du {{ \Illuminate\Support\Carbon::parse($filtres['du'])->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($filtres['au'])->format('d/m/Y') }}
        @elseif ($filtres['du'])
            signalés depuis le {{ \Illuminate\Support\Carbon::parse($filtres['du'])->format('d/m/Y') }}
        @elseif ($filtres['au'])
            signalés jusqu'au {{ \Illuminate\Support\Carbon::parse($filtres['au'])->format('d/m/Y') }}
        @else
            toute période
        @endif
    @endif
@endsection

@section('actions')
    {{-- Le JSON reprend les filtres en cours --}}
    <a href="{{ route('admin.incidents.statistiques.donnees', array_filter($filtres)) }}" class="btn" target="_blank" rel="noopener">
        <i class="ti ti-code me-1"></i> Données JSON
    </a>
@endsection

@section('content')
    @include('admin.incidents._navigation')

    @if ($erreurFiltres)
        <div class="alert alert-warning"><i class="ti ti-calendar-x me-1"></i> {{ $erreurFiltres }}</div>
    @endif

    {{-- Filtres : période de signalement et type d'incident (appliqués à tous les indicateurs et graphiques) --}}
    @if ($filtreActif || $stats['total'] > 0 || $erreurFiltres)
        <form method="GET" class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="stat-du">Signalés du</label>
                        <input type="date" id="stat-du" name="du" value="{{ $filtres['du'] }}" class="form-control" max="{{ today()->format('Y-m-d') }}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="stat-au">au</label>
                        <input type="date" id="stat-au" name="au" value="{{ $filtres['au'] }}" class="form-control" max="{{ today()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="stat-type">Type d'incident</label>
                        <select id="stat-type" name="type" class="form-select" onchange="this.form.submit()">
                            <option value="">Tous les types</option>
                            @foreach (\App\Models\Incident::TYPES as $valeur => $libelle)
                                <option value="{{ $valeur }}" @selected($filtres['type'] === $valeur)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn w-100"><i class="ti ti-filter me-1"></i> Appliquer</button>
                        @if ($filtreActif || $erreurFiltres)
                            <a href="{{ route('admin.incidents.statistiques') }}" class="btn btn-ghost-secondary" title="Effacer les filtres"><i class="ti ti-x"></i></a>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    @endif

    @if ($stats['total'] === 0)
        <div class="card">
            <div class="card-body">
                <div class="empty">
                    <div class="empty-icon"><i class="ti ti-chart-bar-off" style="font-size: 3rem"></i></div>
                    @if ($filtreActif)
                        <p class="empty-title">Aucun incident pour ces filtres</p>
                        <p class="empty-subtitle text-secondary">Élargissez la période ou choisissez un autre type d'incident.</p>
                        <div class="empty-action">
                            <a href="{{ route('admin.incidents.statistiques') }}" class="btn"><i class="ti ti-x me-1"></i> Effacer les filtres</a>
                        </div>
                    @else
                        <p class="empty-title">Pas encore de statistiques</p>
                        <p class="empty-subtitle text-secondary">Aucun incident n'est enregistré : les graphiques apparaîtront dès le premier incident.</p>
                        <div class="empty-action">
                            <a href="{{ route('admin.incidents.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Ajouter un incident</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        @php
            $jours = fn ($valeur) => $valeur === null ? '—' : number_format($valeur['jours'], 1, ',', ' ') . ' j';
        @endphp

        {{-- Indicateurs clés --}}
        <div class="row row-deck row-cards mb-3">
            @foreach ([
                ['alert-triangle',   'azure',  $stats['total'], 'Incidents', null],
                ['circle-check',     'green',  $stats['tauxResolution'] === null ? '—' : number_format($stats['tauxResolution'], 1, ',', ' ') . ' %', 'Taux de résolution', 'Incidents dont toutes les interventions sont terminées'],
                ['clock-play',       'blue',   $jours($stats['delaiPriseEnCharge']), 'Délai moyen de prise en charge',
                    $stats['delaiPriseEnCharge'] ? 'Du signalement à la 1re intervention commencée · ' . $stats['delaiPriseEnCharge']['base'] . ' incident(s)' : 'Aucune intervention commencée pour l\'instant'],
                ['clock-check',      'teal',   $jours($stats['dureeResolution']), 'Durée moyenne de résolution',
                    $stats['dureeResolution'] ? 'Du signalement à la dernière intervention · ' . $stats['dureeResolution']['base'] . ' incident(s) résolu(s)' : 'Aucun incident résolu pour l\'instant'],
                ['clock-exclamation','orange', $stats['interventionsEnRetard'], 'Interventions en retard', 'Planifiées, date passée, non démarrées'],
                ['urgent',           'red',    $stats['critiquesSansIntervention'], 'Critiques sans intervention', 'Depuis au moins ' . \App\Models\Incident::JOURS_ALERTE_CRITIQUE . ' jour'],
            ] as [$icone, $couleur, $valeur, $libelle, $aide])
                <div class="col-sm-6 col-lg-4 col-xl-2">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <span class="avatar avatar-sm bg-{{ $couleur }} text-white me-2"><i class="ti ti-{{ $icone }}"></i></span>
                                <div class="h2 mb-0" style="font-variant-numeric: tabular-nums">{{ $valeur }}</div>
                            </div>
                            <div class="fw-medium">{{ $libelle }}</div>
                            @if ($aide)<div class="small text-secondary">{{ $aide }}</div>@endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row row-cards">
            @foreach ([
                ['graphique-types',     'Incidents par type',     'parType'],
                ['graphique-gravites',  'Incidents par gravité',  'parGravite'],
                ['graphique-statuts',   'Incidents par statut',   'parStatut'],
            ] as [$id, $titre, $cle])
                <div class="col-md-6 col-xl-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">{{ $titre }}</h3></div>
                        <div class="card-body">
                            <div id="{{ $id }}" style="min-height: 200px"></div>
                            <details class="mt-2 small">
                                <summary class="text-secondary">Voir les données</summary>
                                <table class="table table-sm mt-2 mb-0">
                                    @foreach ($stats[$cle] as $ligne)
                                        <tr><td>{{ $ligne['libelle'] }}</td><td class="text-end" style="font-variant-numeric: tabular-nums">{{ $ligne['nombre'] }}</td></tr>
                                    @endforeach
                                </table>
                            </details>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Évolution mensuelle</h3>
                            <div class="card-subtitle">
                                Incidents signalés chaque mois, dont critiques ·
                                {{ ($filtres['du'] || $filtres['au']) ? 'mois de la période choisie (' . \App\Services\StatistiquesIncidents::MOIS_MAX . ' au plus)' : '12 derniers mois' }}.
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="graphique-evolution" style="min-height: 260px"></div>
                        <details class="mt-2 small">
                            <summary class="text-secondary">Voir les données</summary>
                            <div class="table-responsive">
                                <table class="table table-sm mt-2 mb-0">
                                    <thead><tr><th>Mois</th><th class="text-end">Incidents</th><th class="text-end">Dont critiques</th></tr></thead>
                                    @foreach ($stats['evolution'] as $ligne)
                                        <tr>
                                            <td>{{ $ligne['mois'] }}</td>
                                            <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $ligne['total'] }}</td>
                                            <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $ligne['critiques'] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        </details>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Interventions par statut</h3></div>
                    <div class="card-body">
                        <div id="graphique-interventions" style="min-height: 260px"></div>
                        <details class="mt-2 small">
                            <summary class="text-secondary">Voir les données</summary>
                            <table class="table table-sm mt-2 mb-0">
                                @foreach ($stats['interventions'] as $ligne)
                                    <tr><td>{{ $ligne['libelle'] }}</td><td class="text-end" style="font-variant-numeric: tabular-nums">{{ $ligne['nombre'] }}</td></tr>
                                @endforeach
                            </table>
                        </details>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Zones les plus touchées</h3>
                            <div class="card-subtitle">Les 10 zones qui comptent le plus d'incidents (tous statuts).</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="graphique-zones" style="min-height: 240px"></div>
                        <details class="mt-2 small">
                            <summary class="text-secondary">Voir les données</summary>
                            <table class="table table-sm mt-2 mb-0">
                                @foreach ($stats['parZone'] as $ligne)
                                    <tr><td>{{ $ligne['libelle'] }}</td><td class="text-end" style="font-variant-numeric: tabular-nums">{{ $ligne['nombre'] }}</td></tr>
                                @endforeach
                            </table>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@if ($stats['total'] > 0)
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
        <script>
            (function () {
                const stats = @json($stats);
                const encre = { principale: '#0b0b0b', muette: '#898781', grille: '#e1e0d9', axe: '#c3c2b7' };
                const bleu = '#2a78d6';
                // Couleurs de statut, toujours accompagnées de leur libellé
                const couleurs = {
                    faible: '#f59f00', moyenne: '#f76707', critique: '#d63939',
                    non_traite: '#d63939', en_traitement: '#4299e1', resolu: '#2fb344',
                    planifiee: '#206bc4', en_cours: '#f76707', terminee: '#2fb344',
                };

                const base = {
                    chart: { fontFamily: 'inherit', toolbar: { show: false }, animations: { enabled: false }, parentHeightOffset: 0 },
                    grid: { borderColor: encre.grille, strokeDashArray: 0 },
                    tooltip: { theme: 'light' },
                    legend: { show: false },
                };
                const axeTexte = { style: { colors: encre.muette } };

                // Barres horizontales, valeur au bout de la barre
                function barres(id, lignes, couleursBarres, hauteur = 200) {
                    new ApexCharts(document.getElementById(id), {
                        ...base,
                        chart: { ...base.chart, type: 'bar', height: hauteur },
                        series: [{ name: 'Nombre', data: lignes.map(l => l.nombre) }],
                        colors: couleursBarres,
                        plotOptions: { bar: {
                            horizontal: true, barHeight: '45%', borderRadius: 4, borderRadiusApplication: 'end',
                            distributed: couleursBarres.length > 1, dataLabels: { position: 'top' },
                        } },
                        dataLabels: { enabled: true, offsetX: 22, style: { colors: [encre.principale], fontWeight: 500 } },
                        xaxis: { categories: lignes.map(l => l.libelle), labels: { ...axeTexte, formatter: v => Math.round(v) }, axisBorder: { color: encre.axe }, tickAmount: 4 },
                        yaxis: { labels: { style: { colors: encre.principale } } },
                        grid: { ...base.grid, yaxis: { lines: { show: false } } },
                    }).render();
                }

                barres('graphique-types', stats.parType, [bleu]);
                barres('graphique-gravites', stats.parGravite, stats.parGravite.map(l => couleurs[l.cle]));
                barres('graphique-statuts', stats.parStatut, stats.parStatut.map(l => couleurs[l.cle]));
                barres('graphique-interventions', stats.interventions, stats.interventions.map(l => couleurs[l.cle]), 260);
                if (stats.parZone.length) {
                    barres('graphique-zones', stats.parZone, [bleu], Math.max(160, stats.parZone.length * 34));
                }

                // Courbes : incidents par mois, dont critiques
                new ApexCharts(document.getElementById('graphique-evolution'), {
                    ...base,
                    chart: { ...base.chart, type: 'line', height: 260 },
                    series: [
                        { name: 'Incidents', data: stats.evolution.map(m => m.total) },
                        { name: 'Dont critiques', data: stats.evolution.map(m => m.critiques) },
                    ],
                    colors: [bleu, couleurs.critique],
                    stroke: { width: 2, curve: 'straight', lineCap: 'round' },
                    markers: { size: 4, strokeColors: '#ffffff', strokeWidth: 2, hover: { size: 6 } },
                    legend: { show: true, position: 'top', horizontalAlign: 'left', labels: { colors: encre.principale } },
                    dataLabels: { enabled: false },
                    tooltip: { ...base.tooltip, shared: true, intersect: false },
                    xaxis: { categories: stats.evolution.map(m => m.mois), labels: axeTexte, axisBorder: { color: encre.axe }, crosshairs: { show: true } },
                    yaxis: { labels: { ...axeTexte, formatter: v => Math.round(v) }, min: 0, forceNiceScale: true },
                }).render();
            })();
        </script>
    @endpush
@endif
