@extends('layouts.admin')

@section('title', 'Points de prélèvement')
@section('pretitle', "Qualité de l'eau")
@section('subtitle')
    {{ $points->firstItem() ?? 0 }}-{{ $points->lastItem() ?? 0 }} sur {{ $points->total() }} points
@endsection

{{-- Filtres + bouton, en haut à droite --}}
@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex flex-wrap gap-2">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Code, nom, adresse…" style="max-width: 12rem">
            <select name="reseau" class="form-select" onchange="this.form.submit()">
                <option value="">Tous les réseaux</option>
                @foreach ($reseaux as $id => $libelle)
                    <option value="{{ $id }}" @selected((string) request('reseau') === (string) $id)>{{ $libelle }}</option>
                @endforeach
            </select>
            <select name="type" class="form-select" onchange="this.form.submit()">
                <option value="">Tous les types</option>
                @foreach (\App\Models\PointPrelevement::TYPES as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(request('type') === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
            <select name="actif" class="form-select" onchange="this.form.submit()">
                <option value="">En service ou non</option>
                <option value="1" @selected(request('actif') === '1')>En service</option>
                <option value="0" @selected(request('actif') === '0')>Hors service</option>
            </select>
            <label class="form-check form-switch align-self-center mb-0" title="Points en service sans analyse depuis plus de {{ \App\Models\PointPrelevement::JOURS_RETARD }} jours">
                <input class="form-check-input" type="checkbox" name="retard" value="1" @checked(request()->boolean('retard')) onchange="this.form.submit()">
                <span class="form-check-label text-nowrap">En retard</span>
            </label>
        </form>
        <a href="{{ route('admin.prelevements.carte') }}" class="btn">
            <i class="ti ti-map-pin me-1"></i> Carte
        </a>
        <a href="{{ route('admin.prelevements.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Nouveau point
        </a>
    </div>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
            @if (session('point_a_desactiver'))
                <a href="{{ route('admin.prelevements.edit', session('point_a_desactiver')) }}" class="alert-link ms-1">Mettre ce point hors service</a>
            @endif
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Point</th>
                        <th>Type</th>
                        <th>Réseau</th>
                        <th>Analyses</th>
                        <th>Conformité</th>
                        <th>État</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($points as $point)
                        @php
                            $taux = $point->analyses_count ? round($point->analyses_conformes_count / $point->analyses_count * 100, 1) : null;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.prelevements.show', $point) }}" class="fw-medium font-monospace">{{ $point->code }}</a>
                            </td>
                            <td>
                                {{ $point->nom }}
                                <div class="small text-secondary">{{ $point->adresse }}</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm me-2 bg-cyan-lt"><i class="ti ti-{{ $point->iconeType() }}"></i></span>
                                    {{ $point->libelleType() }}
                                </div>
                            </td>
                            <td>
                                <span class="font-monospace">{{ $point->reseau->code }}</span>
                                <div class="small text-secondary">{{ $point->reseau->zone->nom }}</div>
                            </td>
                            <td>
                                {{-- Lien vers la liste des analyses filtrée sur ce point --}}
                                <a href="{{ route('admin.analyses.index', ['point' => $point->id]) }}"
                                   class="badge {{ $point->analyses_count ? 'bg-blue-lt' : 'bg-secondary-lt' }}">
                                    {{ $point->analyses_count }} analyse{{ $point->analyses_count > 1 ? 's' : '' }}
                                </a>
                                @if ($point->derniere_analyse)
                                    <div class="small text-secondary">dernière le {{ \Illuminate\Support\Carbon::parse($point->derniere_analyse)->format('d/m/Y') }}</div>
                                @endif
                                @if ($retard = \App\Models\PointPrelevement::retardAnalyse($point->actif, $point->derniere_analyse))
                                    <span class="badge bg-orange-lt mt-1"><i class="ti ti-clock-exclamation me-1"></i>{{ $retard }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ \App\Models\PointPrelevement::couleurTaux($taux) }}-lt" title="Part des analyses conformes">
                                    {{ $taux === null ? '—' : number_format($taux, 1, ',', ' ') . ' %' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $point->actif ? 'green' : 'secondary' }}-lt">{{ $point->libelleActif() }}</span>
                            </td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.prelevements.show', $point) }}" class="btn btn-sm">
                                        <i class="ti ti-eye me-1"></i> Fiche
                                    </a>
                                    <a href="{{ route('admin.prelevements.edit', $point) }}" class="btn btn-sm">
                                        <i class="ti ti-edit me-1"></i> Modifier
                                    </a>
                                    <form method="POST" action="{{ route('admin.prelevements.destroy', $point) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-ghost-danger"
                                                data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                data-confirm-type="danger"
                                                data-confirm-title="Supprimer ce point ?"
                                                data-confirm-message="Le point {{ $point->code }} ({{ $point->nom }}) sera définitivement supprimé. Un point qui a des analyses ne peut pas être supprimé."
                                                data-confirm-button="Oui, supprimer">
                                            <i class="ti ti-trash me-1"></i> Supprimer
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-droplet" style="font-size: 3rem"></i></div>
                                    <p class="empty-title">Aucun point de prélèvement trouvé</p>
                                    <p class="empty-subtitle text-secondary">Modifiez les filtres ou ajoutez un nouveau point.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $points->links() }}
    </div>
@endsection
