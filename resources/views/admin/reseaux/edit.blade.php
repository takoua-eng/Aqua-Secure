@extends('layouts.admin')

@section('title', 'Modifier ' . $reseau->code)
@section('pretitle', 'Infrastructure')

@section('content')
    <form method="POST" action="{{ route('admin.reseaux.update', $reseau) }}" class="card" data-validate>
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('admin.reseaux._form')
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.reseaux.index') }}" class="btn btn-link">Annuler</a>

            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="primary"
                    data-confirm-title="Enregistrer les modifications ?"
                    data-confirm-message="Le réseau {{ $reseau->code }} va être mis à jour."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Mettre à jour
            </button>
        </div>
    </form>

    {{-- Score de risque, avec le détail des points --}}
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-gauge me-1"></i> Score de risque</h3>
            <div class="card-actions">
                <span class="badge bg-{{ $risque['couleur'] }} text-white fs-4">{{ $risque['score'] }} / 100 · {{ $risque['libelle'] }}</span>
            </div>
        </div>
        <div class="card-body">
            @foreach ($risque['facteurs'] as $facteur)
                <div class="mb-2">
                    <div class="d-flex small mb-1">
                        <span>{{ $facteur['libelle'] }}</span>
                        <span class="ms-auto fw-medium">{{ $facteur['points'] }} / {{ $facteur['max'] }} pts</span>
                    </div>
                    <div class="progress progress-sm">
                        <div class="progress-bar bg-{{ $risque['couleur'] }}" style="width: {{ $facteur['max'] ? $facteur['points'] / $facteur['max'] * 100 : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
            {{-- Prédiction de panne (loi de Weibull) --}}
            <div class="row text-center border-top pt-3 mt-3">
                @if ($prediction['horsService'])
                    <div class="col-12 text-danger"><i class="ti ti-alert-triangle me-1"></i> Élément déjà hors service : aucune prédiction.</div>
                @else
                    @foreach ([
                        ['Panne dans 1 an', number_format($prediction['un_an'], 1, ',', ' ') . ' %'],
                        ['Panne dans 5 ans', number_format($prediction['cinq_ans'], 1, ',', ' ') . ' %'],
                        ['Vie restante estimée', '≈ ' . $prediction['vie_restante'] . ' ans'],
                    ] as [$libelle, $valeur])
                        <div class="col-4">
                            <div class="h3 mb-0">{{ $valeur }}</div>
                            <div class="small text-secondary">{{ $libelle }}</div>
                        </div>
                    @endforeach
                @endif
            </div>
            <small class="form-hint mt-2">
                Calculé à partir des données enregistrées avant modification.
                Prédiction : loi de Weibull selon le matériau et l'âge, risque ×3 si l'élément est dégradé.
            </small>
        </div>
    </div>

    {{-- Historique des états (rempli automatiquement à chaque changement d'état) --}}
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-history me-1"></i> Historique des états</h3>
        </div>
        <div class="card-body">
            <ul class="timeline">
                @foreach ($reseau->historiques as $historique)
                    <li class="timeline-event">
                        <div class="timeline-event-icon bg-{{ \App\Models\ReseauEau::couleurDe($historique->nouvel_etat) }}-lt">
                            <i class="ti ti-{{ $historique->ancien_etat ? 'arrows-exchange' : 'plus' }}"></i>
                        </div>
                        <div class="card timeline-event-card">
                            <div class="card-body">
                                <div class="text-secondary float-end">{{ $historique->created_at->format('d/m/Y H:i') }}</div>
                                @if ($historique->ancien_etat)
                                    <span class="badge bg-{{ \App\Models\ReseauEau::couleurDe($historique->ancien_etat) }}-lt">{{ \App\Models\ReseauEau::libelleEtatDe($historique->ancien_etat) }}</span>
                                    <i class="ti ti-arrow-right mx-1"></i>
                                    <span class="badge bg-{{ \App\Models\ReseauEau::couleurDe($historique->nouvel_etat) }}-lt">{{ \App\Models\ReseauEau::libelleEtatDe($historique->nouvel_etat) }}</span>
                                @else
                                    Créé avec l'état
                                    <span class="badge bg-{{ \App\Models\ReseauEau::couleurDe($historique->nouvel_etat) }}-lt">{{ \App\Models\ReseauEau::libelleEtatDe($historique->nouvel_etat) }}</span>
                                @endif
                                <div class="small text-secondary mt-1">par {{ $historique->user?->nom ?? 'le système' }}</div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endsection
