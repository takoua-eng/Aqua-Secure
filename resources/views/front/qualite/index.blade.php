@extends('layouts.front')

@section('title', "Qualité de l'eau")

@section('content')
@php
    // Apparence selon le résultat de la dernière analyse
    $etats = [
        'conforme'     => ['Eau conforme',           'success',   'bi-check-circle-fill'],
        'non_conforme' => ['Eau non conforme',       'danger',    'bi-exclamation-octagon-fill'],
        'inconnu'      => ['Pas encore analysée',    'secondary', 'bi-hourglass-split'],
    ];
@endphp

{{-- Bandeau --}}
<section class="hero" style="padding: 70px 0 60px">
    <div class="container">
        <h1 class="fw-bold mb-2"><i class="bi bi-droplet-half me-2"></i>Qualité de l'eau potable</h1>
        <p class="lead opacity-75 mb-0">
            Résultats des analyses de laboratoire réalisées sur les points de prélèvement de votre réseau,
            comparés aux normes de potabilité (OMS / norme tunisienne NT 09.14).
        </p>
    </div>
</section>

<section class="section pt-5">
    <div class="container">

        {{-- Filtres --}}
        <form method="GET" class="card border-0 shadow-sm rounded-4 p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small text-secondary mb-1">Commune</label>
                    <select name="commune" class="form-select" onchange="this.form.submit()">
                        <option value="">Toutes les communes</option>
                        @foreach ($communes as $commune)
                            <option value="{{ $commune }}" @selected(request('commune') === $commune)>{{ $commune }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small text-secondary mb-1">Rechercher un lieu</label>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nom ou adresse…">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-eau w-100"><i class="bi bi-search"></i> Chercher</button>
                    @if (request()->hasAny(['commune', 'q']))
                        <a href="{{ route('qualite.index') }}" class="btn btn-outline-secondary" title="Effacer les filtres"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Indicateurs --}}
        <div class="row g-3 mb-4">
            @foreach ([
                ['bi-geo-alt',                 'primary', $indicateurs['points'],    'Points de prélèvement suivis'],
                ['bi-check-circle',            'success', $indicateurs['bonnes'],    'Points avec une eau conforme'],
                ['bi-exclamation-octagon',     'danger',  $indicateurs['mauvaises'], 'Points à surveiller'],
                ['bi-percent',                 'info',
                    $indicateurs['taux'] === null ? '—' : number_format($indicateurs['taux'], 1, ',', ' ') . ' %',
                    'Analyses conformes (8 derniers mois)'],
            ] as [$icone, $couleur, $valeur, $libelle])
                <div class="col-6 col-lg-3">
                    <div class="card carte-service border-0 shadow-sm h-100 p-3">
                        <div class="d-flex align-items-center">
                            <span class="icone-rond bg-{{ $couleur }} bg-opacity-10 text-{{ $couleur }} me-3 flex-shrink-0"><i class="bi {{ $icone }}"></i></span>
                            <div>
                                <div class="fs-3 fw-bold lh-1">{{ $valeur }}</div>
                                <div class="small text-secondary">{{ $libelle }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($indicateurs['mauvaises'] > 0)
            <div class="alert alert-warning d-flex rounded-4">
                <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                <div>
                    La dernière analyse de {{ $indicateurs['mauvaises'] }} point{{ $indicateurs['mauvaises'] > 1 ? 's' : '' }} n'est pas conforme.
                    La régie des eaux a été informée. Par précaution dans ces secteurs, faites bouillir l'eau avant de la boire
                    ou utilisez de l'eau en bouteille pour les nourrissons.
                </div>
            </div>
        @endif

        {{-- Carte + graphique --}}
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-map me-1"></i> Carte des points de prélèvement</div>
                    <div id="carte-qualite" style="height: 380px"></div>
                    <div class="card-footer bg-white d-flex flex-wrap gap-3 small text-secondary">
                        @foreach ($etats as [$libelle, $couleur])
                            <span><span class="badge text-bg-{{ $couleur }} me-1">&nbsp;</span> {{ $libelle }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-bar-chart me-1"></i> Analyses conformes par mois</div>
                    <div class="card-body">
                        <div id="graphique-mois" style="min-height: 300px"></div>
                        <details class="small mt-2">
                            <summary class="text-secondary">Voir les données</summary>
                            <table class="table table-sm mt-2 mb-0">
                                <thead><tr><th>Mois</th><th class="text-end">Analyses</th><th class="text-end">Conformes</th></tr></thead>
                                @foreach ($parMois as $ligne)
                                    <tr>
                                        <td>{{ $ligne['mois'] }}</td>
                                        <td class="text-end">{{ $ligne['total'] }}</td>
                                        <td class="text-end">{{ $ligne['taux'] === null ? '—' : number_format($ligne['taux'], 1, ',', ' ') . ' %' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </details>
                    </div>
                </div>
            </div>
        </div>

        {{-- Liste des points --}}
        <h2 class="fw-bold h4 mb-3">Points de prélèvement</h2>
        <div class="row g-3">
            @forelse ($points as $point)
                @php
                    $derniere = $point->derniereAnalyse;
                    [$libelle, $couleur, $icone] = $etats[$derniere ? ($derniere->conforme ? 'conforme' : 'non_conforme') : 'inconnu'];
                @endphp
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('qualite.show', $point) }}" class="card carte-service border-0 shadow-sm h-100 p-3 text-decoration-none text-reset">
                        <div class="d-flex align-items-start mb-2">
                            <span class="icone-rond bg-{{ $couleur }} bg-opacity-10 text-{{ $couleur }} me-3 flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.2rem">
                                <i class="bi {{ $icone }}"></i>
                            </span>
                            <div>
                                <h6 class="fw-semibold mb-0">{{ $point->nom }}</h6>
                                <div class="small text-secondary">{{ $point->adresse }}</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center small mt-auto">
                            <span class="badge text-bg-{{ $couleur }}">{{ $libelle }}</span>
                            <span class="ms-2 text-secondary">{{ $point->reseau->zone->commune }}</span>
                            @if ($derniere)
                                <span class="ms-auto text-secondary">le {{ $derniere->date_prelevement->format('d/m/Y') }}</span>
                            @endif
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12">
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-droplet d-block mb-2" style="font-size: 2.5rem"></i>
                        Aucun point de prélèvement ne correspond à votre recherche.
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</section>

{{-- Comprendre les analyses --}}
<section class="section section-clair">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Comprendre les analyses</h2>
            <p class="text-secondary">L'eau est déclarée conforme seulement si les 5 paramètres respectent la norme.</p>
        </div>
        <div class="row g-3">
            @foreach (\App\Models\AnalyseQualite::PARAMETRES as $cle => $infos)
                <div class="col-md-6 col-lg">
                    <div class="card carte-service border-0 shadow-sm h-100 p-3">
                        <h6 class="fw-semibold mb-1">{{ $infos['libelle'] }}</h6>
                        <div class="small mb-2"><span class="badge bg-primary bg-opacity-10 text-primary">Norme : {{ \App\Models\AnalyseQualite::norme($cle) }}</span></div>
                        <p class="small text-secondary mb-0">{{ \App\Models\AnalyseQualite::EXPLICATIONS[$cle] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
    <script>
        (function () {
            // Carte : un rond coloré par point selon sa dernière analyse
            const points = @json($carte);
            const couleurs = { conforme: '#198754', non_conforme: '#dc3545', inconnu: '#6c757d' };
            const libelles = { conforme: 'Eau conforme', non_conforme: 'Eau non conforme', inconnu: 'Pas encore analysée' };
            const echapper = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

            const carte = L.map('carte-qualite').setView([34.8, 9.6], 6);   // Tunisie entière
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(carte);

            const reperes = points.map(p => L.circleMarker([p.lat, p.lng], {
                radius: 9, color: '#ffffff', weight: 2, fillColor: couleurs[p.etat], fillOpacity: .9,
            }).addTo(carte).bindPopup(
                `<b>${echapper(p.nom)}</b><br><span class="text-secondary">${echapper(p.adresse)}</span><br>` +
                `<span style="color:${couleurs[p.etat]}">● ${libelles[p.etat]}</span>` +
                (p.date ? ` <small>(${p.date})</small>` : '') +
                `<br><a href="${p.url}">Voir le détail</a>`
            ));

            if (reperes.length) carte.fitBounds(L.featureGroup(reperes).getBounds().pad(0.2), { maxZoom: 14 });

            // Graphique : part des analyses conformes chaque mois
            const parMois = @json($parMois);
            new ApexCharts(document.getElementById('graphique-mois'), {
                chart: { type: 'bar', height: 300, fontFamily: 'inherit', toolbar: { show: false }, animations: { enabled: false }, parentHeightOffset: 0 },
                series: [{ name: 'Analyses conformes', data: parMois.map(m => m.taux) }],
                colors: ['#0b6fb8'],
                plotOptions: { bar: { columnWidth: '50%', borderRadius: 4, borderRadiusApplication: 'end', dataLabels: { position: 'top' } } },
                dataLabels: { enabled: true, offsetY: -18, formatter: v => v === null ? '' : Math.round(v) + ' %', style: { colors: ['#33475b'], fontWeight: 500 } },
                xaxis: { categories: parMois.map(m => m.mois), labels: { style: { colors: '#898781' } } },
                yaxis: { min: 0, max: 100, tickAmount: 4, labels: { formatter: v => Math.round(v) + ' %', style: { colors: '#898781' } } },
                grid: { borderColor: '#e1e0d9' },
                tooltip: { y: { formatter: (v, { dataPointIndex: i }) => v === null ? 'aucune analyse' : `${v} % (${parMois[i].conformes} sur ${parMois[i].total})` } },
            }).render();
        })();
    </script>
@endpush
