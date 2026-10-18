@extends('layouts.admin')

@section('title', 'Incidents')
@section('pretitle', 'Exploitation')
@section('subtitle')
    {{ $incidents->firstItem() ?? 0 }}-{{ $incidents->lastItem() ?? 0 }} sur {{ $incidents->total() }} incident{{ $incidents->total() > 1 ? 's' : '' }}
@endsection

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.incidents.priorites') }}" class="btn">
            <i class="ti ti-list-numbers me-1"></i> Priorités
        </a>
        @can('create', \App\Models\Incident::class)
            <a href="{{ route('admin.incidents.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Nouvel incident
            </a>
        @endcan
    </div>
@endsection

@section('content')
    @include('admin.incidents._navigation')

    @if ($errors->any())
        <div class="alert alert-danger"><i class="ti ti-alert-circle me-1"></i> {{ $errors->first() }}</div>
    @endif

    {{-- Indicateurs sur l'ensemble des incidents (pas seulement la page affichée) --}}
    <div class="row row-deck row-cards mb-3">
        @foreach ([
            ['alert-triangle', 'azure',  $indicateurs['total'],        'Incidents enregistrés', []],
            ['clock-pause',    'red',    $indicateurs['nonTraites'],   'Non traités',           ['statut' => 'non_traite']],
            ['tool',           'orange', $indicateurs['enTraitement'], 'En traitement',         ['statut' => 'en_traitement']],
            ['urgent',         'red',    $indicateurs['critiques'],    'Critiques sans intervention', ['critiques' => 1]],
        ] as [$icone, $couleur, $valeur, $libelle, $lien])
            <div class="col-6 col-lg-3">
                <a href="{{ route('admin.incidents.index', $lien) }}" class="card card-sm card-link text-reset text-decoration-none">
                    <div class="card-body d-flex align-items-center">
                        <span class="avatar bg-{{ $couleur }} text-white me-3"><i class="ti ti-{{ $icone }}"></i></span>
                        <div>
                            <div class="h2 mb-0">{{ $valeur }}</div>
                            <div class="text-secondary">{{ $libelle }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    {{-- Recherche, filtres et tri : appliqués en base de données, sur tous les incidents --}}
    <form method="GET" class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="filtre-q">Recherche</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="search" id="filtre-q" name="q" value="{{ $filtres['q'] }}" class="form-control"
                               placeholder="N°, code réseau, zone, commune…" maxlength="100">
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="form-label" for="filtre-type">Type</label>
                    <select id="filtre-type" name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        @foreach (\App\Models\Incident::TYPES as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($filtres['type'] === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="form-label" for="filtre-gravite">Gravité</label>
                    <select id="filtre-gravite" name="gravite" class="form-select" onchange="this.form.submit()">
                        <option value="">Toutes</option>
                        @foreach (\App\Models\Incident::GRAVITES as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($filtres['gravite'] === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="form-label" for="filtre-statut">Statut</label>
                    <select id="filtre-statut" name="statut" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        @foreach (\App\Models\Incident::STATUTS as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($filtres['statut'] === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    <label class="form-label" for="filtre-zone">Zone</label>
                    <select id="filtre-zone" name="zone" class="form-select" onchange="this.form.submit()">
                        <option value="">Toutes les zones</option>
                        @foreach ($zones as $id => $libelle)
                            <option value="{{ $id }}" @selected($filtres['zone'] === $id)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="filtre-reseau">Réseau</label>
                    <select id="filtre-reseau" name="reseau" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous les réseaux</option>
                        @foreach ($reseaux as $id => $libelle)
                            <option value="{{ $id }}" @selected($filtres['reseau'] === $id)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label" for="filtre-du">Signalé du</label>
                    <input type="date" id="filtre-du" name="du" value="{{ $filtres['du'] }}" class="form-control">
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label" for="filtre-au">au</label>
                    <input type="date" id="filtre-au" name="au" value="{{ $filtres['au'] }}" class="form-control">
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label" for="filtre-tri">Trier par</label>
                    <select id="filtre-tri" name="tri" class="form-select" onchange="this.form.submit()">
                        @foreach (\App\Http\Controllers\Admin\IncidentController::TRIS as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($filtres['tri'] === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 col-lg-1">
                    <label class="form-label" for="filtre-ordre">Ordre</label>
                    <select id="filtre-ordre" name="ordre" class="form-select" onchange="this.form.submit()">
                        <option value="desc" @selected($filtres['ordre'] === 'desc')>↓</option>
                        <option value="asc" @selected($filtres['ordre'] === 'asc')>↑</option>
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-check form-switch mb-2" title="Critiques sans intervention depuis au moins {{ \App\Models\Incident::JOURS_ALERTE_CRITIQUE }} jour">
                        <input class="form-check-input" type="checkbox" name="critiques" value="1" @checked($filtres['critiques']) onchange="this.form.submit()">
                        <span class="form-check-label text-nowrap">Critiques non pris en charge</span>
                    </label>
                </div>
                <div class="col-md-12 col-lg d-flex gap-2 justify-content-lg-end">
                    <button type="submit" class="btn"><i class="ti ti-filter me-1"></i> Filtrer</button>
                    @if (request()->hasAny(['q', 'type', 'gravite', 'statut', 'zone', 'reseau', 'du', 'au', 'critiques', 'tri', 'ordre']))
                        <a href="{{ route('admin.incidents.index') }}" class="btn btn-ghost-secondary" title="Effacer les filtres">
                            <i class="ti ti-x me-1"></i> Effacer
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th class="w-1">N°</th>
                        <th>Signalé le</th>
                        <th>Type</th>
                        <th>Gravité</th>
                        <th>Réseau</th>
                        <th>Statut</th>
                        <th>Interventions</th>
                        <th>Priorité</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incidents as $incident)
                        <tr>
                            <td>
                                <a href="{{ route('admin.incidents.show', $incident) }}" class="fw-medium font-monospace">#{{ $incident->id }}</a>
                            </td>
                            <td>
                                {{ $incident->date_signalement->format('d/m/Y') }}
                                <div class="small text-secondary">il y a {{ $incident->joursDepuisSignalement() }} j</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm me-2 bg-{{ $incident->couleurGravite() }}-lt"><i class="ti ti-{{ $incident->iconeType() }}"></i></span>
                                    {{ $incident->libelleType() }}
                                </div>
                            </td>
                            <td><span class="badge bg-{{ $incident->couleurGravite() }}-lt">{{ $incident->libelleGravite() }}</span></td>
                            <td>
                                <span class="font-monospace">{{ $incident->reseau->code }}</span>
                                <div class="small text-secondary">{{ $incident->reseau->zone->nom }}</div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $incident->couleurStatut() }}-lt">{{ $incident->libelleStatut() }}</span>
                                @if ($incident->estEnAlerteCritique())
                                    <div><span class="badge bg-red mt-1 text-red-fg"><i class="ti ti-urgent me-1"></i>Non pris en charge</span></div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.interventions.index', ['incident' => $incident->id]) }}"
                                   class="badge {{ $incident->interventions->isNotEmpty() ? 'bg-blue-lt' : 'bg-secondary-lt' }}">
                                    {{ $incident->interventions->count() }} intervention{{ $incident->interventions->count() > 1 ? 's' : '' }}
                                </a>
                            </td>
                            <td>@include('admin.incidents._score', ['priorite' => $priorites[$incident->id]])</td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.incidents.show', $incident) }}" class="btn btn-sm">
                                        <i class="ti ti-eye me-1"></i> Fiche
                                    </a>
                                    <a href="{{ route('admin.incidents.edit', $incident) }}" class="btn btn-sm">
                                        <i class="ti ti-edit me-1"></i> Modifier
                                    </a>
                                    @can('delete', $incident)
                                        @if ($incident->interventions->isEmpty())
                                            <form method="POST" action="{{ route('admin.incidents.destroy', $incident) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-sm btn-ghost-danger"
                                                        data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                        data-confirm-type="danger"
                                                        data-confirm-title="Supprimer l'incident n° {{ $incident->id }} ?"
                                                        data-confirm-message="{{ $incident->libelleType() }} sur {{ $incident->reseau->code }}, signalé le {{ $incident->date_signalement->format('d/m/Y') }}. Cette action est définitive."
                                                        data-confirm-button="Oui, supprimer">
                                                    <i class="ti ti-trash me-1"></i> Supprimer
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-alert-triangle" style="font-size: 3rem"></i></div>
                                    @if ($indicateurs['total'] === 0)
                                        <p class="empty-title">Aucun incident enregistré</p>
                                        <p class="empty-subtitle text-secondary">
                                            Enregistrez le premier incident constaté sur un réseau : fuite, contamination ou coupure.
                                        </p>
                                        <div class="empty-action">
                                            <a href="{{ route('admin.incidents.create') }}" class="btn btn-primary">
                                                <i class="ti ti-plus me-1"></i> Ajouter un incident
                                            </a>
                                        </div>
                                    @else
                                        <p class="empty-title">Aucun incident ne correspond à ces critères</p>
                                        <p class="empty-subtitle text-secondary">Modifiez la recherche ou les filtres.</p>
                                        <div class="empty-action">
                                            <a href="{{ route('admin.incidents.index') }}" class="btn"><i class="ti ti-x me-1"></i> Effacer les filtres</a>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $incidents->links() }}
    </div>
@endsection
