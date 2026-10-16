@extends('layouts.admin')

@section('title', $capteur->code)
@section('pretitle', 'Capteur · ' . $capteur->libelleTypeMesure())

@section('actions')
    <div class="btn-list">
        <a href="{{ route('admin.capteurs.edit', $capteur) }}" class="btn">
            <i class="ti ti-edit me-1"></i> Modifier
        </a>
        <a href="{{ route('admin.releves.export', ['capteur' => $capteur->id]) }}" class="btn">
            <i class="ti ti-file-spreadsheet me-1"></i> Export CSV
        </a>
                <a href="{{ route('admin.capteurs.rapport-pdf', $capteur) }}" class="btn">
            <i class="ti ti-file-type-pdf me-1"></i> Rapport PDF
        </a>
        @if ($capteur->statut !== 'hors_service')
            <a href="{{ route('admin.releves.create', ['capteur' => $capteur->id]) }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Ajouter un relevé
            </a>
        @endif
    </div>
@endsection

@section('content')
<div class="row row-cards">

    {{-- Fiche du capteur --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center p-4">
                <span class="avatar avatar-xl mb-3 bg-azure-lt"><i class="ti ti-{{ $capteur->iconeType() }}" style="font-size: 2rem"></i></span>
                <h3 class="m-0 mb-1 font-monospace">{{ $capteur->code }}</h3>
                <div class="text-secondary">{{ $capteur->modele }}</div>
                <div class="mt-3">
                    <span class="badge bg-{{ $capteur->couleurStatut() }}-lt">{{ $capteur->libelleStatut() }}</span>
                </div>
            </div>
            <div class="card-body border-top">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Mesure</div>
                        <div class="datagrid-content">{{ $capteur->libelleTypeMesure() }} ({{ $capteur->unite }})</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Seuils normaux</div>
                        <div class="datagrid-content">{{ $capteur->plageSeuils() }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Réseau</div>
                        <div class="datagrid-content">
                            <a href="{{ route('admin.reseaux.edit', $capteur->reseau) }}" class="font-monospace">{{ $capteur->reseau->code }}</a>
                            <div class="small text-secondary">{{ $capteur->reseau->libelleType() }} · {{ $capteur->reseau->zone->nom }}</div>
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Installé le</div>
                        <div class="datagrid-content">{{ $capteur->date_installation->format('d/m/Y') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Graphique des 50 derniers relevés + prévision --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Évolution des mesures</h3>
                <div class="card-actions text-secondary small">{{ $resume['nombre'] }} derniers relevés</div>
            </div>
            <div class="card-body">
                @if ($resume['nombre'])
                    <div class="row text-center mb-3">
                        <div class="col"><div class="text-secondary small">Minimum</div><div class="fw-bold">{{ $capteur->avecUnite($resume['min']) }}</div></div>
                        <div class="col"><div class="text-secondary small">Moyenne</div><div class="fw-bold">{{ $capteur->avecUnite($resume['moyenne']) }}</div></div>
                        <div class="col"><div class="text-secondary small">Maximum</div><div class="fw-bold">{{ $capteur->avecUnite($resume['max']) }}</div></div>
                        <div class="col">
                            <div class="text-secondary small">Hors seuil</div>
                            <div class="fw-bold {{ $resume['hors_seuil'] ? 'text-danger' : 'text-success' }}">{{ $resume['hors_seuil'] }}</div>
                        </div>
                        <div class="col">
                            <div class="text-secondary small">Anomalies IA</div>
                            <div class="fw-bold {{ count($anomalies) ? 'text-orange' : 'text-success' }}">{{ count($anomalies) }}</div>
                        </div>
                    </div>
                    <div id="graphique-releves" style="min-height: 300px"></div>
                    <div class="small text-secondary mt-2">
                        <span class="badge bg-red me-1"></span> hors seuil
                        <span class="badge bg-orange ms-3 me-1"></span> anomalie détectée par l'IA
                        @if ($prevision)<span class="ms-3">- - - prévision sur 24 h</span>@endif
                    </div>
                @else
                    <div class="empty">
                        <div class="empty-icon"><i class="ti ti-chart-line" style="font-size: 3rem"></i></div>
                        <p class="empty-title">Aucun relevé pour ce capteur</p>
                        <p class="empty-subtitle text-secondary">Le graphique apparaîtra dès le premier relevé.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Analyse intelligente : prévision (régression) + diagnostic rédigé par l'IA --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-sparkles text-purple me-1"></i> Analyse intelligente</h3>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <h4 class="subheader">Prévision (régression linéaire)</h4>
                        @if ($prevision)
                            @php
                                $icone = ['hausse' => 'trending-up', 'baisse' => 'trending-down', 'stable' => 'arrow-right'][$prevision['tendance']];
                            @endphp
                            <div class="datagrid">
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Tendance</div>
                                    <div class="datagrid-content">
                                        <i class="ti ti-{{ $icone }}"></i> {{ ucfirst($prevision['tendance']) }}
                                        <span class="text-secondary">({{ $prevision['pente_jour'] >= 0 ? '+' : '' }}{{ $capteur->avecUnite($prevision['pente_jour']) }} / jour)</span>
                                    </div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Valeur attendue dans 24 h</div>
                                    <div class="datagrid-content">{{ $capteur->avecUnite($prevision['valeur_24h']) }}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Confiance</div>
                                    <div class="datagrid-content">{{ ucfirst($prevision['confiance']) }} (R² = {{ $prevision['r2'] }}, {{ $prevision['nb_points'] }} points)</div>
                                </div>
                            </div>
                            @if ($phrase = \App\Services\AnalyseCapteur::phraseFranchissement($prevision['franchissement']))
                                <div class="alert alert-warning mt-3 mb-0"><i class="ti ti-clock-exclamation me-1"></i> {{ $phrase }}</div>
                            @else
                                <div class="alert alert-success mt-3 mb-0"><i class="ti ti-check me-1"></i> Aucun franchissement de seuil prévu dans les 7 jours.</div>
                            @endif
                        @else
                            <p class="text-secondary mb-0">Il faut au moins 4 relevés pour calculer une tendance.</p>
                        @endif
                    </div>

                    <div class="col-lg-7">
                        <h4 class="subheader mb-0">Diagnostic de l'IA</h4>
                        {{-- Composant Blade réutilisable (aussi utilisé pour l'analyse globale) --}}
                        <x-surveillance.rapport-ia :url="route('admin.capteurs.diagnostic', $capteur)"
                                                   bouton="Générer le diagnostic"
                                                   attente="L'IA analyse le capteur… (jusqu'à une minute)">
                            L'IA analyse les mesures, les anomalies et la prévision pour expliquer la cause probable
                            (fuite, sécheresse, capteur défaillant…) et proposer des actions.
                        </x-surveillance.rapport-ia>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Derniers relevés --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Relevés</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr><th>Date</th><th>Valeur</th><th>État</th><th>Remarque</th><th class="w-1"></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($releves as $releve)
                            <tr>
                                <td class="text-secondary text-nowrap">{{ $releve->date_releve->format('d/m/Y H:i') }}</td>
                                <td class="fw-medium text-nowrap">{{ $capteur->avecUnite($releve->valeur) }}</td>
                                <td>
                                    {{-- Composant Blade : Normal / Hors seuil + Anomalie IA --}}
                                    <x-surveillance.etat-releve :releve="$releve->setRelation('capteur', $capteur)"
                                                                :anomalie="$anomalies[$releve->id] ?? null" />
                                </td>
                                <td class="text-secondary">{{ $releve->remarque ?? '—' }}</td>
                                <td>
                                    <div class="btn-list flex-nowrap">
                                        <a href="{{ route('admin.releves.edit', $releve) }}" class="btn btn-sm"><i class="ti ti-edit"></i></a>
                                        <form method="POST" action="{{ route('admin.releves.destroy', $releve) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-ghost-danger"
                                                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                    data-confirm-type="danger"
                                                    data-confirm-title="Supprimer ce relevé ?"
                                                    data-confirm-message="Relevé de {{ $capteur->avecUnite($releve->valeur) }} du {{ $releve->date_releve->format('d/m/Y H:i') }}."
                                                    data-confirm-button="Oui, supprimer">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-4">Aucun relevé.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($releves->hasPages())
                <div class="card-footer d-flex justify-content-center">{{ $releves->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')


    @if ($resume['nombre'])
        {{-- ApexCharts : la bibliothèque de graphiques utilisée par les démos Tabler --}}
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>
        <script>
            (function () {
                const unite = @json($capteur->unite);
                const min = {{ $capteur->seuil_min }};
                const max = {{ $capteur->seuil_max }};
                const points = @json($graphique);
                const prevision = @json($prevision['points'] ?? []);
                const valeurs = points.map(p => p.y).concat(prevision.map(p => p.y));

                // L'axe vertical montre toujours les deux seuils, même si les mesures restent loin
                const marge = (max - min) * 0.25;
                const negatifPossible = @json($capteur->type_mesure === 'temperature');
                let bas = Math.min(min, ...valeurs) - marge;
                if (!negatifPossible) bas = Math.max(0, bas);
                const haut = Math.max(max, ...valeurs) + marge;

                // La courbe de prévision part du dernier relevé
                const dernier = points[points.length - 1];
                const series = [{ name: 'Mesure', data: points.map(p => ({ x: p.x, y: p.y })) }];
                if (prevision.length) {
                    series.push({ name: 'Prévision', data: [{ x: dernier.x, y: dernier.y }, ...prevision] });
                }

                // Points rouges (hors seuil) et orange (anomalie IA)
                const marqueurs = points.flatMap((p, i) => {
                    if (p.y < min || p.y > max) return [{ seriesIndex: 0, dataPointIndex: i, fillColor: '#d63939', strokeColor: '#fff', size: 5 }];
                    if (p.anomalie) return [{ seriesIndex: 0, dataPointIndex: i, fillColor: '#f76707', strokeColor: '#fff', size: 6 }];
                    return [];
                });

                new ApexCharts(document.getElementById('graphique-releves'), {
                    chart: { type: 'line', height: 300, fontFamily: 'inherit', toolbar: { show: false }, animations: { enabled: false } },
                    series: series,
                    stroke: { width: [2, 2], curve: 'straight', dashArray: [0, 6] },
                    colors: ['#066fd1', '#ae3ec9'],
                    legend: { show: false },
                    markers: { size: [3, 0], discrete: marqueurs },
                    xaxis: { type: 'datetime', labels: { datetimeFormatter: { day: 'dd/MM', hour: 'HH:mm' } } },
                    yaxis: {
                        min: bas, max: haut, tickAmount: 5,
                        labels: { formatter: v => v.toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' ' + unite },
                    },
                    tooltip: {
                        shared: false,
                        x: { format: 'dd/MM/yyyy HH:mm' },
                        y: { formatter: v => v.toLocaleString('fr-FR', { maximumFractionDigits: 2 }) + ' ' + unite },
                    },
                    // Zone verte = plage normale, lignes pointillées = seuils
                    annotations: {
                        yaxis: [
                            { y: min, y2: max, fillColor: '#2fb344', opacity: 0.08, borderColor: 'transparent' },
                            { y: min, borderColor: '#2fb344', strokeDashArray: 4, label: { text: 'Seuil min', style: { background: '#2fb344', color: '#fff' } } },
                            { y: max, borderColor: '#d63939', strokeDashArray: 4, label: { text: 'Seuil max', style: { background: '#d63939', color: '#fff' } } },
                        ],
                    },
                    grid: { strokeDashArray: 4 },
                }).render();
            })();
        </script>
    @endif
@endpush