@extends('layouts.admin')

@section('title', $simulation->nom)
@section('pretitle', 'Rénovation · Scénario What If?')

@section('actions')
    <div class="btn-list">
        <a href="{{ route('admin.simulations.rapport', $simulation) }}" class="btn">
            <i class="ti ti-file-type-pdf me-1"></i> Rapport de décision (PDF)
        </a>
        @can('delete', $simulation)
            <form method="POST" action="{{ route('admin.simulations.destroy', $simulation) }}">
                @csrf
                @method('DELETE')
                <button type="button" class="btn btn-ghost-danger"
                        data-bs-toggle="modal" data-bs-target="#modal-confirm"
                        data-confirm-type="danger"
                        data-confirm-title="Supprimer cette simulation ?"
                        data-confirm-message="La simulation « {{ $simulation->nom }} » sera supprimée. Aucun financement réel n'est concerné."
                        data-confirm-button="Oui, supprimer">
                    <i class="ti ti-trash"></i>
                </button>
            </form>
        @endcan
    </div>
@endsection

@section('content')
    @include('admin.renovation._nav')

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @php
        $r = $simulation->resultats;
        $s = $simulation->scenarioRetenu();
        $k = $s['kpi'];
        $noms = collect($r['scenarios'])->map(fn ($x) => $x['lettre'] . ' · ' . $x['libelle'])->all();
        $aFinancer = collect($s['lignes'])->where('propose', '>', 0)->values();
        $etapes = [
            ['brouillon', 'Simulation enregistrée', $simulation->created_at, $simulation->auteur?->nom],
            ['soumise', 'Soumise pour validation', $simulation->soumise_le, $simulation->auteur?->nom],
            [$simulation->statut === 'rejetee' ? 'rejetee' : 'approuvee', $simulation->statut === 'rejetee' ? 'Rejetée' : 'Approuvée', $simulation->decide_le, $simulation->decideur?->nom],
            ['appliquee', 'Appliquée : financements créés', $simulation->appliquee_le, $simulation->decideur?->nom],
        ];
    @endphp

    {{-- Cycle de validation --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="badge bg-{{ $simulation->couleurStatut() }} text-white fs-5">{{ $simulation->libelleStatut() }}</span>
                <span class="text-secondary small">Stratégie retenue : <strong>{{ $noms[$simulation->strategie] }}</strong> ·
                    budget hypothétique <strong><x-renovation.montant :valeur="$simulation->budget" /></strong></span>
            </div>
            <ul class="steps steps-counter steps-{{ $simulation->statut === 'rejetee' ? 'red' : 'green' }} my-2">
                @foreach ($etapes as [$cle, $libelle, $date, $qui])
                    <li class="step-item {{ $date && ($loop->last || ! $etapes[$loop->index + 1][2]) ? 'active' : '' }}">
                        <div class="fw-medium">{{ $libelle }}</div>
                        <div class="small text-secondary">{{ $date ? $date->format('d/m/Y H:i') . ($qui ? ' · ' . $qui : '') : '—' }}</div>
                    </li>
                @endforeach
            </ul>
            @if ($simulation->commentaire_decision)
                <div class="mt-2 small"><strong>Commentaire de la décision :</strong> {{ $simulation->commentaire_decision }}</div>
            @endif
        </div>
    </div>

    @if ($ecarts)
        <div class="alert alert-warning">
            <div class="fw-bold"><i class="ti ti-refresh-alert me-1"></i>Les données réelles ont changé depuis cette simulation</div>
            <ul class="mb-1 ps-3">@foreach ($ecarts as $ecart)<li>{{ $ecart }}</li>@endforeach</ul>
            Ce scénario ne peut plus être appliqué tel quel : relancez une simulation sur les données actuelles.
        </div>
    @endif

    {{-- Actions du cycle de validation --}}
    @can('soumettre', $simulation)
        <form method="POST" action="{{ route('admin.simulations.soumettre', $simulation) }}" class="card card-body mb-3 d-flex flex-row flex-wrap align-items-center gap-2">
            @csrf
            <i class="ti ti-send text-primary"></i>
            <span>Ce scénario est un brouillon privé. Soumettez-le pour qu'un administrateur l'examine.</span>
            <button type="button" class="btn btn-primary ms-auto"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm" data-confirm-type="primary"
                    data-confirm-title="Soumettre ce scénario ?" data-confirm-message="Un administrateur pourra l'approuver ou le rejeter. Rien n'est appliqué à ce stade."
                    data-confirm-button="Oui, soumettre">
                <i class="ti ti-send me-1"></i> Demander une validation
            </button>
        </form>
    @endcan

    @can('decider', $simulation)
        <form method="POST" action="{{ route('admin.simulations.decider', $simulation) }}" class="card mb-3">
            @csrf
            <div class="card-header"><h3 class="card-title"><i class="ti ti-gavel me-1"></i> Décision de l'administrateur</h3></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label required">Décision</label>
                        <div class="form-selectgroup">
                            <label class="form-selectgroup-item">
                                <input type="radio" name="decision" value="approuver" class="form-selectgroup-input" @checked(old('decision', 'approuver') === 'approuver')>
                                <span class="form-selectgroup-label"><i class="ti ti-check text-green me-1"></i>Approuver</span>
                            </label>
                            <label class="form-selectgroup-item">
                                <input type="radio" name="decision" value="rejeter" class="form-selectgroup-input" @checked(old('decision') === 'rejeter')>
                                <span class="form-selectgroup-label"><i class="ti ti-x text-red me-1"></i>Rejeter</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="decision-source">Source réelle des fonds (si approuvé)</label>
                        <select name="source" id="decision-source" class="form-select @error('source') is-invalid @enderror">
                            <option value="">— Choisir —</option>
                            @foreach (\App\Models\Financement::SOURCES as $valeur => $libelle)
                                <option value="{{ $valeur }}" @selected(old('source', $sourceSuggeree['source'] ?? null) === $valeur)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback">{{ $errors->first('source') }}</div>
                        @if ($sourceSuggeree)
                            <div class="small text-secondary mt-1"><i class="ti ti-brain me-1"></i>Suggestion du modèle IA :
                                <strong>{{ \App\Models\Financement::libelleSourceDe($sourceSuggeree['source']) }}</strong>
                                ({{ $sourceSuggeree['part'] }} % pondéré par les montants). À vous de décider.</div>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="decision-commentaire">Commentaire (obligatoire si rejet)</label>
                        <textarea name="commentaire" id="decision-commentaire" rows="2" maxlength="1000"
                                  class="form-control @error('commentaire') is-invalid @enderror">{{ old('commentaire') }}</textarea>
                        <div class="invalid-feedback">{{ $errors->first('commentaire') }}</div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="button" class="btn btn-primary"
                        data-bs-toggle="modal" data-bs-target="#modal-confirm" data-confirm-type="primary"
                        data-confirm-title="Enregistrer la décision ?" data-confirm-message="L'approbation ne crée encore aucun financement : il faudra ensuite appliquer le scénario."
                        data-confirm-button="Confirmer">
                    <i class="ti ti-gavel me-1"></i> Enregistrer la décision
                </button>
            </div>
        </form>
    @endcan

    @can('appliquer', $simulation)
        <form method="POST" action="{{ route('admin.simulations.appliquer', $simulation) }}" class="card mb-3 border-green">
            @csrf
            <div class="card-header bg-green-lt"><h3 class="card-title"><i class="ti ti-player-play me-1"></i> Appliquer le scénario approuvé</h3></div>
            <div class="card-body">
                <p class="mb-2">
                    <strong>{{ $aFinancer->count() }} financement(s) réel(s)</strong> seront <strong>ajoutés</strong>
                    (source : {{ \App\Models\Financement::libelleSourceDe($simulation->source_financement) }}),
                    pour un total de <strong><x-renovation.montant :valeur="$aFinancer->sum('propose')" /></strong>.
                    Les financements existants ne sont ni modifiés ni supprimés. Les données seront revérifiées juste avant.
                </p>
                <ul class="small mb-3">
                    @foreach ($aFinancer as $l)
                        <li>{{ $l['titre'] }} : <x-renovation.montant :valeur="$l['propose']" /> {{ $l['etat'] === 'partiel' ? '(partiel)' : '' }}</li>
                    @endforeach
                </ul>
                <label class="form-check">
                    <input type="checkbox" class="form-check-input" name="confirmation" value="1" required>
                    <span class="form-check-label">Je confirme la création de ces financements réels.</span>
                </label>
            </div>
            <div class="card-footer text-end">
                <button type="button" class="btn btn-success" @disabled($ecarts || $aFinancer->isEmpty())
                        data-bs-toggle="modal" data-bs-target="#modal-confirm" data-confirm-type="success"
                        data-confirm-title="Appliquer ce scénario ?" data-confirm-message="Les financements listés seront créés en base. Cette action est tracée."
                        data-confirm-button="Oui, appliquer">
                    <i class="ti ti-check me-1"></i> Appliquer
                </button>
            </div>
        </form>
    @endcan

    @if ($simulation->statut === 'appliquee')
        <div class="card mb-3 border-green">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-circle-check text-green me-1"></i> Financements créés par cette décision</h3></div>
            <div class="list-group list-group-flush">
                @foreach ($simulation->financements as $f)
                    <a href="{{ route('admin.financements.show', $f) }}" class="list-group-item list-group-item-action d-flex">
                        <span>Projet #{{ $f->projet_id }} · {{ $f->libelleSource() }}</span>
                        <strong class="ms-auto"><x-renovation.montant :valeur="$f->montant" /></strong>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Résultats calculés --}}
    <div class="row row-cards mb-3">
        @foreach ([
            ['Budget alloué', number_format($k['alloue'], 0, ',', ' ') . ' DT', 'coin', 'purple', 'reste ' . number_format($k['reste'], 0, ',', ' ') . ' DT'],
            ['Projets achevés', $k['nb_complets'] . ' / ' . $k['nb_candidats'], 'circle-check', 'green', 'financés en entier'],
            ['Financements partiels', $k['nb_partiels'], 'progress', 'orange', 'ne permettent pas d\'achever'],
            ['Priorité couverte', number_format($k['priorite_couverte_pct'], 1, ',', ' ') . ' %', 'target-arrow', 'blue', 'besoin couvert ' . number_format($k['besoin_couvert_pct'], 1, ',', ' ') . ' %'],
        ] as [$titre, $valeur, $icone, $couleur, $detail])
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-1">
                            <span class="avatar avatar-sm bg-{{ $couleur }}-lt me-2"><i class="ti ti-{{ $icone }}"></i></span>
                            <span class="small text-secondary">{{ $titre }}</span>
                            <x-renovation.origine type="calcule" class="ms-auto" />
                        </div>
                        <div class="h2 mb-0">{{ $valeur }}</div>
                        <div class="small text-secondary">{{ $detail }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><i class="ti ti-scale me-1"></i> Comparaison des stratégies (au moment du calcul)</h3></div>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Indicateur</th>
                        @foreach ($r['scenarios'] as $cle => $sc)
                            <th class="text-center {{ $cle === $simulation->strategie ? 'bg-primary-lt' : '' }}">
                                {{ $noms[$cle] }}
                                @if ($cle === $r['recommande'])<div><span class="badge bg-green-lt"><i class="ti ti-star me-1"></i>Recommandée (règle)</span></div>@endif
                                @if ($cle === $simulation->strategie)<div><span class="badge bg-primary-lt">Retenue</span></div>@endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        ['Priorité couverte', 'priorite_couverte_pct', '%'], ['Projets achevés', 'nb_complets', ''],
                        ['Réseaux dégradés traités', 'critiques_couverts', ''], ['Financements partiels', 'nb_partiels', ''],
                        ['Budget non utilisé', 'reste', 'DT'],
                    ] as [$libelle, $cle, $unite])
                        <tr>
                            <td class="text-secondary">{{ $libelle }}</td>
                            @foreach ($r['scenarios'] as $cs => $sc)
                                <td class="text-center {{ $cs === $simulation->strategie ? 'bg-primary-lt' : '' }}">
                                    @if (in_array($cs, $r['meilleurs'][$cle] ?? [], true))<i class="ti ti-trophy text-yellow me-1"></i>@endif
                                    {{ $unite === 'DT' ? number_format($sc['kpi'][$cle], 0, ',', ' ') . ' DT' : ($unite === '%' ? number_format($sc['kpi'][$cle], 1, ',', ' ') . ' %' : $sc['kpi'][$cle]) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-secondary"><i class="ti ti-calculator me-1"></i>{{ $r['raison'] }}</div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><i class="ti ti-table me-1"></i> Répartition — {{ $noms[$simulation->strategie] }}</h3></div>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table">
                <thead><tr><th>Projet</th><th>Priorité</th><th class="text-end">Coût</th><th class="text-end">Déjà financé</th><th class="text-end">Proposé</th><th>Après</th><th>Résultat</th></tr></thead>
                <tbody>
                    @foreach ($s['lignes'] as $l)
                        <tr>
                            <td>
                                <a href="{{ route('admin.projets.show', $l['id']) }}" class="fw-medium">{{ $l['titre'] }}</a>
                                <div class="small text-secondary">{{ $l['raison'] }}</div>
                            </td>
                            <td><span class="badge bg-{{ \App\Services\Renovation\ScorePriorite::niveau($l['score'])['couleur'] }} text-white">{{ $l['score'] }}</span></td>
                            <td class="text-end text-nowrap"><x-renovation.montant :valeur="$l['cout']" :decimales="0" />
                                <div><x-renovation.origine :type="in_array($l['cout_source'], ['declare', 'predit'], true) ? $l['cout_source'] : 'estime'" /></div></td>
                            <td class="text-end"><x-renovation.montant :valeur="$l['finance']" :decimales="0" /></td>
                            <td class="text-end fw-bold {{ $l['propose'] > 0 ? 'text-teal' : 'text-secondary' }}"><x-renovation.montant :valeur="$l['propose']" /></td>
                            <td class="small text-nowrap">{{ number_format($l['avant_pct'], 1, ',', ' ') }} % → <strong>{{ number_format($l['apres_pct'], 1, ',', ' ') }} %</strong></td>
                            <td>
                                <span class="badge bg-{{ ['complet' => 'green', 'partiel' => 'orange', 'aucun' => 'secondary'][$l['etat']] }}-lt">
                                    {{ ['complet' => 'Financé en entier', 'partiel' => 'Partiel', 'aucun' => 'Non financé'][$l['etat']] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-alert-triangle me-1"></i> Risques et compromis</h3></div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        @forelse ($s['risques'] as $risque)
                            <li class="d-flex gap-2 mb-2"><i class="ti ti-point-filled text-{{ ['danger' => 'red', 'warning' => 'orange', 'info' => 'azure'][$risque['niveau']] }} mt-1"></i>{{ $risque['texte'] }}</li>
                        @empty
                            <li class="text-secondary">Aucun risque particulier détecté.</li>
                        @endforelse
                    </ul>
                    <div class="small"><strong>Compromis :</strong> {{ $s['compromis'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-bulb me-1"></i> Hypothèses</h3>
                    <div class="card-actions"><x-renovation.origine type="hypothese" /></div></div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @foreach ($r['hypotheses'] as $h)
                            <li class="d-flex gap-2 mb-1"><i class="ti ti-bulb text-purple mt-1"></i>{{ $h }}</li>
                        @endforeach
                        @foreach ($r['diagnostic']['messages'] as $m)
                            <li class="d-flex gap-2 mb-1"><i class="ti ti-info-circle text-orange mt-1"></i>{{ $m }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Interprétation IA --}}
    <div class="card mb-3 border-pink">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-sparkles text-pink me-1"></i> Justification et recommandation</h3>
            <div class="card-actions btn-list">
                <x-renovation.origine type="ia" />
                @can('update', $simulation)
                    <button type="button" class="btn btn-sm btn-outline-pink" id="bouton-analyse">
                        <i class="ti ti-sparkles me-1"></i> {{ $simulation->analyse_ia ? 'Régénérer' : 'Générer' }} l'analyse
                    </button>
                @endcan
            </div>
        </div>
        <div class="card-body" id="zone-analyse">
            @if ($simulation->analyse_ia)
                @include('admin.renovation.simulations._analyse-ia', ['resultat' => $simulation->analyse_ia, 'noms' => $noms])
            @else
                <p class="text-secondary mb-0">Aucune analyse enregistrée pour ce scénario.</p>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/renovation-ia.js') }}"></script>
    @php $urlAnalyseJs = route('admin.simulations.analyser', $simulation); @endphp
    <script>
        document.getElementById('bouton-analyse')?.addEventListener('click', async function () {
            const zone = document.getElementById('zone-analyse');
            this.disabled = true;
            RenovationIA.attente(zone, "Analyse du scénario en cours… (jusqu'à une minute)");
            try {
                const [ok, d] = await RenovationIA.envoyer(@json($urlAnalyseJs));
                ok ? window.location.reload() : RenovationIA.erreur(zone, d);
            } catch (e) {
                RenovationIA.erreur(zone, 'Connexion au serveur impossible.');
            } finally {
                this.disabled = false;
            }
        });
    </script>
@endpush
