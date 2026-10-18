@extends('layouts.admin')

@section('title', 'Interventions')
@section('pretitle', 'Exploitation')
@section('subtitle')
    {{ $interventions->firstItem() ?? 0 }}-{{ $interventions->lastItem() ?? 0 }} sur {{ $interventions->total() }} intervention{{ $interventions->total() > 1 ? 's' : '' }}
@endsection

@section('actions')
    <a href="{{ route('admin.interventions.historique') }}" class="btn">
        <i class="ti ti-history me-1"></i> Historique des statuts
    </a>
@endsection

@section('content')
    @include('admin.incidents._navigation')

    @if ($errors->any())
        <div class="alert alert-danger"><i class="ti ti-alert-circle me-1"></i> {{ $errors->first() }}</div>
    @endif

    <div class="row row-deck row-cards mb-3">
        @foreach ([
            ['calendar-event',    'blue',   $indicateurs['planifiee'], 'Planifiées', ['statut' => 'planifiee']],
            ['tool',              'orange', $indicateurs['en_cours'],  'En cours',   ['statut' => 'en_cours']],
            ['circle-check',      'green',  $indicateurs['terminee'],  'Terminées',  ['statut' => 'terminee']],
            ['clock-exclamation', 'red',    $indicateurs['retard'],    'En retard',  ['retard' => 1]],
        ] as [$icone, $couleur, $valeur, $libelle, $lien])
            <div class="col-6 col-lg-3">
                <a href="{{ route('admin.interventions.index', $lien) }}" class="card card-sm card-link text-reset text-decoration-none">
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

    <form method="GET" class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="filtre-statut">Statut</label>
                    <select id="filtre-statut" name="statut" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        @foreach (\App\Models\Intervention::STATUTS as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($filtres['statut'] === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="filtre-type">Type d'incident</label>
                    <select id="filtre-type" name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        @foreach (\App\Models\Incident::TYPES as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($filtres['type'] === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label" for="filtre-incident">Incident n°</label>
                    <input type="text" inputmode="numeric" id="filtre-incident" name="incident" value="{{ $filtres['incident'] }}" class="form-control" placeholder="Ex : 12">
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label" for="filtre-du">Du</label>
                    <input type="date" id="filtre-du" name="du" value="{{ $filtres['du'] }}" class="form-control">
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label" for="filtre-au">Au</label>
                    <input type="date" id="filtre-au" name="au" value="{{ $filtres['au'] }}" class="form-control">
                </div>
                <div class="col-6 col-md-6 col-lg-2">
                    <label class="form-check form-switch mb-2" title="Planifiées dont la date est passée sans démarrage">
                        <input class="form-check-input" type="checkbox" name="retard" value="1" @checked($filtres['retard']) onchange="this.form.submit()">
                        <span class="form-check-label">En retard</span>
                    </label>
                </div>
                <div class="col-md-6 col-lg-12 d-flex gap-2 justify-content-lg-end">
                    <button type="submit" class="btn"><i class="ti ti-filter me-1"></i> Filtrer</button>
                    @if (request()->hasAny(['statut', 'type', 'incident', 'du', 'au', 'retard']))
                        <a href="{{ route('admin.interventions.index') }}" class="btn btn-ghost-secondary"><i class="ti ti-x me-1"></i> Effacer</a>
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
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Incident</th>
                        <th>Réseau</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($interventions as $intervention)
                        <tr>
                            <td><a href="{{ route('admin.interventions.show', $intervention) }}" class="fw-medium font-monospace">#{{ $intervention->id }}</a></td>
                            <td>
                                {{ $intervention->date_intervention->format('d/m/Y') }}
                                <div class="small text-secondary">{{ $intervention->statut === 'planifiee' ? 'prévue' : 'exécution' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $intervention->couleurStatut() }}-lt"><i class="ti ti-{{ $intervention->iconeStatut() }} me-1"></i>{{ $intervention->libelleStatut() }}</span>
                                @if ($intervention->estEnRetard())
                                    <div><span class="badge bg-orange-lt mt-1"><i class="ti ti-clock-exclamation me-1"></i>En retard</span></div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.incidents.show', $intervention->incident) }}">
                                    #{{ $intervention->incident->id }} · {{ $intervention->incident->libelleType() }}
                                </a>
                                <div><span class="badge bg-{{ $intervention->incident->couleurGravite() }}-lt">{{ $intervention->incident->libelleGravite() }}</span></div>
                            </td>
                            <td>
                                <span class="font-monospace">{{ $intervention->incident->reseau->code }}</span>
                                <div class="small text-secondary">{{ $intervention->incident->reseau->zone->nom }}</div>
                            </td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.interventions.show', $intervention) }}" class="btn btn-sm">
                                        <i class="ti ti-eye me-1"></i> Suivi
                                    </a>
                                    @if ($intervention->estModifiable())
                                        <a href="{{ route('admin.interventions.edit', $intervention) }}" class="btn btn-sm">
                                            <i class="ti ti-edit me-1"></i> Modifier
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-tool" style="font-size: 3rem"></i></div>
                                    @if (array_sum($indicateurs) - $indicateurs['retard'] === 0)
                                        <p class="empty-title">Aucune intervention enregistrée</p>
                                        <p class="empty-subtitle text-secondary">Une intervention se planifie depuis la fiche d'un incident.</p>
                                        <div class="empty-action">
                                            <a href="{{ route('admin.incidents.index') }}" class="btn btn-primary"><i class="ti ti-alert-triangle me-1"></i> Voir les incidents</a>
                                        </div>
                                    @else
                                        <p class="empty-title">Aucune intervention ne correspond à ces critères</p>
                                        <div class="empty-action">
                                            <a href="{{ route('admin.interventions.index') }}" class="btn"><i class="ti ti-x me-1"></i> Effacer les filtres</a>
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
        {{ $interventions->links() }}
    </div>
@endsection
