@extends('layouts.admin')

@section('title', 'Historique des interventions')
@section('pretitle', 'Exploitation · Traçabilité')
@section('subtitle', 'Chaque création et chaque changement de statut, avec son auteur.')

@section('actions')
    <form method="GET" class="d-flex gap-2">
        <select name="statut" class="form-select" onchange="this.form.submit()" aria-label="Filtrer par nouveau statut">
            <option value="">Tous les changements</option>
            @foreach (\App\Models\Intervention::STATUTS as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected($statut === $valeur)>Passage à « {{ $libelle }} »</option>
            @endforeach
        </select>
    </form>
@endsection

@section('content')
    @include('admin.incidents._navigation')

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Intervention</th>
                        <th>Incident</th>
                        <th>Changement</th>
                        <th>Par</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($historique as $ligne)
                        <tr>
                            <td class="text-nowrap">{{ $ligne->created_at?->format('d/m/Y H:i') }}</td>
                            <td><a href="{{ route('admin.interventions.show', $ligne->intervention_id) }}" class="font-monospace">#{{ $ligne->intervention_id }}</a></td>
                            <td>
                                @if ($ligne->intervention?->incident)
                                    <a href="{{ route('admin.incidents.show', $ligne->intervention->incident) }}">
                                        #{{ $ligne->intervention->incident->id }} · {{ $ligne->intervention->incident->libelleType() }}
                                    </a>
                                    <div class="small text-secondary font-monospace">{{ $ligne->intervention->incident->reseau->code }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($ligne->ancien_statut)
                                    <span class="badge bg-secondary-lt">{{ \App\Models\Intervention::libelleStatutDe($ligne->ancien_statut) }}</span>
                                    <i class="ti ti-arrow-right text-secondary mx-1"></i>
                                @else
                                    <span class="text-secondary small me-1">Création :</span>
                                @endif
                                <span class="badge bg-{{ \App\Models\Intervention::couleurStatutDe($ligne->nouveau_statut) }}-lt">{{ \App\Models\Intervention::libelleStatutDe($ligne->nouveau_statut) }}</span>
                            </td>
                            <td>{{ $ligne->user?->nom ?? 'Système' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-history" style="font-size: 3rem"></i></div>
                                    <p class="empty-title">Aucun changement enregistré</p>
                                    <p class="empty-subtitle text-secondary">L'historique se remplit automatiquement à chaque création ou changement de statut d'une intervention.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $historique->links() }}
    </div>
@endsection
