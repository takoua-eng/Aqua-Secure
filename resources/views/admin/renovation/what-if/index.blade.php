@extends('layouts.admin')

@section('title', 'What If? Engine')
@section('pretitle', 'Rénovation · Simulation de décisions financières')
@section('subtitle', 'Testez des répartitions hypothétiques avant de toucher aux financements réels.')

@section('actions')
    <a href="{{ route('admin.simulations.index') }}" class="btn">
        <i class="ti ti-history me-1"></i> Simulations enregistrées
    </a>
@endsection

@push('styles')
<style>
    /* Binôme 3 — styles propres à cette page (couleurs Tabler uniquement) */
    .wi-budget { font-size: 2.25rem; font-weight: 700; line-height: 1.1; color: var(--tblr-purple); }
    .wi-range { accent-color: var(--tblr-purple); }
    .wi-projets { max-height: 340px; overflow-y: auto; }
    .wi-projets .form-check { padding: .4rem .5rem .4rem 2.2rem; border-radius: var(--tblr-border-radius); margin: 0; }
    .wi-projets .form-check:hover { background: var(--tblr-bg-surface-secondary); }
    .wi-resultats { transition: opacity .15s ease; }
    .wi-resultats.wi-calcul { opacity: .55; pointer-events: none; }
    .wi-strategie-col { cursor: pointer; }
    .wi-strategie-col.wi-active { background: var(--tblr-primary-lt, rgba(6,111,209,.08)); }
    .wi-raison { font-size: .78rem; color: var(--tblr-secondary); max-width: 26rem; }
    @media (min-width: 1200px) { .wi-parametres { position: sticky; top: 1rem; } }
</style>
@endpush

