@extends('layouts.admin')

@section('title', 'Financements')
@section('pretitle', 'Rénovation')
@section('subtitle')
    {{ $financements->firstItem() ?? 0 }}-{{ $financements->lastItem() ?? 0 }} sur {{ $financements->total() }} financements
@endsection

@section('actions')
    <div class="btn-list">
        <a href="{{ route('admin.financements.export', request()->query()) }}" class="btn" title="Exporte la sélection filtrée">
            <i class="ti ti-file-spreadsheet me-1"></i> Exporter (Excel)
        </a>
        <a href="{{ route('admin.financements.import') }}" class="btn">
            <i class="ti ti-upload me-1"></i> Importer
        </a>
        <a href="{{ route('admin.financements.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Nouveau financement
        </a>
    </div>
@endsection

@section('content')
    @include('admin.renovation._nav')

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- Totaux de la sélection (tous les résultats filtrés, pas seulement la page) --}}
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-4">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="text-secondary">Total de la sélection <x-renovation.origine type="enregistre" /></div>
                    <div class="h1 mb-0"><x-renovation.montant :valeur="$total" /></div>
                    <div class="small text-secondary">{{ $nombre }} financement(s)</div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="row g-2">
                        @foreach (\App\Models\Financement::SOURCES as $cle => $libelle)
                            @php $montant = (float) ($totaux[$cle]->total ?? 0); @endphp
                            <div class="col-6 col-md-3">
                                <a href="{{ route('admin.financements.index', array_merge(request()->except('page'), ['source' => $cle])) }}" class="text-reset text-decoration-none">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm bg-{{ \App\Models\Financement::couleurSourceDe($cle) }}-lt"><i class="ti ti-{{ \App\Models\Financement::iconeSourceDe($cle) }}"></i></span>
                                        <div class="lh-sm">
                                            <div class="small text-secondary">{{ $libelle }}</div>
                                            <div class="fw-bold"><x-renovation.montant :valeur="$montant" :decimales="0" /></div>
                                            <div class="small text-secondary">{{ $total > 0 ? number_format(100 * $montant / $total, 1, ',', ' ') : '0' }} %</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" for="f-projet">Projet</label>
                    <select name="projet" id="f-projet" class="form-select">
                        <option value="">Tous les projets</option>
                        @foreach ($projets as $id => $titre)
                            <option value="{{ $id }}" @selected((string) request('projet') === (string) $id)>{{ $titre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="f-source">Source</label>
                    <select name="source" id="f-source" class="form-select">
                        <option value="">Toutes</option>
                        @foreach (\App\Models\Financement::SOURCES as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected(request('source') === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="f-origine">Origine</label>
                    <select name="origine" id="f-origine" class="form-select">
                        <option value="">Toutes</option>
                        <option value="manuel" @selected(request('origine') === 'manuel')>Saisie manuelle</option>
                        <option value="scenario" @selected(request('origine') === 'scenario')>Scénario appliqué</option>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label" for="f-min">Min (DT)</label>
                    <input type="number" name="min" id="f-min" min="0" step="0.01" value="{{ request('min') }}" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="f-max">Max (DT)</label>
                    <input type="number" name="max" id="f-max" min="0" step="0.01" value="{{ request('max') }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill"><i class="ti ti-filter me-1"></i> Filtrer</button>
                    <a href="{{ route('admin.financements.index') }}" class="btn btn-icon" title="Effacer les filtres"><i class="ti ti-x"></i></a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr><th>Date</th><th>Projet</th><th>Source</th><th>Origine</th><th class="text-end">Montant</th><th class="w-1"></th></tr>
                </thead>
                <tbody>
                    @forelse ($financements as $f)
                        <tr>
                            <td class="text-secondary text-nowrap">{{ $f->created_at->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('admin.projets.show', $f->projet_id) }}" class="fw-medium">{{ $f->projet?->titre }}</a>
                                <div class="small text-secondary font-monospace">{{ $f->projet?->reseau?->code }}</div>
                            </td>
                            <td>
                                <span class="badge bg-{{ \App\Models\Financement::couleurSourceDe($f->source) }}-lt">
                                    <i class="ti ti-{{ \App\Models\Financement::iconeSourceDe($f->source) }} me-1"></i>{{ $f->libelleSource() }}
                                </span>
                            </td>
                            <td class="small">
                                @if ($f->simulation)
                                    <a href="{{ route('admin.simulations.show', $f->simulation_id) }}"><i class="ti ti-adjustments-alt me-1"></i>{{ $f->simulation->nom }}</a>
                                @else
                                    <span class="text-secondary">Saisie manuelle</span>
                                @endif
                            </td>
                            <td class="text-end fw-medium"><x-renovation.montant :valeur="$f->montant" /></td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.financements.show', $f) }}" class="btn btn-sm"><i class="ti ti-eye me-1"></i> Voir</a>
                                    @can('update', $f)
                                        <a href="{{ route('admin.financements.edit', $f) }}" class="btn btn-sm btn-icon" title="Modifier"><i class="ti ti-edit"></i></a>
                                    @endcan
                                    @can('delete', $f)
                                        <form method="POST" action="{{ route('admin.financements.destroy', $f) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-icon btn-ghost-danger" title="Supprimer"
                                                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                    data-confirm-type="danger"
                                                    data-confirm-title="Supprimer ce financement ?"
                                                    data-confirm-message="Le financement de {{ number_format((float) $f->montant, 2, ',', ' ') }} DT sera définitivement supprimé."
                                                    data-confirm-button="Oui, supprimer">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-coin" style="font-size: 3rem"></i></div>
                                    <p class="empty-title">Aucun financement trouvé</p>
                                    <p class="empty-subtitle text-secondary">Modifiez les filtres ou enregistrez un nouveau financement.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $financements->links() }}
    </div>
@endsection
