@extends('layouts.admin')

@section('title', 'Modèles IA')
@section('pretitle', 'Rénovation · Intelligence artificielle entraînée sur les données')
@section('subtitle', 'Deux modèles appris sur les devis et les financements : prédire le coût d\'un projet, recommander la source de financement.')

@section('actions')
    <form method="POST" action="{{ route('admin.renovation.modeles.entrainer') }}">
        @csrf
        <button type="button" class="btn btn-primary"
                data-bs-toggle="modal" data-bs-target="#modal-confirm" data-confirm-type="primary"
                data-confirm-title="Réentraîner les modèles ?" data-confirm-message="Les deux modèles sont recalculés sur les données actuelles (quelques secondes). Aucune donnée n'est modifiée."
                data-confirm-button="Réentraîner">
            <i class="ti ti-refresh me-1"></i> {{ $cout || $source ? 'Réentraîner' : 'Entraîner' }} les modèles
        </button>
    </form>
@endsection

@use('App\Models\Financement')
@use('App\Models\ReseauEau')
@use('App\Services\Renovation\ModeleCoutAppris')
@use('App\Services\Renovation\ModeleSourceAppris')
@php
    $dt = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' DT';
    $pct = fn ($v) => number_format((float) $v, 1, ',', ' ') . ' %';
    $statut = fn ($m) => $m && $m['actif']
        ? '<span class="badge bg-green text-white"><i class="ti ti-circle-check me-1"></i>Actif</span>'
        : '<span class="badge bg-secondary text-white"><i class="ti ti-player-pause me-1"></i>Inactif</span>';
@endphp

