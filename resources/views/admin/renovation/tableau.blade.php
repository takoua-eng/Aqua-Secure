@extends('layouts.admin')

@section('title', 'Financement des rénovations')
@section('pretitle', 'Rénovation · Smart Budget Monitoring')
@section('subtitle', 'Indicateurs calculés en direct à partir des projets et financements enregistrés.')

@section('actions')
    <div class="btn-list">
        <form method="GET" action="{{ route('admin.renovation.rapport-mensuel') }}" class="d-flex gap-1">
            <input type="month" name="mois" value="{{ now()->format('Y-m') }}" max="{{ now()->format('Y-m') }}" class="form-control" style="width: 10.5rem" aria-label="Mois du rapport">
            <button class="btn" title="Rapport PDF : chiffres du mois + synthèse IA"><i class="ti ti-file-type-pdf me-1"></i> Rapport mensuel</button>
        </form>
        <a href="{{ route('admin.whatif.index') }}" class="btn btn-outline-primary">
            <i class="ti ti-adjustments-alt me-1"></i> What If? Engine
        </a>
        <a href="{{ route('admin.projets.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Nouveau projet
        </a>
    </div>
@endsection

@section('content')
    @include('admin.renovation._nav')

    @php
        $c = $suivi['couverture'];
        $niveaux = ['danger' => ['red', 'alert-octagon', 'Critique'], 'warning' => ['orange', 'alert-triangle', 'À surveiller'], 'info' => ['azure', 'info-circle', 'Information']];
    @endphp

    @if ($suivi['nb_projets'] === 0)
        <div class="card">
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-building" style="font-size: 3rem"></i></div>
                <p class="empty-title">Aucun projet de rénovation enregistré</p>
                <p class="empty-subtitle text-secondary">Les indicateurs s'afficheront dès que des projets et des financements seront saisis.</p>
                <div class="empty-action">
                    <a href="{{ route('admin.projets.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Créer le premier projet</a>
                </div>
            </div>
        </div>
    @else
        {{-- Indicateurs --}}
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-xl-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="avatar bg-blue text-white me-2"><i class="ti ti-building"></i></span>
                            <div class="text-secondary">Projets de rénovation</div>
                            <x-renovation.origine type="enregistre" class="ms-auto" />
                        </div>
                        <div class="h1 mb-1">{{ $suivi['nb_projets'] }}</div>
                        <div class="small text-secondary">
                            @foreach ($suivi['par_statut'] as $s)
                                {{ $s['libelle'] }} : <strong>{{ $s['nombre'] }}</strong>@if (! $loop->last) · @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="avatar bg-green text-white me-2"><i class="ti ti-coin"></i></span>
                            <div class="text-secondary">Montant financé</div>
                            <x-renovation.origine type="enregistre" class="ms-auto" />
                        </div>
                        <div class="h1 mb-1"><x-renovation.montant :valeur="$suivi['total_finance']" :decimales="0" /></div>
                        <div class="small text-secondary">{{ $suivi['nb_financements'] }} financement(s) enregistré(s)</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="avatar bg-teal text-white me-2"><i class="ti ti-chart-pie"></i></span>
                            <div class="text-secondary">Couverture (devis)</div>
                            <x-renovation.origine type="calcule" class="ms-auto" />
                        </div>
                        @if ($c['taux'] !== null)
                            <div class="h1 mb-1">{{ number_format($c['taux'], 1, ',', ' ') }} %</div>
                            <div class="progress progress-sm mb-1"><div class="progress-bar bg-teal" style="width: {{ min(100, $c['taux']) }}%"></div></div>
                            <div class="small text-secondary">Sur {{ $c['nb_declares'] }} projet(s) avec coût déclaré · reste <x-renovation.montant :valeur="$c['reste_declare']" :decimales="0" /></div>
                        @else
                            <div class="h1 mb-1 text-secondary">—</div>
                            <div class="small text-secondary">Non calculable : aucun projet n'a de coût déclaré (devis).</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="avatar bg-orange text-white me-2"><i class="ti ti-alert-triangle"></i></span>
                            <div class="text-secondary">Sans financement</div>
                            <x-renovation.origine type="enregistre" class="ms-auto" />
                        </div>
                        <div class="h1 mb-1">{{ $suivi['sans_financement']->count() }}</div>
                        <div class="small text-secondary">projet(s) planifié(s) ou en cours sans aucun financement</div>
                    </div>
                </div>
            </div>
        </div>

        @if ($c['nb_estimes'] > 0)
            <div class="alert alert-warning d-flex align-items-start gap-2">
                <i class="ti ti-ruler-measure mt-1"></i>
                <div>
                    <strong>{{ $c['nb_estimes'] }} projet(s) n'ont qu'un coût estimé</strong> (barème de remplacement du module Analyse des risques) :
                    ils sont exclus du taux de couverture fiable.
                    Couverture indicative de ces projets :
                    <strong>{{ $c['taux_estime'] !== null ? number_format($c['taux_estime'], 1, ',', ' ') . ' %' : '—' }}</strong>
                    <x-renovation.origine type="estime" />
                </div>
            </div>
        @endif

        <div class="row row-cards mb-3">
            {{-- Répartition par source --}}
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title">Répartition par source</h3>
                        <div class="card-actions"><x-renovation.origine type="enregistre" /></div>
                    </div>
                    <div class="card-body">
                        @if ($suivi['total_finance'] > 0)
                            <div id="graphique-sources" style="min-height: 240px"></div>
                        @endif
                        <div class="mt-2">
                            @foreach ($suivi['sources'] as $s)
                                <div class="d-flex align-items-center py-1">
                                    <span class="avatar avatar-xs bg-{{ $s['couleur'] }}-lt me-2"><i class="ti ti-{{ $s['icone'] }}"></i></span>
                                    <span>{{ $s['libelle'] }}</span>
                                    <span class="ms-auto text-secondary small me-3">{{ $s['nombre'] }} ligne(s)</span>
                                    <strong class="me-2"><x-renovation.montant :valeur="$s['montant']" :decimales="0" /></strong>
                                    <span class="badge bg-{{ $s['couleur'] }}-lt" style="min-width: 3.5rem">{{ number_format($s['part'], 1, ',', ' ') }} %</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Par statut --}}
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title">Projets et montants financés par statut</h3>
                        <div class="card-actions"><x-renovation.origine type="enregistre" /></div>
                    </div>
                    <div class="card-body">
                        <div id="graphique-statuts" style="min-height: 280px"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards mb-3">
            {{-- Projets prioritaires (scoring) --}}
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-target-arrow me-1"></i> Projets prioritaires</h3>
                        <div class="card-actions"><x-renovation.origine type="calcule" /></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>Projet</th><th>Réseau</th><th class="text-end">Financé</th><th>Priorité</th></tr></thead>
                            <tbody>
                                @forelse ($suivi['prioritaires'] as $p)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.projets.show', $p) }}" class="fw-medium">{{ $p->titre }}</a>
                                            <div class="small"><span class="badge bg-{{ $p->couleurStatut() }}-lt">{{ $p->libelleStatut() }}</span></div>
                                        </td>
                                        <td>
                                            <span class="font-monospace">{{ $p->reseau?->code }}</span>
                                            @if ($p->reseau)
                                                <div><span class="badge bg-{{ $p->reseau->couleurEtat() }}-lt">{{ $p->reseau->libelleEtat() }}</span></div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <x-renovation.montant :valeur="$p->totalFinance()" :decimales="0" />
                                            <div class="small text-secondary">sur <x-renovation.montant :valeur="$p->priorite['cout']['montant'] ?? 0" :decimales="0" />
                                                ({{ \App\Services\Renovation\ModeleCout::COURTS[$p->priorite['cout']['source']] ?? 'estimé' }})</div>
                                        </td>
                                        <td style="min-width: 9rem">
                                            <div class="d-flex align-items-center gap-2"
                                                 title="{{ collect($p->priorite['facteurs'])->map(fn ($f) => $f['libelle'] . ' : ' . $f['points'] . '/' . $f['max'])->implode(' · ') }}">
                                                <span class="badge bg-{{ $p->priorite['couleur'] }} text-white">{{ $p->priorite['score'] }}</span>
                                                <span class="small">{{ $p->priorite['libelle'] }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-secondary text-center py-4">Tous les projets sont terminés.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer small text-secondary">
                        Score sur 100 : risque du réseau (40 pts), avancement (20 pts), reste à financer (25 pts), alertes des capteurs (8 pts) et qualité de l'eau (7 pts), lues dans les modules Surveillance et Qualité de l'eau.
                        Il classe les projets à partir des données enregistrées ; il ne mesure ni l'urgence ni une probabilité de panne.
                    </div>
                </div>
            </div>

            {{-- Alertes financières --}}
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-shield-exclamation me-1"></i> Alertes financières</h3>
                        <div class="card-actions"><span class="badge bg-secondary-lt">{{ count($alertes) }}</span></div>
                    </div>
                    <div class="list-group list-group-flush overflow-auto" style="max-height: 420px">
                        @forelse ($alertes as $a)
                            @php [$couleur, $icone, $libelleNiveau] = $niveaux[$a['niveau']]; @endphp
                            <div class="list-group-item">
                                <div class="d-flex gap-2">
                                    <i class="ti ti-{{ $icone }} text-{{ $couleur }} mt-1"></i>
                                    <div class="flex-fill">
                                        <div class="d-flex align-items-center gap-2">
                                            <strong>{{ $a['titre'] }}</strong>
                                            <span class="badge bg-{{ $couleur }}-lt ms-auto">{{ $libelleNiveau }}</span>
                                            <span class="badge bg-secondary-lt font-monospace" title="Règle de détection">{{ $a['code'] }}</span>
                                        </div>
                                        <div class="small text-secondary">{{ $a['message'] }}</div>
                                        @if ($a['projet_id'])
                                            <a href="{{ route('admin.projets.show', $a['projet_id']) }}" class="small">Voir le projet <i class="ti ti-arrow-right"></i></a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="list-group-item text-center text-secondary py-4">
                                <i class="ti ti-circle-check text-green" style="font-size: 2rem"></i>
                                <div>Aucune anomalie détectée par les règles.</div>
                            </div>
                        @endforelse
                    </div>
                    @if ($alertes)
                        <div class="card-footer">
                            <button type="button" class="btn btn-outline-pink w-100" id="bouton-alertes-ia">
                                <i class="ti ti-sparkles me-1"></i> Expliquer ces alertes {{ $iaConfiguree ? 'avec l\'IA' : '(mode secours)' }}
                            </button>
                            <div id="resultat-alertes-ia" class="mt-3"></div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($suivi['sans_financement']->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Projets sans aucun financement</h3></div>
                <div class="list-group list-group-flush">
                    @foreach ($suivi['sans_financement'] as $p)
                        <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
                            <a href="{{ route('admin.projets.show', $p) }}" class="fw-medium">{{ $p->titre }}</a>
                            <span class="badge bg-{{ $p->couleurStatut() }}-lt">{{ $p->libelleStatut() }}</span>
                            <span class="font-monospace small text-secondary">{{ $p->reseau?->code }}</span>
                            <a href="{{ route('admin.financements.create', ['projet' => $p->id]) }}" class="btn btn-sm btn-outline-primary ms-auto">
                                <i class="ti ti-plus me-1"></i> Ajouter un financement
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
    <script src="{{ asset('js/renovation-ia.js') }}"></script>
    <script>
        (function () {
            const dt = v => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v) + ' DT';
            const couleurs = { blue: '#066fd1', teal: '#0ca678', purple: '#ae3ec9', pink: '#d6336c' };
            const sources = @json($suivi['nb_projets'] ? $suivi['sources'] : []);
            const statuts = @json($suivi['nb_projets'] ? $suivi['par_statut'] : []);

            const zoneSources = document.getElementById('graphique-sources');
            if (zoneSources) {
                new ApexCharts(zoneSources, {
                    chart: { type: 'donut', height: 240, fontFamily: 'inherit' },
                    series: sources.map(s => s.montant),
                    labels: sources.map(s => s.libelle),
                    colors: sources.map(s => couleurs[s.couleur] || '#667382'),
                    legend: { show: false },
                    dataLabels: { enabled: false },
                    tooltip: { y: { formatter: dt } },
                    plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total', formatter: w => dt(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) } } } } },
                }).render();
            }

            const zoneStatuts = document.getElementById('graphique-statuts');
            if (zoneStatuts) {
                new ApexCharts(zoneStatuts, {
                    chart: { type: 'bar', height: 280, fontFamily: 'inherit', toolbar: { show: false } },
                    series: [
                        { name: 'Projets', type: 'column', data: statuts.map(s => s.nombre) },
                        { name: 'Montant financé', type: 'line', data: statuts.map(s => s.montant) },
                    ],
                    xaxis: { categories: statuts.map(s => s.libelle) },
                    yaxis: [
                        { title: { text: 'Projets' }, forceNiceScale: true, labels: { formatter: v => Math.round(v) } },
                        { opposite: true, title: { text: 'DT' }, labels: { formatter: v => new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(v) } },
                    ],
                    colors: ['#066fd1', '#0ca678'],
                    stroke: { width: [0, 3] },
                    markers: { size: 5 },
                    tooltip: { y: [{ formatter: v => v + ' projet(s)' }, { formatter: dt }] },
                    plotOptions: { bar: { columnWidth: '40%', borderRadius: 4 } },
                }).render();
            }

            const bouton = document.getElementById('bouton-alertes-ia');
            bouton?.addEventListener('click', async function () {
                const cible = document.getElementById('resultat-alertes-ia');
                bouton.disabled = true;
                RenovationIA.attente(cible, "Analyse des alertes en cours… (jusqu'à une minute)");
                try {
                    const [ok, d] = await RenovationIA.envoyer(@json(route('admin.renovation.alertes-ia')));
                    ok ? cible.innerHTML = RenovationIA.alertes(d) : RenovationIA.erreur(cible, d);
                } catch (e) {
                    RenovationIA.erreur(cible, 'Connexion au serveur impossible.');
                } finally {
                    bouton.disabled = false;
                }
            });
        })();
    </script>
@endpush
