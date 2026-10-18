@extends('layouts.admin')

@section('title', 'Équité territoriale')
@section('pretitle', 'Rénovation · 24 gouvernorats')
@section('subtitle', 'Les financements enregistrés sont-ils répartis équitablement entre les régions ?')

@section('actions')
    <a href="{{ route('admin.whatif.index') }}" class="btn btn-primary"><i class="ti ti-map-2 me-1"></i> Simuler la stratégie « Équité »</a>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @include('admin.renovation._nav')

    @php $dt = fn ($v, $d = 0) => number_format((float) $v, $d, ',', ' '); @endphp

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm h-100"><div class="card-body">
                <div class="d-flex align-items-center mb-1"><span class="text-secondary">Indice de Gini</span><x-renovation.origine type="calcule" class="ms-auto" /></div>
                <div class="h1 mb-1">{{ $lorenz['gini'] !== null ? number_format($lorenz['gini'], 2, ',', ' ') : '—' }}</div>
                <div class="small text-secondary">0 = égalité parfaite par habitant · 1 = tout au même endroit</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm h-100"><div class="card-body">
                <div class="text-secondary mb-1">Moyenne nationale</div>
                <div class="h1 mb-1">{{ $moyenne !== null ? $dt($moyenne, 2) . ' DT' : '—' }}</div>
                <div class="small text-secondary">investis par habitant des zones enregistrées</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm h-100"><div class="card-body">
                <div class="text-secondary mb-1">Gouvernorats sans projet</div>
                <div class="h1 mb-1">{{ $sansProjet->count() }} <span class="fs-5 text-secondary">/ 24</span></div>
                <div class="small text-secondary text-truncate" title="{{ $sansProjet->implode(', ') }}">{{ $sansProjet->implode(', ') ?: 'Tous ont au moins un projet' }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm h-100 bg-azure-lt"><div class="card-body">
                <div class="fw-bold mb-1"><i class="ti ti-scale me-1"></i>Lecture</div>
                <div class="small">{{ $lecture }}</div>
            </div></div>
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-map me-1"></i> Carte des DT investis par habitant</h3>
                    <div class="card-actions"><x-renovation.origine type="enregistre" /></div>
                </div>
                <div class="card-body">
                    <div id="carte-territoires" style="height: 460px; border-radius: 8px"></div>
                    <div class="d-flex flex-wrap gap-3 small mt-2" id="legende-territoires"></div>
                    <div class="small text-secondary mt-1">Taille du cercle = population des zones enregistrées · couleur = DT par habitant. Position : chef-lieu du gouvernorat.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-chart-line me-1"></i> Courbe de Lorenz</h3></div>
                <div class="card-body">
                    <div id="graphique-lorenz" style="min-height: 320px"></div>
                    <p class="small text-secondary mb-0">
                        Les gouvernorats sont classés du moins au mieux doté par habitant. Plus la courbe s'éloigne de la diagonale
                        (égalité parfaite), plus la répartition est inégale. Exemple de lecture : si le point (50 ; 20) est sur la courbe,
                        la moitié de la population concernée ne reçoit que 20 % des fonds.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-list-numbers me-1"></i> Classement des 24 gouvernorats</h3>
            <div class="card-actions"><x-renovation.origine type="calcule" /></div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-sm">
                <thead>
                    <tr><th>#</th><th>Gouvernorat</th><th class="text-end">Population (zones)</th><th class="text-end">Projets</th>
                        <th class="text-end">En travaux</th><th class="text-end">Financé</th><th class="text-end">DT / habitant</th><th style="min-width: 12rem">Écart à la moyenne</th></tr>
                </thead>
                <tbody>
                    @php $max = max(1, (float) $territoires->max('par_habitant')); @endphp
                    @foreach ($territoires as $i => $t)
                        <tr>
                            <td class="text-secondary">{{ $t['par_habitant'] !== null ? $i + 1 : '—' }}</td>
                            <td class="fw-medium">{{ $t['gouvernorat'] }}</td>
                            <td class="text-end">{{ $t['population'] ? $dt($t['population']) : '—' }}</td>
                            <td class="text-end">{{ $t['projets'] }}</td>
                            <td class="text-end">{{ $t['en_cours'] }}</td>
                            <td class="text-end"><x-renovation.montant :valeur="$t['finance']" :decimales="0" /></td>
                            <td class="text-end fw-bold">{{ $t['par_habitant'] !== null ? $dt($t['par_habitant'], 2) : '—' }}</td>
                            <td>
                                @if ($t['par_habitant'] !== null && $moyenne)
                                    @php $ecart = round(100 * ($t['par_habitant'] - $moyenne) / $moyenne); @endphp
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress progress-sm flex-fill"><div class="progress-bar bg-{{ $ecart >= 0 ? 'teal' : 'orange' }}" style="width: {{ 100 * $t['par_habitant'] / $max }}%"></div></div>
                                        <span class="small text-nowrap text-{{ $ecart >= 0 ? 'teal' : 'orange' }}">{{ $ecart >= 0 ? '+' : '' }}{{ $ecart }} %</span>
                                    </div>
                                @else
                                    <span class="small text-secondary">{{ $t['population'] ? 'aucun financement' : 'aucune zone enregistrée' }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-secondary">
            Population = somme des populations des zones enregistrées dans AquaSecure (ce n'est pas un recensement).
            @if ($sansZone->isNotEmpty()) Gouvernorats sans zone enregistrée : {{ $sansZone->implode(', ') }}. @endif
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
<script>
(function () {
    const territoires = @json($territoires);
    const lorenz = @json($lorenz);
    const echapper = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const nombre = (v, d = 0) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d }).format(v);

    // Couleurs par quartile de DT/habitant (orange = peu, vert = beaucoup)
    const valeurs = territoires.map(t => t.par_habitant).filter(v => v !== null).sort((a, b) => a - b);
    const q = p => valeurs.length ? valeurs[Math.min(valeurs.length - 1, Math.floor(p * valeurs.length))] : 0;
    const seuils = [q(.25), q(.5), q(.75)];
    const palette = ['#f76707', '#fab005', '#74b816', '#0ca678'];
    const couleur = v => v === null ? '#adb5bd' : palette[seuils.filter(s => v >= s).length];

    const map = L.map('carte-territoires', { scrollWheelZoom: false }).setView([34.2, 9.6], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
    const popMax = Math.max(1, ...territoires.map(t => t.population));
    territoires.forEach(t => {
        L.circleMarker([t.lat, t.lng], { radius: 6 + 18 * Math.sqrt(t.population / popMax), color: '#fff', weight: 2, fillColor: couleur(t.par_habitant), fillOpacity: .85 })
            .addTo(map)
            .bindPopup(`<strong>${echapper(t.gouvernorat)}</strong><br>${t.par_habitant !== null ? nombre(t.par_habitant, 2) + ' DT / habitant' : 'Pas de population enregistrée'}
                <br>${nombre(t.finance)} DT · ${t.projets} projet(s)`);
    });
    document.getElementById('legende-territoires').innerHTML =
        ['Moins doté (1er quart)', '2e quart', '3e quart', 'Mieux doté (4e quart)'].map((l, i) =>
            `<span><span class="badge me-1" style="background:${palette[i]}">&nbsp;</span>${l}</span>`).join('')
        + '<span><span class="badge me-1" style="background:#adb5bd">&nbsp;</span>Sans population enregistrée</span>';

    new ApexCharts(document.getElementById('graphique-lorenz'), {
        chart: { type: 'line', height: 320, fontFamily: 'inherit', toolbar: { show: false } },
        series: [
            { name: 'Répartition réelle', data: lorenz.points.map(p => ({ x: p[0], y: p[1] })) },
            { name: 'Égalité parfaite', data: [{ x: 0, y: 0 }, { x: 100, y: 100 }] },
        ],
        xaxis: { type: 'numeric', min: 0, max: 100, title: { text: '% cumulé de la population' }, labels: { formatter: v => Math.round(v) + ' %' } },
        yaxis: { min: 0, max: 100, title: { text: '% cumulé des fonds' }, labels: { formatter: v => Math.round(v) + ' %' } },
        colors: ['#066fd1', '#adb5bd'],
        stroke: { width: [3, 2], dashArray: [0, 6], curve: 'straight' },
        markers: { size: [4, 0] },
        tooltip: { x: { formatter: v => nombre(v, 1) + ' % de la population' }, y: { formatter: v => nombre(v, 1) + ' % des fonds' } },
        legend: { position: 'top' },
    }).render();
})();
</script>
@endpush
