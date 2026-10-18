@extends('layouts.admin')

@section('title', 'IA — Analyse intelligente des incidents')
@section('pretitle', 'Exploitation · Intelligence artificielle')
@section('subtitle', "Détection d'observations inhabituelles (réseau × mois) par un modèle non supervisé : Isolation Forest.")

@section('actions')
    <form method="POST" action="{{ route('admin.incidents.ia.lancer') }}">
        @csrf
        <button type="button" class="btn btn-primary"
                data-bs-toggle="modal" data-bs-target="#modal-confirm"
                data-confirm-type="primary"
                data-confirm-title="Lancer une nouvelle analyse ?"
                data-confirm-message="Les incidents et interventions sont lus sans être modifiés. Le résultat remplace la dernière analyse enregistrée."
                data-confirm-button="Lancer l'analyse">
            <i class="ti ti-player-play me-1"></i> Lancer l'analyse
        </button>
    </form>
@endsection

@section('content')
@php
    $nombre = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', ' '), '0'), ',');
    $termine = ($analyse['statut'] ?? null) === 'termine';
@endphp

@include('admin.incidents._navigation')

<div class="alert alert-info">
    <div class="d-flex">
        <i class="ti ti-info-circle me-2" style="font-size: 1.25rem"></i>
        <div>
            <strong>À lire avant d'interpréter :</strong> ce modèle d'apprentissage <em>non supervisé</em> signale des
            combinaisons de valeurs <strong>statistiquement inhabituelles</strong>. Une observation signalée n'est pas un incident
            confirmé et ne prouve ni fuite ni contamination. Aucune donnée n'est modifiée automatiquement.
            Ce module est distinct du <a href="{{ route('admin.incidents.priorites') }}" class="alert-link">score de priorité</a>, qui applique des règles métier fixes.
        </div>
    </div>
</div>

