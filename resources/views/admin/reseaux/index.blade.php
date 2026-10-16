@extends('layouts.admin')

@section('title', 'Réseaux')
@section('pretitle', 'Infrastructure')
@section('subtitle')
    {{ $reseaux->firstItem() ?? 0 }}-{{ $reseaux->lastItem() ?? 0 }} sur {{ $reseaux->total() }} éléments
@endsection

{{-- Filtres + bouton, en haut à droite --}}
@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex flex-wrap gap-2">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Code…" style="max-width: 9rem">
            <select name="zone" class="form-select" onchange="this.form.submit()">
                <option value="">Toutes les zones</option>
                @foreach ($zones as $id => $libelle)
                    <option value="{{ $id }}" @selected((string) request('zone') === (string) $id)>{{ $libelle }}</option>
                @endforeach
            </select>
            <select name="type" class="form-select" onchange="this.form.submit()">
                <option value="">Tous les types</option>
                @foreach (\App\Models\ReseauEau::TYPES as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(request('type') === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
            <select name="etat" class="form-select" onchange="this.form.submit()">
                <option value="">Tous les états</option>
                @foreach (\App\Models\ReseauEau::ETATS as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(request('etat') === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.reseaux.import') }}" class="btn">
            <i class="ti ti-file-import me-1"></i> Importer CSV
        </a>
        <a href="{{ route('admin.reseaux.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Nouveau réseau
        </a>
    </div>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Zone</th>
                        <th>Matériau</th>
                        <th>Âge</th>
                        <th>État</th>
                        <th>Risque</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reseaux as $reseau)
                        <tr>
                            <td>
                                <a href="{{ route('admin.reseaux.edit', $reseau) }}" class="fw-medium font-monospace">{{ $reseau->code }}</a>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm me-2 bg-azure-lt"><i class="ti ti-{{ $reseau->iconeType() }}"></i></span>
                                    {{ $reseau->libelleType() }}
                                </div>
                            </td>
                            <td>
                                {{ $reseau->zone->nom }}
                                <div class="small text-secondary">{{ $reseau->zone->commune }}</div>
                            </td>
                            <td class="text-secondary">{{ $reseau->libelleMateriau() }}</td>
                            <td class="text-secondary" title="Mis en service le {{ $reseau->date_mise_en_service->format('d/m/Y') }}">
                                {{ $reseau->age() }} an{{ $reseau->age() > 1 ? 's' : '' }}
                            </td>
                            <td>
                                <span class="badge bg-{{ $reseau->couleurEtat() }}-lt">{{ $reseau->libelleEtat() }}</span>
                            </td>
                            <td>
                                @php $risque = \App\Services\ScoreRisque::pourReseau($reseau); @endphp
                                <span class="badge bg-{{ $risque['couleur'] }}-lt" title="Score de risque sur 100">
                                    {{ $risque['score'] }} · {{ $risque['libelle'] }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.reseaux.edit', $reseau) }}" class="btn btn-sm">
                                        <i class="ti ti-edit me-1"></i> Modifier
                                    </a>
                                    <form method="POST" action="{{ route('admin.reseaux.destroy', $reseau) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-ghost-danger"
                                                data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                data-confirm-type="danger"
                                                data-confirm-title="Supprimer ce réseau ?"
                                                data-confirm-message="{{ $reseau->libelleType() }} {{ $reseau->code }} ({{ $reseau->zone->nom }}) sera définitivement supprimé, avec son historique."
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
                                    <div class="empty-icon"><i class="ti ti-share" style="font-size: 3rem"></i></div>
                                    <p class="empty-title">Aucun réseau trouvé</p>
                                    <p class="empty-subtitle text-secondary">Modifiez les filtres ou ajoutez un nouvel élément.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $reseaux->links() }}
    </div>
@endsection
