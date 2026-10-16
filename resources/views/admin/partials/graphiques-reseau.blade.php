{{--
    Graphiques du parc d'infrastructure (ApexCharts). Données : App\Services\StatistiquesReseau::calculer()
    Couleurs : palette de statut pour les états (bon / dégradé / hors service, toujours avec leur libellé),
    une seule teinte bleue pour les quantités. Chaque graphique a son tableau de données.
--}}
@php
    $etatCouleurs = ['bon' => '#0ca30c', 'degrade' => '#fab219', 'hors_service' => '#d03b3b'];
@endphp

<div class="col-12">
    <h3 class="mb-0 mt-2">Parc d'infrastructure <span class="text-secondary fw-normal">· {{ $statsReseau['total'] }} éléments</span></h3>
</div>

@foreach ([
    ['graphique-etats',     'Éléments par état',     'etats'],
    ['graphique-materiaux', 'Éléments par matériau', 'materiaux'],
    ['graphique-ages',      'Âge du parc',           'ages'],
] as [$id, $titre, $cle])
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ $titre }}</h3></div>
            <div class="card-body">
                <div id="{{ $id }}" style="min-height: 220px"></div>
                <details class="mt-2 small">
                    <summary class="text-secondary">Voir les données</summary>
                    <table class="table table-sm mt-2 mb-0">
                        @foreach ($statsReseau[$cle] as $ligne)
                            <tr><td>{{ $ligne['libelle'] }}</td><td class="text-end" style="font-variant-numeric: tabular-nums">{{ $ligne['nombre'] }}</td></tr>
                        @endforeach
                    </table>
                </details>
            </div>
        </div>
    </div>
@endforeach

<div class="col-12">
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Éléments en mauvais état sur 12 mois</h3>
                <div class="card-subtitle">Reconstitué à partir de l'historique des états, à la fin de chaque mois.</div>
            </div>
        </div>
        <div class="card-body">
            <div id="graphique-evolution" style="min-height: 260px"></div>
            <details class="mt-2 small">
                <summary class="text-secondary">Voir les données</summary>
                <div class="table-responsive">
                    <table class="table table-sm mt-2 mb-0">
                        <thead><tr><th>Mois</th><th class="text-end">Dégradés</th><th class="text-end">Hors service</th></tr></thead>
                        @foreach ($statsReseau['evolution'] as $ligne)
                            <tr>
                                <td>{{ $ligne['mois'] }}</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $ligne['degrade'] }}</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $ligne['hors_service'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </details>
        </div>
    </div>
</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
    <script>
        (function () {
            const stats = @json($statsReseau);
            const encre = { principale: '#0b0b0b', muette: '#898781', grille: '#e1e0d9', axe: '#c3c2b7' };
            const bleu = '#2a78d6';

            // Réglages communs : grille fine et discrète, pas de barre d'outils, police de la page
            const base = {
                chart: { fontFamily: 'inherit', toolbar: { show: false }, animations: { enabled: false }, parentHeightOffset: 0 },
                grid: { borderColor: encre.grille, strokeDashArray: 0 },
                tooltip: { theme: 'light' },
                legend: { show: false },
            };
            const axeTexte = { style: { colors: encre.muette } };

            // Barres horizontales fines, extrémité arrondie, valeur au bout de la barre (en couleur de texte)
            function barres(id, libelles, valeurs, couleurs) {
                new ApexCharts(document.getElementById(id), {
                    ...base,
                    chart: { ...base.chart, type: 'bar', height: 220 },
                    series: [{ name: 'Éléments', data: valeurs }],
                    colors: couleurs,
                    plotOptions: { bar: {
                        horizontal: true, barHeight: '45%', borderRadius: 4, borderRadiusApplication: 'end',
                        distributed: couleurs.length > 1, dataLabels: { position: 'top' },
                    } },
                    dataLabels: { enabled: true, offsetX: 22, style: { colors: [encre.principale], fontWeight: 500 } },
                    xaxis: { categories: libelles, labels: { ...axeTexte, formatter: v => Math.round(v) }, axisBorder: { color: encre.axe }, tickAmount: 4 },
                    yaxis: { labels: { style: { colors: encre.principale } } },
                    grid: { ...base.grid, yaxis: { lines: { show: false } } },
                }).render();
            }

            barres('graphique-etats',
                stats.etats.map(e => e.libelle), stats.etats.map(e => e.nombre),
                stats.etats.map(e => @json($etatCouleurs)[e.cle]));
            barres('graphique-materiaux',
                stats.materiaux.map(m => m.libelle), stats.materiaux.map(m => m.nombre), [bleu]);

            // Colonnes : âge du parc par tranche de 10 ans
            new ApexCharts(document.getElementById('graphique-ages'), {
                ...base,
                chart: { ...base.chart, type: 'bar', height: 220 },
                series: [{ name: 'Éléments', data: stats.ages.map(a => a.nombre) }],
                colors: [bleu],
                plotOptions: { bar: { columnWidth: '40%', borderRadius: 4, borderRadiusApplication: 'end', dataLabels: { position: 'top' } } },
                dataLabels: { enabled: true, offsetY: -18, style: { colors: [encre.principale], fontWeight: 500 } },
                xaxis: { categories: stats.ages.map(a => a.libelle), labels: axeTexte, axisBorder: { color: encre.axe } },
                yaxis: { labels: { ...axeTexte, formatter: v => Math.round(v) }, tickAmount: 3 },
            }).render();

            // Courbes : dégradés et hors service à la fin de chaque mois (légende + valeur finale)
            new ApexCharts(document.getElementById('graphique-evolution'), {
                ...base,
                chart: { ...base.chart, type: 'line', height: 260 },
                series: [
                    { name: 'Dégradés', data: stats.evolution.map(m => m.degrade) },
                    { name: 'Hors service', data: stats.evolution.map(m => m.hors_service) },
                ],
                colors: [@json($etatCouleurs['degrade']), @json($etatCouleurs['hors_service'])],
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
