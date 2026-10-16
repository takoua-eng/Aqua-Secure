@extends('layouts.admin')

@section('title', 'Analyses')
@section('pretitle', "Qualité de l'eau")
@section('subtitle')
    {{ $analyses->firstItem() ?? 0 }}-{{ $analyses->lastItem() ?? 0 }} sur {{ $analyses->total() }} analyses
@endsection

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        {{-- L'export reprend les filtres en cours --}}
        <a href="{{ route('admin.analyses.export', request()->only(['point', 'conforme', 'du', 'au'])) }}" class="btn">
            <i class="ti ti-file-spreadsheet me-1"></i> Exporter (Excel / CSV)
        </a>
        <a href="{{ route('admin.analyses.create', array_filter(['point' => request('point')])) }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Nouvelle analyse
        </a>
    </div>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- Filtres : point, résultat, période --}}
    <form method="GET" class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Point de prélèvement</label>
                    <select name="point" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous les points</option>
                        @foreach ($points as $id => $libelle)
                            <option value="{{ $id }}" @selected((string) request('point') === (string) $id)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Résultat</label>
                    <select name="conforme" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="1" @selected(request('conforme') === '1')>Conformes</option>
                        <option value="0" @selected(request('conforme') === '0')>Non conformes</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Du</label>
                    <input type="date" name="du" value="{{ request('du') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Au</label>
                    <input type="date" name="au" value="{{ request('au') }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn w-100"><i class="ti ti-filter me-1"></i> Filtrer</button>
                    @if (request()->hasAny(['point', 'conforme', 'du', 'au']))
                        <a href="{{ route('admin.analyses.index') }}" class="btn btn-ghost-secondary" title="Effacer les filtres"><i class="ti ti-x"></i></a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    {{-- Indicateurs sur les analyses filtrées --}}
    <div class="row row-deck row-cards mb-3">
        @foreach ([
            ['flask',         'azure', $indicateurs['total'],        'Analyses'],
            ['circle-check',  'green', $indicateurs['conformes'],    'Conformes'],
            ['alert-octagon', 'red',   $indicateurs['nonConformes'], 'Non conformes'],
            ['percentage',    \App\Models\PointPrelevement::couleurTaux($indicateurs['taux']),
                $indicateurs['taux'] === null ? '—' : number_format($indicateurs['taux'], 1, ',', ' ') . ' %', 'Taux de conformité'],
        ] as [$icone, $couleur, $valeur, $libelle])
            <div class="col-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body d-flex align-items-center">
                        <span class="avatar bg-{{ $couleur }} text-white me-3"><i class="ti ti-{{ $icone }}"></i></span>
                        <div>
                            <div class="h2 mb-0">{{ $valeur }}</div>
                            <div class="text-secondary">{{ $libelle }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Point</th>
                        @foreach (\App\Models\AnalyseQualite::PARAMETRES as $cle => $infos)
                            <th class="text-end" title="Norme : {{ \App\Models\AnalyseQualite::norme($cle) }}">{{ $infos['libelle'] }}</th>
                        @endforeach
                        <th>Résultat</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($analyses as $analyse)
                        <tr>
                            <td>
                                <a href="{{ route('admin.analyses.show', $analyse) }}" class="fw-medium">{{ $analyse->date_prelevement->format('d/m/Y') }}</a>
                                <div class="small text-secondary">{{ $analyse->laboratoire }}</div>
                            </td>
                            <td>
                                <a href="{{ route('admin.prelevements.show', $analyse->point) }}" class="font-monospace">{{ $analyse->point->code }}</a>
                                <div class="small text-secondary">{{ $analyse->point->nom }}</div>
                            </td>
                            {{-- Valeurs hors norme en rouge --}}
                            @foreach (array_keys(\App\Models\AnalyseQualite::PARAMETRES) as $parametre)
                                <td class="text-end {{ $analyse->estHorsNorme($parametre) ? 'text-red fw-bold' : '' }}" style="font-variant-numeric: tabular-nums"
                                    @if ($analyse->estHorsNorme($parametre)) title="Hors norme ({{ \App\Models\AnalyseQualite::norme($parametre) }})" @endif>
                                    {{ \App\Models\AnalyseQualite::formater($parametre, $analyse->{$parametre}) }}
                                </td>
                            @endforeach
                            <td><span class="badge bg-{{ $analyse->couleurConformite() }}-lt">{{ $analyse->libelleConformite() }}</span></td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.analyses.show', $analyse) }}" class="btn btn-sm">
                                        <i class="ti ti-file-description me-1"></i> Bulletin
                                    </a>
                                    <a href="{{ route('admin.analyses.edit', $analyse) }}" class="btn btn-sm">
                                        <i class="ti ti-edit me-1"></i> Modifier
                                    </a>
                                    <form method="POST" action="{{ route('admin.analyses.destroy', $analyse) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-ghost-danger"
                                                data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                data-confirm-type="danger"
                                                data-confirm-title="Supprimer cette analyse ?"
                                                data-confirm-message="L'analyse du {{ $analyse->date_prelevement->format('d/m/Y') }} au point {{ $analyse->point->code }} sera définitivement supprimée."
                                                data-confirm-button="Oui, supprimer">
                                            <i class="ti ti-trash me-1"></i> Supprimer
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-flask" style="font-size: 3rem"></i></div>
                                    <p class="empty-title">Aucune analyse trouvée</p>
                                    <p class="empty-subtitle text-secondary">Modifiez les filtres ou saisissez une nouvelle analyse.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $analyses->links() }}
    </div>
@endsection
