@extends('layouts.admin')

@section('title', 'Zones')
@section('pretitle', 'Infrastructure')
@section('subtitle')
    {{ $zones->firstItem() ?? 0 }}-{{ $zones->lastItem() ?? 0 }} sur {{ $zones->total() }} zones
@endsection

@section('actions')
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nom ou commune…">
        </form>
        <a href="{{ route('admin.zones.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Nouvelle zone
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
                        <th>Zone</th>
                        <th>Commune</th>
                        <th>Population</th>
                        <th>Réseaux</th>
                        <th>Qualité de l'eau</th>
                        <th>Créée le</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($zones as $zone)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm me-2 bg-blue-lt"><i class="ti ti-map"></i></span>
                                    <a href="{{ route('admin.zones.edit', $zone) }}" class="fw-medium">{{ $zone->nom }}</a>
                                </div>
                            </td>
                            <td class="text-secondary">{{ $zone->commune }}</td>
                            <td class="text-secondary">{{ number_format($zone->population, 0, ',', ' ') }} hab.</td>
                            <td>
                                {{-- Lien vers la liste des réseaux filtrée sur cette zone --}}
                                <a href="{{ route('admin.reseaux.index', ['zone' => $zone->id]) }}"
                                   class="badge {{ $zone->reseaux_count ? 'bg-blue-lt' : 'bg-secondary-lt' }}">
                                    {{ $zone->reseaux_count }} réseau{{ $zone->reseaux_count > 1 ? 'x' : '' }}
                                </a>
                            </td>
                            <td>
                                {{-- Part des analyses conformes sur les points de la zone (module Qualité de l'eau) --}}
                                @php $qualiteZone = $qualite[$zone->id] ?? null; @endphp
                                <span class="badge bg-{{ \App\Models\PointPrelevement::couleurTaux($qualiteZone['taux'] ?? null) }}-lt"
                                      title="{{ $qualiteZone ? $qualiteZone['total'] . ' analyse(s)' : 'Aucune analyse' }}">
                                    {{ $qualiteZone ? number_format($qualiteZone['taux'], 1, ',', ' ') . ' % conforme' : '—' }}
                                </span>
                            </td>
                            <td class="text-secondary">{{ $zone->created_at->format('d/m/Y') }}</td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.zones.edit', $zone) }}" class="btn btn-sm">
                                        <i class="ti ti-edit me-1"></i> Modifier
                                    </a>
                                    <form method="POST" action="{{ route('admin.zones.destroy', $zone) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-ghost-danger"
                                                data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                data-confirm-type="danger"
                                                data-confirm-title="Supprimer cette zone ?"
                                                data-confirm-message="La zone {{ $zone->nom }} ({{ $zone->commune }}) sera définitivement supprimée."
                                                data-confirm-button="Oui, supprimer">
                                            <i class="ti ti-trash me-1"></i> Supprimer
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-map" style="font-size: 3rem"></i></div>
                                    <p class="empty-title">Aucune zone trouvée</p>
                                    <p class="empty-subtitle text-secondary">Modifiez la recherche ou créez une nouvelle zone.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $zones->links() }}
    </div>
@endsection
