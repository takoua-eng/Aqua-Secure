@extends('layouts.admin')

@section('title', 'Projets de rénovation')
@section('pretitle', 'Rénovation')
@section('subtitle')
    {{ $projets->firstItem() ?? 0 }}-{{ $projets->lastItem() ?? 0 }} sur {{ $projets->total() }} projets
@endsection

@section('actions')
    <a href="{{ route('admin.projets.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i> Nouveau projet
    </a>
@endsection

@section('content')
    @include('admin.renovation._nav')

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <x-surveillance.kpi libelle="Projets" :valeur="$stats['total']" icone="building" couleur="blue"
                                :lien="route('admin.projets.index')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-surveillance.kpi libelle="Chantiers en cours" :valeur="$stats['en_cours']" icone="progress" couleur="orange"
                                :lien="route('admin.projets.index', ['statut' => 'en_cours'])" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-surveillance.kpi libelle="Montant financé" :valeur="number_format($stats['finance'], 0, ',', ' ') . ' DT'" icone="coin" couleur="green"
                                :lien="route('admin.financements.index')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-surveillance.kpi libelle="Sans financement" :valeur="$stats['sans_financement']" icone="alert-triangle" couleur="red"
                                :lien="route('admin.projets.index', ['financement' => 'sans'])" />
        </div>
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Recherche</label>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Titre ou code réseau…">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="">Tous</option>
                        @foreach (\App\Models\ProjetRenovation::STATUTS as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected(request('statut') === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Zone</label>
                    <select name="zone" class="form-select">
                        <option value="">Toutes</option>
                        @foreach ($zones as $id => $libelle)
                            <option value="{{ $id }}" @selected((string) request('zone') === (string) $id)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Financement</label>
                    <select name="financement" class="form-select">
                        <option value="">Tous</option>
                        <option value="avec" @selected(request('financement') === 'avec')>Avec financement</option>
                        <option value="sans" @selected(request('financement') === 'sans')>Sans financement</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Tri</label>
                    <select name="tri" class="form-select">
                        <option value="">Récents</option>
                        <option value="titre" @selected(request('tri') === 'titre')>Titre</option>
                        <option value="finance" @selected(request('tri') === 'finance')>Financé</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill"><i class="ti ti-filter me-1"></i> Filtrer</button>
                    <a href="{{ route('admin.projets.index') }}" class="btn btn-icon" title="Effacer les filtres"><i class="ti ti-x"></i></a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Projet</th>
                        <th>Réseau</th>
                        <th>Statut</th>
                        <th class="text-end">Financé</th>
                        <th style="min-width: 11rem">Couverture</th>
                        <th>Priorité</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projets as $projet)
                        @php
                            $cout = $projet->priorite['cout'];
                            $finance = $projet->totalFinance();
                            $pct = $cout['montant'] ? min(100, round(100 * $finance / $cout['montant'])) : null;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.projets.show', $projet) }}" class="fw-medium">{{ $projet->titre }}</a>
                                @if ($projet->publie)
                                    <span class="badge bg-azure-lt ms-1" title="Visible sur la page publique"><i class="ti ti-world"></i></span>
                                @endif
                                <div class="small text-secondary">{{ $projet->financements_count }} financement(s)</div>
                            </td>
                            <td>
                                <span class="font-monospace text-nowrap">{{ $projet->reseau?->code }}</span>
                                <div class="small text-secondary">{{ $projet->reseau?->zone?->nom }}
                                    @if ($projet->reseau) · <span class="text-{{ $projet->reseau->couleurEtat() }}">{{ $projet->reseau->libelleEtat() }}</span>@endif
                                </div>
                            </td>
                            <td><span class="badge bg-{{ $projet->couleurStatut() }}-lt"><i class="ti ti-{{ $projet->iconeStatut() }} me-1"></i>{{ $projet->libelleStatut() }}</span></td>
                            <td class="text-end"><x-renovation.montant :valeur="$finance" :decimales="0" /></td>
                            <td>
                                @if ($pct !== null)
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress progress-sm flex-fill">
                                            <div class="progress-bar bg-{{ $cout['source'] === 'declare' ? 'teal' : 'yellow' }}" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="small text-nowrap">{{ $pct }} %</span>
                                    </div>
                                    <div class="small text-secondary">
                                        de <x-renovation.montant :valeur="$cout['montant']" :decimales="0" />
                                        {{ $cout['source'] === 'declare' ? '(devis)' : '(estimé)' }}
                                    </div>
                                @else
                                    <span class="text-secondary small">Coût inconnu</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $projet->priorite['couleur'] }} text-white"
                                      title="{{ collect($projet->priorite['facteurs'])->map(fn ($f) => $f['libelle'] . ' : ' . $f['points'] . '/' . $f['max'])->implode(' · ') }}">
                                    {{ $projet->priorite['score'] }}
                                </span>
                                <span class="small">{{ $projet->priorite['libelle'] }}</span>
                            </td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.projets.show', $projet) }}" class="btn btn-sm"><i class="ti ti-eye me-1"></i> Voir</a>
                                    @can('update', $projet)
                                        <a href="{{ route('admin.projets.edit', $projet) }}" class="btn btn-sm"><i class="ti ti-edit"></i></a>
                                    @endcan
                                    @if ($projet->financements_count === 0)
                                        <form method="POST" action="{{ route('admin.projets.destroy', $projet) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-ghost-danger"
                                                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                    data-confirm-type="danger"
                                                    data-confirm-title="Supprimer ce projet ?"
                                                    data-confirm-message="Le projet « {{ $projet->titre }} » sera définitivement supprimé."
                                                    data-confirm-button="Oui, supprimer">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-building" style="font-size: 3rem"></i></div>
                                    <p class="empty-title">Aucun projet trouvé</p>
                                    <p class="empty-subtitle text-secondary">Modifiez les filtres ou créez un nouveau projet de rénovation.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $projets->links() }}
    </div>
@endsection