@section('content')
    @include('admin.renovation._nav')

    {{-- Deux familles d'IA dans le module --}}
    <div class="card mb-3 bg-indigo-lt border-0">
        <div class="card-body d-flex flex-wrap gap-3 align-items-start">
            <i class="ti ti-brain text-indigo" style="font-size: 2rem"></i>
            <div class="flex-fill" style="min-width: 260px">
                <div class="fw-bold">IA prédictive, entraînée ici</div>
                <div class="small">Ces modèles apprennent des <strong>exemples</strong> (devis, financements) et calculent une prédiction.
                    Codés en PHP sans bibliothèque, évalués par validation croisée et comparés à une méthode simple ;
                    ils ne sont utilisés que s'ils font mieux qu'elle.</div>
            </div>
            <div class="flex-fill" style="min-width: 260px">
                <div class="fw-bold">À ne pas confondre avec l'IA générative</div>
                <div class="small">Celle-ci (Groq / Claude) <strong>rédige</strong> des explications à partir des résultats calculés
                    (What If?, alertes, rapport mensuel). Les deux se complètent : l'une estime, l'autre explique.</div>
            </div>
        </div>
    </div>

    @if (! $cout && ! $source)
        <div class="card">
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-brain" style="font-size: 3rem"></i></div>
                <p class="empty-title">Les modèles n'ont pas encore été entraînés</p>
                <p class="empty-subtitle text-secondary">Cliquez sur « Entraîner les modèles » (ou lancez <code>php artisan renovation:entrainer</code>).</p>
            </div>
        </div>
    @endif

    {{-- ================= Modèle 1 : coût ================= --}}
    @if ($cout)
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><span class="badge bg-indigo-lt me-2">1</span> Prédire le coût d'un projet sans devis</h3>
                <div class="card-actions">{!! $statut($cout) !!}</div>
            </div>
            <div class="card-body">
                @if ($cout['raison'])
                    <div class="alert alert-warning">{{ $cout['raison'] }}</div>
                @endif
                <div class="row g-3 mb-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-secondary small">Exemples d'entraînement</div>
                        <div class="h2 mb-0">{{ $cout['n'] }}</div>
                        <div class="small text-secondary">{{ $cout['composition']['base'] }} devis de la base + {{ $cout['composition']['synthetique'] }} historiques synthétiques</div>
                    </div>
                    @isset($cout['retenu'])
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-secondary small">Algorithme retenu</div>
                            <div class="h3 mb-0">{{ ModeleCoutAppris::ALGORITHMES[$cout['retenu']['algorithme']] }}</div>
                            <div class="small text-secondary">{{ $cout['retenu']['algorithme'] === 'ridge' ? 'λ' : 'k' }} = {{ $cout['retenu']['parametre'] }}</div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-secondary small">Erreur relative moyenne <x-renovation.origine type="calcule" /></div>
                            <div class="h2 mb-0 text-indigo">{{ $pct($cout['retenu']['mape']) }}</div>
                            <div class="small text-secondary">barème actuel : {{ $pct($cout['bareme']['mape']) }}</div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-secondary small">Gain sur le barème</div>
                            <div class="h2 mb-0 text-{{ $cout['gain_pct'] > 0 ? 'green' : 'red' }}">{{ $cout['gain_pct'] > 0 ? '−' : '+' }}{{ $pct(abs($cout['gain_pct'])) }}</div>
                            <div class="small text-secondary">d'erreur (validation croisée)</div>
                        </div>
                    @endisset
                </div>

                @isset($cout['retenu'])
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="fw-medium mb-1">Prédit (validation croisée) / coût réel</div>
                            <div id="graphique-dispersion" style="min-height: 320px"></div>
                            <div class="small text-secondary">Chaque point : un devis prédit par un modèle qui ne l'a jamais vu. Plus les points sont près de la diagonale, meilleur est le modèle.</div>
                        </div>
                        <div class="col-lg-5">
                            <div class="fw-medium mb-1">Méthodes comparées</div>
                            <div id="graphique-candidats" style="min-height: 240px"></div>
                            @if ($cout['influences'])
                                <div class="fw-medium mt-3 mb-1">Ce qui fait varier le coût (régression)</div>
                                @foreach (array_slice($cout['influences'], 0, 7) as $inf)
                                    <div class="d-flex align-items-center gap-2 small mb-1">
                                        <span class="text-truncate" style="width: 55%">{{ $inf['variable'] }}</span>
                                        <div class="flex-fill progress progress-sm"><div class="progress-bar bg-{{ $inf['effet_pct'] > 0 ? 'red' : 'green' }}" style="width: {{ min(100, abs($inf['effet_pct']) * 2) }}%"></div></div>
                                        <span class="text-nowrap {{ $inf['effet_pct'] > 0 ? 'text-red' : 'text-green' }}">{{ $inf['effet_pct'] > 0 ? '+' : '' }}{{ number_format($inf['effet_pct'], 1, ',', ' ') }} %</span>
                                    </div>
                                @endforeach
                                <div class="small text-secondary">Effet sur le coût d'une hausse d'un écart-type de la variable.</div>
                            @endif
                        </div>
                    </div>

                    @if ($cout['sur_la_base'])
                        @php $b = $cout['sur_la_base']; @endphp
                        <div class="alert alert-info mt-3 mb-0">
                            <div class="fw-bold"><i class="ti ti-scale me-1"></i>Contrôle d'honnêteté : sur les {{ $b['n'] }} devis de la base uniquement</div>
                            Modèle entraîné sans eux : {{ $pct($b['modele']['mape']) }} d'erreur relative · barème : {{ $pct($b['bareme']['mape']) }}.
                            @if ($b['modele']['mape'] >= $b['bareme']['mape'])
                                Sur ces devis-là, le modèle ne fait pas mieux : ce sont des données de démonstration saisies à la main, sans la régularité
                                de l'historique. Avec de vrais devis en nombre suffisant, mettez <code>RENOVATION_JEU_SYNTHETIQUE=false</code> et réentraînez.
                            @else
                                Le modèle fait aussi mieux que le barème sur les devis réels de la base.
                            @endif
                        </div>
                    @endif
                @endisset
            </div>
            <div class="card-footer small text-secondary">
                Entraîné le {{ \Illuminate\Support\Carbon::parse($cout['entraine_le'])->format('d/m/Y à H:i') }} en {{ $cout['duree_ms'] }} ms ·
                variables : {{ implode(', ', $cout['variables']) }} · utilisé pour les projets sans devis (fiche projet, What If?, score), avec sa marge d'erreur.
            </div>
        </div>
    @endif

    {{-- ================= Modèle 2 : source ================= --}}
    @if ($source)
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><span class="badge bg-indigo-lt me-2">2</span> Recommander la source de financement à solliciter</h3>
                <div class="card-actions">{!! $statut($source) !!}</div>
            </div>
            <div class="card-body">
                @if ($source['raison'])
                    <div class="alert alert-warning">{{ $source['raison'] }}</div>
                @endif
                <div class="row g-3 mb-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-secondary small">Exemples d'entraînement</div>
                        <div class="h2 mb-0">{{ $source['n'] }}</div>
                        <div class="small text-secondary">{{ $source['composition']['base'] }} financements de la base + {{ $source['composition']['synthetique'] }} synthétiques</div>
                    </div>
                    @isset($source['exactitude'])
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-secondary small">Bonne source en 1er choix <x-renovation.origine type="calcule" /></div>
                            <div class="h2 mb-0 text-indigo">{{ $pct($source['exactitude']) }}</div>
                            <div class="small text-secondary">référence « toujours {{ Financement::libelleSourceDe($source['reference']['source']) }} » : {{ $pct($source['reference']['exactitude']) }}</div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-secondary small">Bonne source dans les 2 premiers choix</div>
                            <div class="h2 mb-0">{{ $pct($source['exactitude_top2']) }}</div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-secondary small">Algorithme</div>
                            <div class="h3 mb-0">Bayésien naïf</div>
                            <div class="small text-secondary">variables : {{ implode(', ', ModeleSourceAppris::VARIABLES) }}</div>
                        </div>
                    @endisset
                </div>

                @isset($source['matrice'])
                    @php $classes = array_keys($source['repartition']); @endphp
                    <div class="fw-medium mb-1">Matrice de confusion (validation croisée)</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered text-center mb-1" style="max-width: 640px">
                            <thead><tr><th class="text-start">Vraie source ↓ / prédite →</th>
                                @foreach ($classes as $c)<th>{{ Financement::libelleSourceDe($c) }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($classes as $vraie)
                                    <tr>
                                        <td class="text-start fw-medium">{{ Financement::libelleSourceDe($vraie) }}</td>
                                        @foreach ($classes as $predite)
                                            @php $v = $source['matrice'][$vraie][$predite] ?? 0; @endphp
                                            <td class="{{ $vraie === $predite && $v ? 'bg-green-lt fw-bold' : '' }}">{{ $v }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-secondary">En vert : recommandations justes. Utilisé sur la fiche des projets non terminés et pour suggérer la source à l'approbation d'un scénario.</div>
                @endisset
            </div>
            <div class="card-footer small text-secondary">
                Entraîné le {{ \Illuminate\Support\Carbon::parse($source['entraine_le'])->format('d/m/Y à H:i') }} en {{ $source['duree_ms'] }} ms.
            </div>
        </div>
    @endif

    {{-- ================= Essai en direct ================= --}}
    @if ($cout || $source)
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-flask me-1"></i> Essayer les modèles</h3></div>
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end mb-3">
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            @foreach (ReseauEau::TYPES as $v => $l)<option value="{{ $v }}" @selected(($essai['brut']['type'] ?? 'canalisation') === $v)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Matériau</label>
                        <select name="materiau" class="form-select">
                            @foreach (ReseauEau::MATERIAUX as $v => $l)<option value="{{ $v }}" @selected(($essai['brut']['materiau'] ?? 'fonte') === $v)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Âge</label>
                        <input type="number" name="age" min="0" max="120" class="form-control" value="{{ $essai['brut']['age'] ?? 45 }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">État</label>
                        <select name="etat" class="form-select">
                            @foreach (ReseauEau::ETATS as $v => $l)<option value="{{ $v }}" @selected(($essai['brut']['etat'] ?? 'degrade') === $v)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Population</label>
                        <input type="number" name="population" min="0" step="1000" class="form-control" value="{{ $essai['brut']['population'] ?? 30000 }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Gouvernorat</label>
                        <select name="gouvernorat" class="form-select">
                            @foreach ($gouvernorats as $g)<option value="{{ $g }}" @selected(($essai['gouvernorat'] ?? 'Tunis') === $g)>{{ $g }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-1"><button class="btn btn-primary w-100"><i class="ti ti-player-play"></i></button></div>
                </form>

                @if ($essai)
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-secondary small">Coût prédit <x-renovation.origine type="predit" /></div>
                                @if ($essai['prediction'])
                                    <div class="h1 mb-0 text-indigo">{{ $dt($essai['prediction']['montant']) }}</div>
                                    <div class="small">± {{ $dt($essai['prediction']['marge']) }} (erreur moyenne du modèle) · {{ $essai['prediction']['algorithme'] }}</div>
                                @else
                                    <div class="text-secondary">Modèle inactif : le barème s'applique.</div>
                                @endif
                                <div class="small text-secondary mt-2">Barème actuel pour ce cas : {{ $dt($essai['bareme']) }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-secondary small mb-1">Source à solliciter <x-renovation.origine type="predit" /></div>
                                @if ($essai['source'])
                                    @foreach ($essai['source']['probabilites'] as $s => $p)
                                        <div class="d-flex small"><span class="{{ $loop->first ? 'fw-bold' : '' }}">{{ Financement::libelleSourceDe($s) }}</span><span class="ms-auto">{{ number_format($p, 0) }} %</span></div>
                                        <div class="progress progress-xs mb-1"><div class="progress-bar bg-{{ Financement::couleurSourceDe($s) }}" style="width: {{ $p }}%"></div></div>
                                    @endforeach
                                    <div class="small text-secondary mt-1">{{ $essai['source']['explication'] }}</div>
                                @else
                                    <div class="text-secondary">Modèle inactif.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Règles du jeu synthétique : affichées pour la transparence --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-file-description me-1"></i> Historique synthétique d'entraînement (données de démonstration)</h3>
            </div>
            <div class="card-body">
                <p class="small text-secondary">
                    Les devis et financements de la base de démonstration ont été saisis à la main, sans régularité : aucun modèle ne peut en apprendre.
                    Comme le module Qualité de l'eau, on ajoute donc un historique de {{ \App\Services\Renovation\Apprentissage\JeuEntrainementSynthetique::TAILLE }} projets passés,
                    généré par les règles ci-dessous avec une part de hasard (graine fixe : résultats reproductibles). <strong>Rien n'est écrit en base.</strong>
                    Le modèle doit retrouver ces relations à partir des seuls exemples, sans connaître les règles.
                </p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="fw-medium mb-1">Coût</div>
                        <ul class="small mb-0">@foreach ($regles['cout'] as $r)<li>{{ $r }}</li>@endforeach</ul>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-medium mb-1">Source de financement</div>
                        <ul class="small mb-0">@foreach ($regles['source'] as $r)<li>{{ $r }}</li>@endforeach</ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @isset($cout['retenu'])
        @php
            $pointsJs = collect($cout['validation'])->groupBy('origine')->map(fn ($l) => $l->map(fn ($v) => ['x' => $v['reel'], 'y' => $v['predit']])->values());
            $candidatsJs = collect($cout['candidats'])->map(fn ($c) => ['nom' => ($c['algorithme'] === 'ridge' ? 'Ridge λ=' : 'k-NN k=') . $c['parametre'], 'mape' => $c['mape']])
                ->push(['nom' => 'Barème actuel', 'mape' => $cout['bareme']['mape']])->values();
            $maxJs = max(array_column($cout['validation'], 'reel'));
        @endphp
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
        <script>
            (function () {
                const points = @json($pointsJs);
                const candidats = @json($candidatsJs);
                const max = @json($maxJs);
                const k = v => new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(v);

                new ApexCharts(document.getElementById('graphique-dispersion'), {
                    chart: { type: 'scatter', height: 320, fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false } },
                    series: [
                        { name: 'Historique synthétique', type: 'scatter', data: points.synthetique || [] },
                        { name: 'Devis de la base', type: 'scatter', data: points.base || [] },
                        { name: 'Prédiction parfaite', type: 'line', data: [{ x: 0, y: 0 }, { x: max, y: max }] },
                    ],
                    xaxis: { type: 'numeric', min: 0, max, title: { text: 'Coût réel (DT)' }, labels: { formatter: k } },
                    yaxis: { min: 0, max, title: { text: 'Coût prédit (DT)' }, labels: { formatter: k } },
                    colors: ['#ae3ec9', '#066fd1', '#adb5bd'],
                    stroke: { width: [0, 0, 2], dashArray: [0, 0, 6] },
                    markers: { size: [4, 6, 0] },
                    legend: { position: 'top' },
                    tooltip: { shared: false, x: { formatter: v => 'réel ' + k(v) + ' DT' }, y: { formatter: v => 'prédit ' + k(v) + ' DT' } },
                }).render();

                new ApexCharts(document.getElementById('graphique-candidats'), {
                    chart: { type: 'bar', height: 240, fontFamily: 'inherit', toolbar: { show: false } },
                    series: [{ name: 'Erreur relative moyenne', data: candidats.map(c => c.mape) }],
                    xaxis: { categories: candidats.map(c => c.nom), labels: { formatter: v => v + ' %' } },
                    plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 3 } },
                    colors: candidats.map(c => c.nom === 'Barème actuel' ? '#adb5bd' : '#4263eb'),
                    legend: { show: false },
                    dataLabels: { enabled: true, formatter: v => v + ' %' },
                    tooltip: { y: { formatter: v => v + ' % d\'erreur relative' } },
                }).render();
            })();
        </script>
    @endisset
@endpush