@if (! $analyse)
    <div class="card">
        <div class="card-body">
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-brain" style="font-size: 3rem"></i></div>
                <p class="empty-title">Aucune analyse n'a encore été lancée</p>
                <p class="empty-subtitle text-secondary">
                    Lancez l'analyse : elle commence par vérifier que les données sont suffisantes.
                    Si elles ne le sont pas, aucun résultat n'est produit.
                </p>
                <div class="empty-action">
                    <form method="POST" action="{{ route('admin.incidents.ia.lancer') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary"><i class="ti ti-player-play me-1"></i> Lancer l'analyse</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@else
    {{-- Résumé de la dernière analyse --}}
    <div class="row row-deck row-cards mb-3">
        @foreach ([
            ['clock',          'azure',  \Illuminate\Support\Carbon::parse($analyse['date'])->format('d/m/Y H:i'), 'Dernière analyse'],
            ['calendar',       'blue',   $analyse['periode']['debut'] ? \Illuminate\Support\Carbon::parse($analyse['periode']['debut'])->format('m/Y') . ' → ' . \Illuminate\Support\Carbon::parse($analyse['periode']['fin'])->format('m/Y') : '—', 'Période analysée'],
            ['table',          'teal',   $analyse['observations'], 'Observations réseau × mois'],
            ['alert-octagon',  'red',    $termine ? $analyse['signalees'] : '—', 'Observations inhabituelles'],
            ['arrows-shuffle', 'purple', $termine ? number_format($analyse['stabilite']['moyenne'], 2, ',', '') : '—', 'Stabilité (Jaccard moyen)'],
        ] as [$icone, $couleur, $valeur, $libelle])
            <div class="col-sm-6 col-lg">
                <div class="card card-sm">
                    <div class="card-body d-flex align-items-center">
                        <span class="avatar bg-{{ $couleur }} text-white me-3"><i class="ti ti-{{ $icone }}"></i></span>
                        <div>
                            <div class="h3 mb-0">{{ $valeur }}</div>
                            <div class="text-secondary small">{{ $libelle }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if (! $termine)
        {{-- Données insuffisantes : le modèle n'a pas été lancé --}}
        <div class="card mb-3 border-warning">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-database-exclamation me-1 text-warning"></i> Données insuffisantes : le modèle n'a pas été lancé</h3>
            </div>
            <div class="card-body">
                <p class="text-secondary">
                    Avec trop peu d'observations, un modèle signalerait des « anomalies » sans valeur.
                    L'analyse sera possible quand tous les critères ci-dessous seront remplis.
                </p>
                <div class="table-responsive">
                    <table class="table table-vcenter mb-0">
                        <thead><tr><th>Critère</th><th class="text-end">Valeur actuelle</th><th class="text-end">Minimum</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($analyse['criteres'] as $critere)
                                <tr>
                                    <td>{{ $critere['libelle'] }}</td>
                                    <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $critere['valeur'] }}</td>
                                    <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $critere['minimum'] }}</td>
                                    <td class="w-1">
                                        @if ($critere['ok'])
                                            <span class="badge bg-green-lt"><i class="ti ti-check me-1"></i>Rempli</span>
                                        @else
                                            <span class="badge bg-red-lt"><i class="ti ti-x me-1"></i>Manquant</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="row row-cards mb-3">
            {{-- Observations signalées --}}
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Observations inhabituelles</h3>
                            <div class="card-subtitle">Score ≥ {{ $nombre($analyse['parametres']['seuil']) }} · forte si ≥ {{ $nombre($analyse['parametres']['seuil_fort']) }} · triées par score</div>
                        </div>
                        <div class="card-actions">
                            <form method="GET" class="d-flex gap-2">
                                <select name="reseau" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filtrer par réseau">
                                    <option value="">Tous les réseaux</option>
                                    @foreach ($reseaux as $code)
                                        <option value="{{ $code }}" @selected($reseau === $code)>{{ $code }}</option>
                                    @endforeach
                                </select>
                                <select name="niveau" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filtrer par niveau">
                                    <option value="">Tous niveaux</option>
                                    <option value="forte" @selected($niveau === 'forte')>Fortes</option>
                                    <option value="moderee" @selected($niveau === 'moderee')>Modérées</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    @forelse ($anomalies as $anomalie)
                        @php
                            $debutMois = $anomalie['mois'] . '-01';
                            $finMois = \Illuminate\Support\Carbon::parse($debutMois)->endOfMonth()->toDateString();
                        @endphp
                        <div class="card-body border-bottom">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="badge {{ $anomalie['niveau'] === 'forte' ? 'bg-red text-red-fg' : 'bg-orange text-orange-fg' }}">
                                    Score {{ number_format($anomalie['score'], 2, ',', '') }} · {{ $anomalie['niveau'] === 'forte' ? 'forte' : 'modérée' }}
                                </span>
                                <strong class="font-monospace">{{ $anomalie['reseau'] }}</strong>
                                <span class="text-secondary">{{ $anomalie['zone'] }} · {{ $anomalie['mois_libelle'] }}</span>
                                <a href="{{ route('admin.incidents.index', ['reseau' => $anomalie['reseau_id'], 'du' => $debutMois, 'au' => $finMois]) }}" class="ms-auto small">
                                    <i class="ti ti-list me-1"></i>Incidents de ce mois
                                </a>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="small text-secondary text-uppercase fw-bold mb-1">Pourquoi ce signalement</div>
                                    @if (empty($anomalie['explications']))
                                        <div class="small text-secondary">Aucune variable ne s'écarte fortement seule : c'est leur combinaison qui est rare.</div>
                                    @else
                                        <ul class="small mb-0 ps-3">
                                            @foreach ($anomalie['explications'] as $explication)
                                                <li>{{ $explication['texte'] }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    @if ($anomalie['delai_impute'])
                                        <div class="small text-secondary mt-1"><i class="ti ti-info-circle me-1"></i>Délai : aucune intervention commencée ce mois-là, valeur médiane utilisée.</div>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <div class="small text-secondary text-uppercase fw-bold mb-1">Recommandations</div>
                                    <ul class="small mb-0 ps-3">
                                        @foreach ($anomalie['recommandations'] as $recommandation)
                                            <li>{{ $recommandation }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="card-body">
                            <div class="empty">
                                <div class="empty-icon"><i class="ti ti-circle-check" style="font-size: 3rem"></i></div>
                                <p class="empty-title">{{ ($reseau || $niveau) ? 'Aucune observation pour ces filtres' : 'Aucune observation inhabituelle' }}</p>
                                <p class="empty-subtitle text-secondary">
                                    {{ ($reseau || $niveau) ? 'Modifiez les filtres.' : "Aucun réseau n'a de mois atteignant le seuil de score sur la période analysée." }}
                                </p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Graphiques --}}
            <div class="col-xl-4">
                <div class="row row-cards">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <h3 class="card-title">Répartition des scores</h3>
                                    <div class="card-subtitle">Toutes les observations ; en rouge, les tranches signalées.</div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="graphique-scores" style="min-height: 220px"></div>
                                <details class="mt-2 small">
                                    <summary class="text-secondary">Voir les données</summary>
                                    <table class="table table-sm mt-2 mb-0">
                                        @foreach ($analyse['distribution'] as $classe)
                                            <tr><td>{{ $classe['libelle'] }}</td><td class="text-end" style="font-variant-numeric: tabular-nums">{{ $classe['nombre'] }}</td></tr>
                                        @endforeach
                                    </table>
                                </details>
                            </div>
                        </div>
                    </div>

                    @if (! empty($analyse['series']['reseaux']))
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Tendance d'un réseau</h3>
                                    <div class="card-actions">
                                        <select id="choix-serie" class="form-select form-select-sm" aria-label="Choisir un réseau">
                                            @foreach (array_keys($analyse['series']['reseaux']) as $code)
                                                <option value="{{ $code }}" @selected($reseau === $code)>{{ $code }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="graphique-serie" style="min-height: 240px"></div>
                                    <div class="small text-secondary">Barres : incidents du mois · courbe : score d'anomalie (seuil {{ $nombre($analyse['parametres']['seuil']) }}).</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="row row-cards">
        {{-- Audit des données réellement disponibles --}}
        <div class="col-lg-6">
            @php $d = $analyse['diagnostic']; @endphp
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-database-search me-1"></i> Données disponibles</h3></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-7 fw-normal text-secondary">Incidents · interventions</dt>
                        <dd class="col-5">{{ $d['incidents'] }} · {{ $d['interventions'] }}</dd>
                        <dt class="col-7 fw-normal text-secondary">Premier / dernier signalement</dt>
                        <dd class="col-5">{{ $d['premier'] ? \Illuminate\Support\Carbon::parse($d['premier'])->format('d/m/Y') : '—' }} / {{ $d['dernier'] ? \Illuminate\Support\Carbon::parse($d['dernier'])->format('d/m/Y') : '—' }}</dd>
                        <dt class="col-7 fw-normal text-secondary">Mois couverts</dt>
                        <dd class="col-5">{{ $d['mois_couverts'] }}</dd>
                        <dt class="col-7 fw-normal text-secondary">Réseaux avec incidents</dt>
                        <dd class="col-5">{{ $d['reseaux_avec_incidents'] }} sur {{ $d['reseaux_total'] }} (max. {{ $d['max_incidents_par_reseau'] }} par réseau)</dd>
                        <dt class="col-7 fw-normal text-secondary">Types</dt>
                        <dd class="col-5">{{ collect($d['par_type'])->map(fn ($t) => $t['libelle'] . ' ' . $t['nombre'])->implode(' · ') }}</dd>
                        <dt class="col-7 fw-normal text-secondary">Gravités</dt>
                        <dd class="col-5">{{ collect($d['par_gravite'])->map(fn ($g) => $g['libelle'] . ' ' . $g['nombre'])->implode(' · ') }}</dd>
                        <dt class="col-7 fw-normal text-secondary">Incidents avec délai mesurable</dt>
                        <dd class="col-5">{{ $d['incidents_avec_delai'] }} ({{ $d['incidents_sans_intervention'] }} sans intervention)</dd>
                        <dt class="col-7 fw-normal text-secondary">Relevés de capteurs (hors seuil)</dt>
                        <dd class="col-5 mb-0">{{ $d['capteurs_disponibles'] ? $d['releves'] . ' (' . $d['releves_hors_seuil'] . ')' : 'non disponibles' }}</dd>
                    </dl>
                </div>
                <div class="card-footer small text-secondary">
                    <i class="ti ti-alert-triangle me-1"></i>
                    Si la base a été remplie avec les données de démonstration (IncidentSeeder), ces chiffres et les résultats portent sur des données <strong>fictives</strong>.
                </div>
            </div>
        </div>

        {{-- Méthode et limites --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-microscope me-1"></i> Méthode et limites</h3></div>
                <div class="card-body small">
                    <p class="mb-2">
                        <strong>{{ $analyse['modele'] }}</strong> · {{ $analyse['parametres']['arbres'] }} arbres ·
                        échantillon de {{ $analyse['parametres']['echantillon'] }} observations · graine {{ $analyse['parametres']['graine'] }}.
                        Une observation = un réseau pendant un mois.
                    </p>
                    <div class="fw-bold mb-1">Variables utilisées</div>
                    <ul class="ps-3 mb-2">
                        @foreach ($analyse['variables'] as $variable)
                            <li>{{ $variable['libelle'] }}</li>
                        @endforeach
                    </ul>
                    <div class="fw-bold mb-1">Évaluation</div>
                    <p class="mb-2">
                        Aucune anomalie n'est étiquetée dans les données : précision, rappel et F1 <strong>ne peuvent pas être mesurés</strong>.
                        On mesure la stabilité : l'analyse est refaite avec 5 autres graines aléatoires et on compare les observations signalées
                        (indice de Jaccard, 1 = identiques).
                        @if ($termine)
                            Résultat : {{ collect($analyse['stabilite']['jaccard'])->map(fn ($j) => number_format($j, 2, ',', ''))->implode(' · ') }}.
                        @endif
                    </p>
                    <div class="fw-bold mb-1">Limites</div>
                    <ul class="ps-3 mb-0">
                        <li>Faux positifs possibles : un mois rare mais normal (travaux programmés…) peut être signalé.</li>
                        <li>Faux négatifs possibles : une dérive lente, étalée sur plusieurs mois, peut passer inaperçue.</li>
                        <li>Le délai de prise en charge n'existe que si une intervention a commencé ; sinon la médiane est utilisée.</li>
                        <li>Les relevés hors seuil viennent du module Surveillance (lecture seule) ; sans capteurs, cette variable vaut 0.</li>
                        <li>Le modèle décrit le passé de la base : il ne prédit pas les incidents futurs.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@if (($analyse['statut'] ?? null) === 'termine')
    @php
        // @json découpe son argument aux virgules : les données des graphiques sont préparées ici
        $donneesGraphiques = [
            'distribution' => $analyse['distribution'],
            'series'       => $analyse['series'],
            'seuil'        => $analyse['parametres']['seuil'],
        ];
    @endphp
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
        <script>
            (function () {
                const analyse = @json($donneesGraphiques);
                const encre = { principale: '#0b0b0b', muette: '#898781', grille: '#e1e0d9', axe: '#c3c2b7' };
                const bleu = '#2a78d6', rouge = '#d63939';
                const base = {
                    chart: { fontFamily: 'inherit', toolbar: { show: false }, animations: { enabled: false }, parentHeightOffset: 0 },
                    grid: { borderColor: encre.grille },
                    tooltip: { theme: 'light' },
                };
                const axeTexte = { style: { colors: encre.muette } };

                // Répartition des scores : tranches signalées en rouge
                new ApexCharts(document.getElementById('graphique-scores'), {
                    ...base,
                    chart: { ...base.chart, type: 'bar', height: 220 },
                    series: [{ name: 'Observations', data: analyse.distribution.map(c => c.nombre) }],
                    colors: analyse.distribution.map(c => c.signalee ? rouge : bleu),
                    plotOptions: { bar: { columnWidth: '60%', borderRadius: 3, distributed: true } },
                    legend: { show: false },
                    dataLabels: { enabled: false },
                    xaxis: { categories: analyse.distribution.map(c => c.libelle), labels: { ...axeTexte, rotate: -45 }, axisBorder: { color: encre.axe } },
                    yaxis: { labels: { ...axeTexte, formatter: v => Math.round(v) } },
                }).render();

                // Tendance d'un réseau : incidents (barres) et score (courbe)
                const zone = document.getElementById('graphique-serie');
                const choix = document.getElementById('choix-serie');
                if (!zone || !choix) return;
                let graphique = null;

                function afficher(code) {
                    const serie = analyse.series.reseaux[code];
                    if (graphique) graphique.destroy();
                    graphique = new ApexCharts(zone, {
                        ...base,
                        chart: { ...base.chart, type: 'line', height: 240 },
                        series: [
                            { name: 'Incidents', type: 'column', data: serie.incidents },
                            { name: "Score d'anomalie", type: 'line', data: serie.scores },
                        ],
                        colors: [bleu, rouge],
                        stroke: { width: [0, 2] },
                        markers: { size: [0, 3] },
                        legend: { show: true, position: 'top', horizontalAlign: 'left', labels: { colors: encre.principale } },
                        dataLabels: { enabled: false },
                        xaxis: { categories: analyse.series.mois, labels: axeTexte, axisBorder: { color: encre.axe } },
                        yaxis: [
                            { title: { text: 'Incidents' }, labels: { ...axeTexte, formatter: v => Math.round(v) }, min: 0 },
                            { opposite: true, title: { text: 'Score' }, min: 0, max: 1, labels: { ...axeTexte, formatter: v => v.toFixed(1) } },
                        ],
                        annotations: { yaxis: [{ y: analyse.seuil, yAxisIndex: 1, borderColor: rouge, strokeDashArray: 4, label: { text: 'seuil', style: { color: '#fff', background: rouge } } }] },
                        tooltip: { ...base.tooltip, shared: true, intersect: false },
                    });
                    graphique.render();
                }

                choix.addEventListener('change', () => afficher(choix.value));
                afficher(choix.value);
            })();
        </script>
    @endpush
@endif
