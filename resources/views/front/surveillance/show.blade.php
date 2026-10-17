@extends('layouts.front')

@section('title', $capteur->libelleTypeMesure() . ' — ' . $capteur->reseau->zone->nom)

@section('content')
@php
    [$libelle, $couleur, $icone] = \App\Http\Controllers\Front\SurveillanceController::ETATS[$etat];
    $messages = [
        'normal'      => "Les dernières mesures sont dans la plage normale : le réseau fonctionne correctement dans ce secteur.",
        'alerte'      => "La dernière mesure sort de la plage normale. La régie des eaux a été prévenue automatiquement et vérifie la situation.",
        'maintenance' => "Ce capteur est en cours de réparation : ses mesures peuvent être incomplètes.",
        'inconnu'     => "Aucune mesure n'a été reçue récemment de ce capteur.",
    ];
    $tendances = ['hausse' => ['en hausse', 'bi-graph-up-arrow'], 'baisse' => ['en baisse', 'bi-graph-down-arrow'], 'stable' => ['stable', 'bi-arrow-right']];
@endphp

{{-- Bandeau --}}
<section class="hero" style="padding: 60px 0 50px">
    <div class="container">
        <a href="{{ route('surveillance.index') }}" class="text-white-50 text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Surveillance du réseau
        </a>
        <h1 class="fw-bold mt-2 mb-1">{{ $capteur->libelleTypeMesure() }} — {{ $capteur->reseau->zone->nom }}</h1>
        <p class="opacity-75 mb-0">
            <i class="bi bi-geo-alt"></i> {{ $capteur->reseau->zone->commune }} · {{ $capteur->reseau->libelleType() }}
            · capteur {{ $capteur->code }}
        </p>
    </div>
</section>

<section class="section pt-5">
    <div class="container">

        {{-- État actuel --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-5 border-{{ $couleur }}">
            <div class="card-body p-4 d-flex flex-wrap align-items-center gap-3">
                <span class="icone-rond bg-{{ $couleur }} bg-opacity-10 text-{{ $couleur }}"><i class="bi {{ $icone }}"></i></span>
                <div class="flex-grow-1">
                    <h2 class="h4 fw-bold mb-1">{{ $libelle }}</h2>
                    <div class="text-secondary">{{ $messages[$etat] }}</div>
                </div>
                @if ($capteur->dernierReleve)
                    <div class="text-center px-3">
                        <div class="fs-2 fw-bold lh-1">{{ $capteur->avecUnite($capteur->dernierReleve->valeur) }}</div>
                        <div class="small text-secondary">{{ $capteur->dernierReleve->date_releve->diffForHumans() }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="row g-4">
            {{-- Graphique des 7 derniers jours --}}
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-graph-up me-1"></i> Évolution sur 7 jours</div>
                    <div class="card-body">
                        @if ($resume['nombre'])
                            <div id="graphique" style="min-height: 300px"></div>
                            <div class="small text-secondary mt-2">
                                <span class="badge text-bg-success opacity-50 me-1">&nbsp;</span> plage normale ({{ $capteur->plageSeuils() }})
                            </div>
                        @else
                            <div class="text-center text-secondary py-5">
                                <i class="bi bi-graph-up d-block mb-2" style="font-size: 2.5rem"></i>
                                Aucune mesure sur les 7 derniers jours.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Résumé --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> En résumé</div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex"><span class="text-secondary">Plage normale</span><span class="ms-auto fw-semibold">{{ $capteur->plageSeuils() }}</span></li>
                        <li class="list-group-item d-flex"><span class="text-secondary">Mesures (7 jours)</span><span class="ms-auto fw-semibold">{{ $resume['nombre'] }}</span></li>
                        @if ($resume['nombre'])
                            <li class="list-group-item d-flex"><span class="text-secondary">Minimum / maximum</span><span class="ms-auto fw-semibold">{{ $capteur->avecUnite($resume['min']) }} / {{ $capteur->avecUnite($resume['max']) }}</span></li>
                        @endif
                        <li class="list-group-item d-flex"><span class="text-secondary">Mesures anormales</span><span class="ms-auto fw-semibold text-{{ $resume['hors_seuil'] ? 'danger' : 'success' }}">{{ $resume['hors_seuil'] }}</span></li>
                        @if ($tendance)
                            <li class="list-group-item d-flex"><span class="text-secondary">Tendance (calcul IA)</span><span class="ms-auto fw-semibold"><i class="bi {{ $tendances[$tendance][1] }}"></i> {{ $tendances[$tendance][0] }}</span></li>
                        @endif
                    </ul>
                    <div class="card-body small text-secondary border-top">
                        <i class="bi bi-lightbulb"></i> {{ $explication }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Dernières mesures --}}
        @if ($derniers->isNotEmpty())
            <h2 class="fw-bold h4 mt-5 mb-3">Dernières mesures</h2>
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <table class="table mb-0 align-middle">
                    <thead class="table-light"><tr><th>Date</th><th class="text-end">Valeur</th><th>État</th></tr></thead>
                    <tbody>
                        @foreach ($derniers as $releve)
                            <tr>
                                <td>{{ $releve->date_releve->format('d/m/Y à H:i') }}</td>
                                <td class="text-end fw-semibold">{{ $capteur->avecUnite($releve->valeur) }}</td>
                                <td>
                                    @if ($releve->hors_seuil)
                                        <span class="badge text-bg-danger">Anormale ({{ $releve->setRelation('capteur', $capteur)->sens() }})</span>
                                    @else
                                        <span class="badge text-bg-success">Normale</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (auth()->user()->isCitoyen())
            <div class="text-center mt-5">
                <p class="text-secondary mb-2">Vous constatez une fuite, une coupure ou une eau trouble près de chez vous ?</p>
                <a href="{{ route('signalement.create') }}" class="btn btn-eau"><i class="bi bi-exclamation-triangle"></i> Signaler un incident</a>
            </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
    @if ($resume['nombre'])
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>
        <script>
            (function () {
                const unite = @json($capteur->unite);
                const min = {{ $capteur->seuil_min }};
                const max = {{ $capteur->seuil_max }};
                const points = @json($graphique);
                const valeurs = points.map(p => p.y);
                const marge = (max - min) * 0.25;
                let bas = Math.min(min, ...valeurs) - marge;
                if (@json($capteur->type_mesure !== 'temperature')) bas = Math.max(0, bas);

                new ApexCharts(document.getElementById('graphique'), {
                    chart: { type: 'area', height: 300, fontFamily: 'inherit', toolbar: { show: false }, animations: { enabled: false } },
                    series: [{ name: @json($capteur->libelleTypeMesure()), data: points }],
                    colors: ['#0b6fb8'],
                    stroke: { width: 2, curve: 'smooth' },
                    fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .05 } },
                    dataLabels: { enabled: false },
                    xaxis: { type: 'datetime', labels: { datetimeFormatter: { day: 'dd/MM', hour: 'HH:mm' } } },
                    yaxis: { min: bas, max: Math.max(max, ...valeurs) + marge, tickAmount: 5,
                             labels: { formatter: v => v.toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' ' + unite } },
                    tooltip: { x: { format: 'dd/MM/yyyy HH:mm' },
                               y: { formatter: v => v.toLocaleString('fr-FR', { maximumFractionDigits: 2 }) + ' ' + unite } },
                    // Bande verte : plage normale
                    annotations: { yaxis: [{ y: min, y2: max, fillColor: '#198754', opacity: 0.08, borderColor: 'transparent' }] },
                    grid: { borderColor: '#e1e0d9', strokeDashArray: 4 },
                }).render();
            })();
        </script>
    @endif
@endpush