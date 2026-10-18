@extends('layouts.admin')

@section('title', 'Comparaison de simulations')
@section('pretitle', 'Rénovation · What If? Engine')

@section('actions')
    <a href="{{ route('admin.simulations.index') }}" class="btn"><i class="ti ti-arrow-left me-1"></i> Simulations</a>
@endsection

@section('content')
    @include('admin.renovation._nav')

    @php
        $lignes = [
            ['Budget hypothétique', fn ($s, $k) => number_format((float) $s->budget, 0, ',', ' ') . ' DT', null],
            ['Stratégie retenue', fn ($s, $k) => $s->libelleStrategie(), null],
            ['Budget alloué', fn ($s, $k) => number_format($k['alloue'], 0, ',', ' ') . ' DT', null],
            ['Projets achevés', fn ($s, $k) => $k['nb_complets'] . ' / ' . $k['nb_candidats'], 'nb_complets'],
            ['Priorité couverte', fn ($s, $k) => number_format($k['priorite_couverte_pct'], 1, ',', ' ') . ' %', 'priorite_couverte_pct'],
            ['Réseaux dégradés traités', fn ($s, $k) => $k['critiques_couverts'] . ' / ' . $k['critiques_total'], 'critiques_couverts'],
            ['Financements partiels', fn ($s, $k) => $k['nb_partiels'], null],
            ['Budget non utilisé', fn ($s, $k) => number_format($k['reste'], 0, ',', ' ') . ' DT', null],
            ['Part sur coûts estimés', fn ($s, $k) => number_format($k['part_estimee_pct'], 1, ',', ' ') . ' %', null],
        ];
        $kpis = $simulations->mapWithKeys(fn ($s) => [$s->id => $s->scenarioRetenu()['kpi']]);
    @endphp

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Indicateur <x-renovation.origine type="calcule" /></th>
                        @foreach ($simulations as $s)
                            <th class="text-center">
                                <a href="{{ route('admin.simulations.show', $s) }}">{{ $s->nom }}</a>
                                <div><span class="badge bg-{{ $s->couleurStatut() }}-lt">{{ $s->libelleStatut() }}</span></div>
                                <div class="small text-secondary fw-normal">{{ $s->created_at->format('d/m/Y') }} · {{ $s->auteur?->nom }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lignes as [$libelle, $valeur, $meilleur])
                        @php $max = $meilleur ? $kpis->max($meilleur) : null; @endphp
                        <tr>
                            <td class="text-secondary">{{ $libelle }}</td>
                            @foreach ($simulations as $s)
                                <td class="text-center">
                                    @if ($meilleur && $kpis[$s->id][$meilleur] == $max)<i class="ti ti-trophy text-yellow me-1"></i>@endif
                                    {{ $valeur($s, $kpis[$s->id]) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-secondary">
            Chaque simulation a été calculée sur les données réelles du moment de son enregistrement : des écarts peuvent venir
            de financements ajoutés entre-temps.
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Projets financés par simulation</h3></div>
        <div class="card-body">
            <div id="graphique-comparer" style="min-height: 320px"></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js" integrity="sha384-5u6RO0Q8xx8HD9yfm1MUPRgt4kTTlZxjJmXIFZxa5pEZy7U9XOtkAZgbF1fSYmsw" crossorigin="anonymous"></script>
    @php
    // Préparé ici : @json découpe son argument sur les virgules (il perdrait l'échappement des balises HTML)
    $simsJs = $simulations->map(fn ($s) => ['nom' => $s->nom, 'lignes' => collect($s->scenarioRetenu()['lignes'])
    ->map(fn ($l) => ['titre' => $l['titre'], 'propose' => $l['propose']])->all()])->values();
    @endphp
    <script>
        (function () {
            const sims = @json($simsJs);
            const titres = [...new Set(sims.flatMap(s => s.lignes.filter(l => l.propose > 0).map(l => l.titre)))].slice(0, 20);
            new ApexCharts(document.getElementById('graphique-comparer'), {
                chart: { type: 'bar', height: Math.max(320, 30 * titres.length * sims.length / 1.5 + 80), fontFamily: 'inherit', toolbar: { show: false } },
                series: sims.map(s => ({ name: s.nom, data: titres.map(t => (s.lignes.find(l => l.titre === t) || { propose: 0 }).propose) })),
                xaxis: { categories: titres.map(t => t.length > 40 ? t.slice(0, 38) + '…' : t),
                    labels: { formatter: v => new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(v) } },
                plotOptions: { bar: { horizontal: true } },
                dataLabels: { enabled: false },
                legend: { position: 'top' },
                tooltip: { y: { formatter: v => new Intl.NumberFormat('fr-FR').format(v) + ' DT' } },
            }).render();
        })();
    </script>
@endpush