@section('content')
    @include('admin.renovation._nav')

    {{-- Bandeau : ce qui est hypothétique, calculé, réel ou interprété --}}
    <div class="card mb-3 bg-purple-lt border-0">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
            <i class="ti ti-flask-2 text-purple"></i>
            <strong>Bac à sable :</strong>
            <span>le budget manipulé ici est hypothétique ; aucun financement réel n'est créé ni modifié.</span>
            <span class="ms-lg-auto d-flex flex-wrap gap-1">
                <x-renovation.origine type="enregistre" />
                <x-renovation.origine type="hypothese" />
                <x-renovation.origine type="calcule" />
                <x-renovation.origine type="estime" />
                <x-renovation.origine type="ia" />
            </span>
        </div>
    </div>

    @if (empty($candidats))
        <div class="card">
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-adjustments-alt" style="font-size: 3rem"></i></div>
                <p class="empty-title">Aucun projet à simuler</p>
                <p class="empty-subtitle text-secondary">Créez des projets de rénovation pour pouvoir tester des répartitions de budget.</p>
                <div class="empty-action">
                    <a href="{{ route('admin.projets.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Nouveau projet</a>
                </div>
            </div>
        </div>
    @else
    <div class="row row-cards">
        {{-- ================= Paramètres ================= --}}
        <div class="col-xl-4">
            <div class="wi-parametres">
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-adjustments me-1"></i> Paramètres</h3>
                        <div class="card-actions"><x-renovation.origine type="hypothese" /></div>
                    </div>
                    <div class="card-body">
                        <label class="form-label" for="wi-budget-curseur">Budget hypothétique</label>
                        <div class="wi-budget mb-1" id="wi-budget-affiche">—</div>
                        <input type="range" class="form-range wi-range mb-2" id="wi-budget-curseur"
                               min="0" max="{{ $budgetMax }}" step="{{ max(1000, (int) round($budgetMax / 400 / 1000) * 1000) }}" value="{{ $budgetInitial }}">
                        <div class="input-group input-group-sm mb-2">
                            <input type="number" class="form-control" id="wi-budget-saisie" min="0" max="{{ \App\Services\Renovation\MoteurWhatIf::BUDGET_MAX }}"
                                   step="1000" value="{{ $budgetInitial }}" aria-label="Budget en dinars">
                            <span class="input-group-text">DT</span>
                        </div>
                        <div class="btn-group btn-group-sm w-100 mb-2" role="group" aria-label="Raccourcis de budget">
                            @foreach ([25, 50, 75, 100] as $part)
                                <button type="button" class="btn wi-raccourci" data-part="{{ $part }}">{{ $part }} %</button>
                            @endforeach
                        </div>
                        <div class="small text-secondary">
                            Besoin total des projets candidats : <strong><x-renovation.montant :valeur="$besoinTotal" :decimales="0" /></strong>
                            <x-renovation.origine type="calcule" />
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title"><i class="ti ti-route me-1"></i> Stratégie à détailler</h3></div>
                    <div class="card-body">
                        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column">
                            @foreach ($strategies as $cle => $s)
                                <label class="form-selectgroup-item flex-fill">
                                    <input type="radio" name="wi-strategie" value="{{ $cle }}" class="form-selectgroup-input" @checked($loop->first)>
                                    <div class="form-selectgroup-label d-flex align-items-start p-2 text-start">
                                        <span class="avatar avatar-sm bg-primary-lt me-2 fw-bold">{{ $s['lettre'] }}</span>
                                        <div>
                                            <div class="fw-medium"><i class="ti ti-{{ $s['icone'] }} me-1"></i>{{ $s['libelle'] }}</div>
                                            <div class="small text-secondary">{{ $s['description'] }}</div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <div class="small text-secondary mt-2">Les 4 stratégies sont toujours calculées et comparées ; celle-ci est détaillée.</div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title"><i class="ti ti-settings me-1"></i> Contraintes</h3></div>
                    <div class="card-body">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="wi-partiel">
                            <span class="form-check-label">Autoriser un financement partiel du reliquat</span>
                        </label>
                        <label class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="wi-estimes" checked>
                            <span class="form-check-label">Inclure les projets sans devis (coût estimé)</span>
                        </label>
                        <div class="border-top pt-2">
                            <div class="fw-medium small mb-1"><i class="ti ti-dice-5 me-1"></i>Test de robustesse — incertitude sur les coûts <x-renovation.origine type="hypothese" /></div>
                            <label class="form-label small mb-0 d-flex" for="wi-incertitude-estime">Coûts estimés : <strong class="ms-auto">± <span id="wi-incertitude-estime-valeur">30</span> %</strong></label>
                            <input type="range" class="form-range wi-range" id="wi-incertitude-estime" min="0" max="60" step="5" value="30">
                            <label class="form-label small mb-0 d-flex" for="wi-incertitude-devis">Coûts sur devis : <strong class="ms-auto">± <span id="wi-incertitude-devis-valeur">10</span> %</strong></label>
                            <input type="range" class="form-range wi-range" id="wi-incertitude-devis" min="0" max="30" step="5" value="10">
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-list-check me-1"></i> Projets inclus</h3>
                        <div class="card-actions btn-list">
                            <button type="button" class="btn btn-sm" id="wi-tous">Tous</button>
                            <button type="button" class="btn btn-sm" id="wi-aucun">Aucun</button>
                        </div>
                    </div>
                    <div class="card-body pb-0">
                        <input type="search" class="form-control form-control-sm mb-2" id="wi-filtre" placeholder="Filtrer les projets…">
                    </div>
                    <div class="card-body pt-0 wi-projets" id="wi-projets">
                        @foreach ($candidats as $c)
                            <label class="form-check wi-projet" data-texte="{{ mb_strtolower($c['titre'] . ' ' . $c['reseau'] . ' ' . $c['zone']) }}">
                                <input class="form-check-input wi-inclus" type="checkbox" value="{{ $c['id'] }}" @checked($c['statut'] !== 'termine')
                                       @disabled($c['statut'] === 'termine')>
                                <span class="form-check-label d-block">
                                    <span class="d-flex align-items-center gap-1">
                                        <span class="badge bg-{{ \App\Services\Renovation\ScorePriorite::niveau($c['score'])['couleur'] }} text-white" title="Score de priorité">{{ $c['score'] }}</span>
                                        <span class="text-truncate">{{ $c['titre'] }}</span>
                                    </span>
                                    <span class="small text-secondary">
                                        <span class="font-monospace">{{ $c['reseau'] }}</span> ·
                                        <span class="text-{{ \App\Models\ReseauEau::couleurDe($c['etat_reseau']) }}">{{ \App\Models\ReseauEau::libelleEtatDe($c['etat_reseau']) }}</span> ·
                                        @if ($c['statut'] === 'termine')
                                            terminé
                                        @elseif ($c['cout'] !== null)
                                            reste <x-renovation.montant :valeur="max(0, $c['cout'] - $c['finance'])" :decimales="0" />
                                            {{ $c['cout_source'] === 'declare' ? '(devis)' : '(estimé)' }}
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" id="wi-simuler">
                                <i class="ti ti-player-play me-1"></i> Simuler
                            </button>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-pink" id="wi-ia">
                                    <i class="ti ti-sparkles me-1"></i> {{ $iaConfiguree ? 'Analyse IA' : 'Analyse (mode secours)' }}
                                </button>
                                <button type="button" class="btn btn-outline-success" id="wi-enregistrer" data-bs-toggle="modal" data-bs-target="#wi-modal-enregistrer">
                                    <i class="ti ti-device-floppy me-1"></i> Enregistrer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= Résultats ================= --}}
        <div class="col-xl-8">
            <div class="wi-resultats" id="wi-resultats">
                <div id="wi-erreur"></div>
                <div id="wi-diagnostic"></div>

                <div class="row row-cards mb-3" id="wi-kpi"></div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-scale me-1"></i> Comparatif des scénarios</h3>
                        <div class="card-actions d-flex align-items-center gap-2">
                            <span class="spinner-border spinner-border-sm text-secondary d-none" id="wi-chargement"></span>
                            <x-renovation.origine type="calcule" />
                        </div>
                    </div>
                    <div class="card-body pb-0"><div id="wi-graphique-comparaison" style="min-height: 260px"></div></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table table-sm" id="wi-tableau-comparaison"></table>
                    </div>
                    <div class="card-footer small text-secondary" id="wi-recommandation"></div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-arrows-right-left me-1"></i> Avant / après <span class="text-secondary fw-normal" id="wi-titre-avant-apres"></span></h3>
                    </div>
                    <div class="card-body"><div id="wi-graphique-avant-apres" style="min-height: 300px"></div></div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-table me-1"></i> Répartition proposée <span class="text-secondary fw-normal" id="wi-titre-repartition"></span></h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th><th>Projet</th><th>Priorité</th>
                                    <th class="text-end">Coût</th>
                                    <th class="text-end">Déjà financé <x-renovation.origine type="enregistre" /></th>
                                    <th class="text-end">Proposé <x-renovation.origine type="calcule" /></th>
                                    <th style="min-width: 9rem">Après</th>
                                    <th>Résultat</th>
                                </tr>
                            </thead>
                            <tbody id="wi-repartition"></tbody>
                        </table>
                    </div>
                </div>

                <div class="row row-cards mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><h3 class="card-title"><i class="ti ti-alert-triangle me-1"></i> Risques et compromis</h3></div>
                            <div class="card-body" id="wi-risques"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><h3 class="card-title"><i class="ti ti-bulb me-1"></i> Hypothèses du calcul</h3></div>
                            <div class="card-body" id="wi-hypotheses"></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3 border-pink">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-sparkles text-pink me-1"></i> Justification et recommandation</h3>
                        <div class="card-actions"><x-renovation.origine type="ia" /></div>
                    </div>
                    <div class="card-body" id="wi-analyse-ia">
                        <div class="text-secondary">
                            L'IA explique les scénarios calculés ci-dessus : pourquoi certains projets sont privilégiés, les compromis,
                            les risques, les données manquantes et une recommandation argumentée. Elle ne calcule rien et ne modifie aucune donnée.
                            @unless ($iaConfiguree)
                                <div class="mt-2"><i class="ti ti-info-circle me-1"></i>Aucune clé API n'est configurée : l'analyse sera rédigée par des règles fixes (mode secours).</div>
                            @endunless
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Enregistrement : le serveur recalcule le scénario, rien n'est appliqué --}}
    <div class="modal modal-blur fade" id="wi-modal-enregistrer" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form method="POST" action="{{ route('admin.simulations.store') }}" class="modal-content" id="wi-form-enregistrer">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-device-floppy me-1"></i> Enregistrer la simulation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label required" for="wi-nom">Nom de la simulation</label>
                    <input type="text" class="form-control" id="wi-nom" name="nom" required minlength="3" maxlength="120"
                           value="Simulation du {{ now()->format('d/m/Y H:i') }}">
                    <div class="small text-secondary mt-2" id="wi-resume-enregistrement"></div>
                    <label class="form-check mt-3 mb-0">
                        <input class="form-check-input" type="checkbox" name="soumettre" value="1">
                        <span class="form-check-label">Soumettre directement pour validation par un administrateur</span>
                    </label>
                    <div id="wi-champs-caches"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endsection

@push('scripts')
    @unless (empty($candidats))
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
        <script src="{{ asset('js/renovation-ia.js') }}"></script>
        <script>
            window.WhatIfConfig = {
                urlSimuler: @json(route('admin.whatif.simuler')),
                urlAnalyser: @json(route('admin.whatif.analyser')),
                besoinTotal: @json($besoinTotal),
                @php $strategiesJs = collect($strategies)->map(fn ($s) => ['lettre' => $s['lettre'], 'libelle' => $s['libelle']]); @endphp
                strategies: @json($strategiesJs),
                etats: @json(\App\Models\ReseauEau::ETATS),
            };
        </script>
        <script src="{{ asset('js/what-if.js') }}"></script>
    @endunless
@endpush
