@extends('layouts.admin')

@section('title', 'Détection de contamination')
@section('pretitle', "Qualité de l'eau · Intelligence artificielle")

@section('actions')
    <form method="POST" action="{{ route('admin.detection.entrainer') }}">
        @csrf
        <button type="button" class="btn btn-primary"
                data-bs-toggle="modal" data-bs-target="#modal-confirm"
                data-confirm-type="primary"
                data-confirm-title="Réentraîner le modèle ?"
                data-confirm-message="L'arbre de décision va être reconstruit à partir de toutes les analyses enregistrées."
                data-confirm-button="Oui, réentraîner">
            <i class="ti ti-refresh me-1"></i> {{ $modele ? 'Réentraîner' : 'Entraîner' }} le modèle
        </button>
    </form>
@endsection

@section('content')
@php
    $pourcent = fn ($v) => $v === null ? '—' : number_format($v * 100, 1, ',', ' ') . ' %';
@endphp

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row row-cards">

    {{-- Modèle de détection précoce de contamination --}}
    <div class="col-12">
        <div class="card">
            <div class="card-status-top bg-primary"></div>
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i class="ti ti-binary-tree me-1"></i> Détection précoce de contamination bactérienne</h3>
                    <div class="card-subtitle">
                        Arbre de décision (algorithme CART, indice de Gini), codé en PHP sans bibliothèque.
                        Il estime la probabilité de présence de coliformes à partir des mesures faites sur place
                        (pH, chlore, turbidité, nitrates), du type de point et de la saison, <strong>sans attendre les 24 à 48 h de culture en laboratoire</strong>.
                    </div>
                </div>
            </div>

            @if (! $modele)
                <div class="card-body">
                    <div class="empty">
                        <div class="empty-icon"><i class="ti ti-virus-search" style="font-size: 3rem"></i></div>
                        <p class="empty-title">Le modèle n'a pas encore été entraîné</p>
                        <p class="empty-subtitle text-secondary">
                            Cliquez sur « Entraîner le modèle », ou lancez <code>php artisan qualite:entrainer</code>.
                        </p>
                    </div>
                </div>
            @else
                @php $m = $modele['metriques']; $d = $modele['donnees']; @endphp
                <div class="card-body">
                    {{-- Performances sur les analyses de test --}}
                    <div class="row g-3 mb-3">
                        @foreach ([
                            ['Rappel',     $m['rappel'],     'Part des contaminations réelles détectées par le modèle. C\'est la mesure la plus importante pour une alerte sanitaire.'],
                            ['Précision',  $m['precision'],  'Part des alertes du modèle qui étaient de vraies contaminations.'],
                            ['F1',         $m['f1'],         'Moyenne harmonique de la précision et du rappel.'],
                            ['Exactitude', $m['exactitude'], 'Part des prédictions justes (contaminé ou sain). Référence « toujours sain » : ' . $pourcent($m['reference']) . '.'],
                        ] as [$libelle, $valeur, $aide])
                            <div class="col-6 col-lg-3">
                                <div class="card card-sm h-100">
                                    <div class="card-body">
                                        <div class="text-secondary small">{{ $libelle }}</div>
                                        <div class="h1 mb-1">{{ $pourcent($valeur) }}</div>
                                        <div class="small text-secondary">{{ $aide }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="row g-3">
                        {{-- Matrice de confusion --}}
                        <div class="col-lg-5">
                            <h4>Matrice de confusion <span class="text-secondary fw-normal small">({{ $d['test'] }} analyses de test)</span></h4>
                            <table class="table table-bordered text-center mb-2">
                                <thead>
                                    <tr><th></th><th>Prédit contaminé</th><th>Prédit sain</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <th class="text-start">Vraiment contaminé</th>
                                        <td class="bg-green-lt fw-bold" title="Vrais positifs">{{ $m['matrice']['vp'] }}</td>
                                        <td class="bg-red-lt fw-bold" title="Faux négatifs : contaminations ratées">{{ $m['matrice']['fn'] }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-start">Vraiment sain</th>
                                        <td class="bg-yellow-lt fw-bold" title="Faux positifs : fausses alertes">{{ $m['matrice']['fp'] }}</td>
                                        <td class="bg-green-lt fw-bold" title="Vrais négatifs">{{ $m['matrice']['vn'] }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <small class="form-hint">
                                Alerte dès {{ \App\Services\DetectionContamination::SEUIL_ALERTE * 100 }} % de probabilité : on préfère une fausse alerte
                                (case jaune, un prélèvement de contrôle) à une contamination ratée (case rouge).
                            </small>

                            <h4 class="mt-4">Entraînement</h4>
                            <dl class="row small mb-0">
                                <dt class="col-6 fw-normal text-secondary">Entraîné le</dt>
                                <dd class="col-6">{{ \Illuminate\Support\Carbon::parse($modele['entraineLe'])->format('d/m/Y à H:i') }}</dd>
                                <dt class="col-6 fw-normal text-secondary">Analyses utilisées</dt>
                                <dd class="col-6">{{ $d['total'] }} (dont {{ $d['positifs'] }} avec coliformes)</dd>
                                <dt class="col-6 fw-normal text-secondary">Apprentissage / test</dt>
                                <dd class="col-6">{{ $d['entrainement'] }} / {{ $d['test'] }} ({{ \App\Services\DetectionContamination::PART_TEST * 100 }} % mis de côté, tirage stratifié)</dd>
                                <dt class="col-6 fw-normal text-secondary">Profondeur maximale</dt>
                                <dd class="col-6">{{ $modele['arbre']['profondeurMax'] }} questions</dd>
                                <dt class="col-6 fw-normal text-secondary">Minimum par feuille</dt>
                                <dd class="col-6 mb-0">{{ $modele['arbre']['minParFeuille'] }} analyses</dd>
                            </dl>
                        </div>

                        {{-- Importance des variables --}}
                        <div class="col-lg-7">
                            <h4>Importance des variables</h4>
                            <div id="graphique-importances" style="min-height: 260px"></div>
                            <small class="form-hint">Part de la séparation contaminé / sain apportée par chaque variable dans l'arbre.</small>
                        </div>
                    </div>
                </div>

                {{-- Règles apprises --}}
                <div class="card-header border-top">
                    <h3 class="card-title"><i class="ti ti-list-details me-1"></i> Règles apprises par le modèle</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr><th>Si…</th><th class="text-end">Analyses</th><th>Probabilité de contamination</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($regles as $regle)
                                <tr>
                                    <td class="small">
                                        @forelse ($regle['conditions'] as $condition)
                                            <span class="badge bg-azure-lt me-1 mb-1">{{ $condition }}</span>
                                        @empty
                                            <span class="text-secondary">Toutes les analyses</span>
                                        @endforelse
                                    </td>
                                    <td class="text-end text-secondary">{{ $regle['n'] }} <span class="small">({{ $regle['positifs'] }} contaminées)</span></td>
                                    <td style="min-width: 12rem">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress progress-sm flex-grow-1">
                                                <div class="progress-bar bg-{{ $regle['niveau']['couleur'] }}" style="width: {{ $regle['proba'] * 100 }}%"></div>
                                            </div>
                                            <span class="badge bg-{{ $regle['niveau']['couleur'] }}-lt">{{ round($regle['proba'] * 100) }} %</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@if ($modele)
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
        <script>
            (function () {
                const variables = @json(collect($modele['variables'])->pluck('libelle'));
                const importances = @json($modele['arbre']['importances']).map(v => Math.round(v * 1000) / 10);
                // Variables triées de la plus à la moins importante
                const ordre = importances.map((v, i) => i).sort((a, b) => importances[b] - importances[a]);

                new ApexCharts(document.getElementById('graphique-importances'), {
                    chart: { type: 'bar', height: 260, fontFamily: 'inherit', toolbar: { show: false }, animations: { enabled: false }, parentHeightOffset: 0 },
                    series: [{ name: 'Importance', data: ordre.map(i => importances[i]) }],
                    colors: ['#2a78d6'],
                    plotOptions: { bar: { horizontal: true, barHeight: '55%', borderRadius: 4, borderRadiusApplication: 'end', dataLabels: { position: 'top' } } },
                    dataLabels: { enabled: true, offsetX: 26, formatter: v => v + ' %', style: { colors: ['#0b0b0b'], fontWeight: 500 } },
                    xaxis: { categories: ordre.map(i => variables[i]), max: 100, labels: { formatter: v => Math.round(v) + ' %', style: { colors: '#898781' } } },
                    yaxis: { labels: { style: { colors: '#0b0b0b' } } },
                    grid: { borderColor: '#e1e0d9', yaxis: { lines: { show: false } } },
                    tooltip: { y: { formatter: v => v + ' %' } },
                }).render();
            })();
        </script>
    @endpush
@endif
