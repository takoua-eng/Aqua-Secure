@extends('layouts.admin')

@section('title', 'Relevés')
@section('pretitle', 'Surveillance')
@section('subtitle')
    {{ $releves->firstItem() ?? 0 }}-{{ $releves->lastItem() ?? 0 }} sur {{ $releves->total() }} relevés
@endsection

@section('actions')
    <div class="btn-list">
        {{-- L'export reprend les filtres affichés --}}
        <a href="{{ route('admin.releves.export', request()->query()) }}" class="btn">
            <i class="ti ti-file-spreadsheet me-1"></i> Export CSV
        </a>
        <a href="{{ route('admin.releves.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Nouveau relevé
        </a>
    </div>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        {{-- Filtres --}}
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Capteur</label>
                    <select name="capteur" class="form-select">
                        <option value="">Tous les capteurs</option>
                        @foreach ($capteurs as $id => $libelle)
                            <option value="{{ $id }}" @selected((string) request('capteur') === (string) $id)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">État</label>
                    <select name="etat" class="form-select">
                        <option value="">Tous</option>
                        <option value="normal" @selected(request('etat') === 'normal')>Normal</option>
                        <option value="hors_seuil" @selected(request('etat') === 'hors_seuil')>Hors seuil</option>
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
                    <button class="btn btn-primary flex-fill"><i class="ti ti-filter me-1"></i> Filtrer</button>
                    <a href="{{ route('admin.releves.index') }}" class="btn btn-icon" title="Effacer les filtres"><i class="ti ti-x"></i></a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Capteur</th>
                        <th>Réseau</th>
                        <th>Valeur</th>
                        <th>État</th>
                        <th>Remarque</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($releves as $releve)
                        <tr>
                            <td class="text-secondary text-nowrap">{{ $releve->date_releve->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.capteurs.show', $releve->capteur) }}" class="font-monospace text-nowrap">{{ $releve->capteur->code }}</a>
                                <div class="small text-secondary">{{ $releve->capteur->libelleTypeMesure() }}</div>
                            </td>
                            <td class="font-monospace text-secondary text-nowrap">{{ $releve->capteur->reseau->code }}</td>
                            <td class="fw-medium text-nowrap">
                                {{ $releve->capteur->avecUnite($releve->valeur) }}
                                <div class="small text-secondary fw-normal">Seuils : {{ $releve->capteur->plageSeuils() }}</div>
                            </td>
                            <td><x-surveillance.etat-releve :releve="$releve" /></td>
                            <td class="text-secondary">{{ \Illuminate\Support\Str::limit($releve->remarque, 40) ?: '—' }}</td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.releves.edit', $releve) }}" class="btn btn-sm">
                                        <i class="ti ti-edit me-1"></i> Modifier
                                    </a>
                                    <form method="POST" action="{{ route('admin.releves.destroy', $releve) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-ghost-danger"
                                                data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                data-confirm-type="danger"
                                                data-confirm-title="Supprimer ce relevé ?"
                                                data-confirm-message="Relevé de {{ $releve->capteur->avecUnite($releve->valeur) }} ({{ $releve->capteur->code }}) du {{ $releve->date_releve->format('d/m/Y H:i') }}."
                                                data-confirm-button="Oui, supprimer">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-chart-bar" style="font-size: 3rem"></i></div>
                                    <p class="empty-title">Aucun relevé trouvé</p>
                                    <p class="empty-subtitle text-secondary">Modifiez les filtres ou ajoutez un relevé.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $releves->links() }}
    </div>
@endsection