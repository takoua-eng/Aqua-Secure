@extends('layouts.admin')

@section('title', 'Plan pluriannuel')
@section('pretitle', 'Rénovation · What If? Engine')
@section('subtitle', 'Combien d\'années faut-il pour financer tous les projets avec un budget annuel donné ?')

@section('actions')
    <a href="{{ route('admin.whatif.index') }}" class="btn"><i class="ti ti-adjustments-alt me-1"></i> Simulateur sur un an</a>
@endsection

@section('content')
    @include('admin.renovation._nav')

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="GET" class="card mb-3" data-validate>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <x-input name="budget" type="number" label="Budget annuel hypothétique (DT)" icon="coin" required
                             :value="request('budget', $budgetPropose)" min="1000" max="100000000" step="1000"
                             data-msg-required="Indiquez le budget annuel." valid-message="Budget valide." />
                </div>
                <div class="col-md-2">
                    <x-select name="annees" label="Durée" icon="calendar" required :value="request('annees', 3)"
                              :options="collect(range(1, \App\Services\Renovation\PlanPluriannuel::ANNEES_MAX))->mapWithKeys(fn ($n) => [$n => $n . ' an' . ($n > 1 ? 's' : '')])->all()" />
                </div>
                <div class="col-md-4">
                    <x-select name="strategie" label="Stratégie appliquée chaque année" icon="route" required :value="request('strategie', 'degrades')"
                              :options="collect($strategies)->mapWithKeys(fn ($s, $k) => [$k => $s['lettre'] . ' — ' . $s['libelle']])->all()" />
                </div>
                <div class="col-md-3">
                    <label class="form-check form-switch mb-2">
                        <input type="hidden" name="report" value="0">
                        <input class="form-check-input" type="checkbox" name="report" value="1" @checked(request('report', '1') === '1')>
                        <span class="form-check-label">Reporter le budget non utilisé</span>
                    </label>
                    <button class="btn btn-primary w-100 mb-3"><i class="ti ti-calendar-stats me-1"></i> Calculer le plan</button>
                </div>
            </div>
            <div class="small text-secondary">
                Besoin total actuel des projets non terminés : <strong><x-renovation.montant :valeur="$besoin" :decimales="0" /></strong>
                <x-renovation.origine type="calcule" /> · le budget annuel est une <x-renovation.origine type="hypothese" />, aucune donnée réelle n'est modifiée.
            </div>
        </div>
    </form>

    @if ($plan)
        @php $nbAnnees = count($plan['annees']); @endphp
        <div class="row row-cards mb-3">
            @foreach ([
                ['Projets achevés', $plan['acheves'] . ' / ' . $plan['candidats'], 'circle-check', 'green', 'en ' . $nbAnnees . ' an(s)'],
                ['Montant alloué', number_format($plan['alloue'], 0, ',', ' ') . ' DT', 'coin', 'purple', 'sur toute la période'],
                ['Reste à financer ensuite', number_format($plan['reste_a_financer'], 0, ',', ' ') . ' DT', 'hourglass', 'orange', count($plan['restants']) . ' projet(s) au-delà de ' . end($plan['annees'])['annee']],
                ['Reliquat final', number_format(end($plan['annees'])['reliquat'], 0, ',', ' ') . ' DT', 'wallet', 'secondary', 'budget non utilisé la dernière année'],
            ] as [$titre, $valeur, $icone, $couleur, $detail])
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm h-100"><div class="card-body">
                        <div class="d-flex align-items-center mb-1"><span class="avatar avatar-sm bg-{{ $couleur }}-lt me-2"><i class="ti ti-{{ $icone }}"></i></span>
                            <span class="small text-secondary">{{ $titre }}</span><x-renovation.origine type="calcule" class="ms-auto" /></div>
                        <div class="h2 mb-0">{{ $valeur }}</div><div class="small text-secondary">{{ $detail }}</div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-chart-bar me-1"></i> Projets achevés année après année</h3></div>
            <div class="card-body"><div id="graphique-plan" style="min-height: 260px"></div></div>
        </div>

        {{-- Frise : une colonne par année --}}
        <div class="row row-cards mb-3">
            @foreach ($plan['annees'] as $a)
                <div class="col-md-6 col-xl">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title">{{ $a['annee'] }}</h3>
                            <div class="card-actions small text-secondary">{{ number_format($a['alloue'], 0, ',', ' ') }} / {{ number_format($a['budget'], 0, ',', ' ') }} DT</div>
                        </div>
                        <div class="list-group list-group-flush">
                            @forelse ($a['projets'] as $p)
                                <a href="{{ route('admin.projets.show', $p['id']) }}" class="list-group-item list-group-item-action py-2">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-{{ $p['etat'] === 'complet' ? 'green' : 'orange' }}-lt me-2">{{ $p['etat'] === 'complet' ? 'Achevé' : 'Partiel' }}</span>
                                        <span class="small fw-medium text-truncate">{{ $p['titre'] }}</span>
                                    </div>
                                    <div class="small text-secondary">{{ $p['territoire'] ?? $p['reseau'] }} · {{ number_format($p['propose'], 0, ',', ' ') }} DT</div>
                                </a>
                            @empty
                                <div class="list-group-item small text-secondary">Aucun projet ne tient dans le budget de cette année.</div>
                            @endforelse
                        </div>
                        @if ($a['reliquat'] > 0)
                            <div class="card-footer small text-secondary">Non utilisé : {{ number_format($a['reliquat'], 0, ',', ' ') }} DT</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row row-cards mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title"><i class="ti ti-hourglass me-1"></i> Projets encore à financer après le plan</h3></div>
                    <div class="list-group list-group-flush">
                        @forelse ($plan['restants'] as $r)
                            <a href="{{ route('admin.projets.show', $r['id']) }}" class="list-group-item list-group-item-action d-flex py-2">
                                <span class="small text-truncate">{{ $r['titre'] }}</span>
                                <span class="small ms-auto text-nowrap">reste {{ number_format($r['besoin'], 0, ',', ' ') }} DT</span>
                            </a>
                        @empty
                            <div class="list-group-item text-green"><i class="ti ti-confetti me-1"></i> Tous les projets sont financés dans la période.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title"><i class="ti ti-bulb me-1"></i> Hypothèses</h3></div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            @foreach ($plan['hypotheses'] as $h)
                                <li class="d-flex gap-2 mb-1"><i class="ti ti-bulb text-purple mt-1"></i>{{ $h }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @if ($plan)
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js" integrity="sha384-5u6RO0Q8xx8HD9yfm1MUPRgt4kTTlZxjJmXIFZxa5pEZy7U9XOtkAZgbF1fSYmsw" crossorigin="anonymous"></script>
        <script>
            (function () {
                const annees = @json($plan['annees']);
                let cumul = 0;
                new ApexCharts(document.getElementById('graphique-plan'), {
                    chart: { type: 'bar', height: 260, fontFamily: 'inherit', toolbar: { show: false } },
                    series: [
                        { name: 'Achevés dans l\'année', type: 'column', data: annees.map(a => a.acheves) },
                        { name: 'Cumul', type: 'line', data: annees.map(a => (cumul += a.acheves)) },
                    ],
                    xaxis: { categories: annees.map(a => String(a.annee)) },
                    yaxis: { labels: { formatter: v => Math.round(v) }, title: { text: 'Projets' } },
                    colors: ['#0ca678', '#066fd1'],
                    stroke: { width: [0, 3] },
                    plotOptions: { bar: { columnWidth: '40%', borderRadius: 4 } },
                    dataLabels: { enabled: true, enabledOnSeries: [0] },
                }).render();
            })();
        </script>
    @endif
@endpush
