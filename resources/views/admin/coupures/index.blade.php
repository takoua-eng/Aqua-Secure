@extends('layouts.admin')

@section('title', 'Coupures programmées')
@section('pretitle', 'Infrastructure')
@section('subtitle')
    {{ $coupures->firstItem() ?? 0 }}-{{ $coupures->lastItem() ?? 0 }} sur {{ $coupures->total() }} coupures
@endsection

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex flex-wrap gap-2">
            <select name="statut" class="form-select" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                @foreach (\App\Models\CoupureProgrammee::STATUTS as $cle => [$libelle])
                    <option value="{{ $cle }}" @selected(request('statut') === $cle)>{{ $libelle }}</option>
                @endforeach
            </select>
            <select name="zone" class="form-select" onchange="this.form.submit()">
                <option value="">Toutes les zones</option>
                @foreach ($zones as $id => $libelle)
                    <option value="{{ $id }}" @selected((string) request('zone') === (string) $id)>{{ $libelle }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.coupures.create') }}" class="btn btn-primary">
            <i class="ti ti-calendar-plus me-1"></i> Programmer une coupure
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
                        <th>Période</th>
                        <th>Réseau · zone</th>
                        <th>Motif</th>
                        <th class="text-end">Habitants touchés</th>
                        <th>Commentaires</th>
                        <th>Statut</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($coupures as $coupure)
                        <tr>
                            <td>
                                <div>{{ $coupure->debut->format('d/m/Y H:i') }}</div>
                                <div class="small text-secondary">→ {{ $coupure->fin->format('d/m/Y H:i') }} · {{ $coupure->dureeHeures() }} h</div>
                            </td>
                            <td>
                                <span class="font-monospace fw-medium">{{ $coupure->reseau->code }}</span>
                                <div class="small text-secondary">{{ $coupure->reseau->zone->nom }}, {{ $coupure->reseau->zone->commune }}</div>
                            </td>
                            <td class="text-secondary">{{ $coupure->motif }}</td>
                            <td class="text-end" style="font-variant-numeric: tabular-nums" title="Estimation : toute la zone pour un réservoir ou une station, une part de la zone pour une canalisation">
                                ≈ {{ number_format($coupure->habitantsTouches(), 0, ',', ' ') }}
                            </td>
                            <td>
                                <span class="badge {{ $coupure->commentaires_count ? 'bg-azure-lt' : 'bg-secondary-lt' }}">
                                    <i class="ti ti-messages me-1"></i>{{ $coupure->commentaires_count }}
                                </span>
                            </td>
                            <td><span class="badge bg-{{ $coupure->couleurStatut() }}-lt">{{ $coupure->libelleStatut() }}</span></td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    {{-- Ouvre / ferme le panneau de détails juste en dessous (commentaires compris) --}}
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="collapse" data-bs-target="#details-{{ $coupure->id }}"
                                            aria-expanded="false" aria-controls="details-{{ $coupure->id }}">
                                        <i class="ti ti-eye me-1"></i> Voir détails
                                    </button>
                                    @if ($coupure->estModifiable())
                                        <a href="{{ route('admin.coupures.edit', $coupure) }}" class="btn btn-sm">
                                            <i class="ti ti-edit me-1"></i> Modifier
                                        </a>
                                    @endif
                                    @if (in_array($coupure->statut(), ['a_venir', 'en_cours']))
                                        <form method="POST" action="{{ route('admin.coupures.annuler', $coupure) }}">
                                            @csrf
                                            <button type="button" class="btn btn-sm btn-ghost-warning"
                                                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                    data-confirm-type="danger"
                                                    data-confirm-title="Annuler cette coupure ?"
                                                    data-confirm-message="Les citoyens de {{ $coupure->reseau->zone->nom }} seront prévenus que la coupure n'aura pas lieu."
                                                    data-confirm-button="Oui, annuler">
                                                <i class="ti ti-calendar-off me-1"></i> Annuler
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.coupures.destroy', $coupure) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-ghost-danger"
                                                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                    data-confirm-type="danger"
                                                    data-confirm-title="Supprimer cette coupure ?"
                                                    data-confirm-message="Elle disparaîtra de l'historique."
                                                    data-confirm-button="Oui, supprimer">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Détails de la coupure et commentaires des citoyens (affichés sous la ligne) --}}
                        <tr class="collapse bg-light" id="details-{{ $coupure->id }}">
                            <td colspan="7" class="p-0">
                                <div class="p-3">
                                    <div class="row g-3">
                                        <div class="col-lg-4">
                                            <h4 class="mb-2"><i class="ti ti-info-circle me-1"></i> Détails</h4>
                                            <dl class="row small mb-0">
                                                <dt class="col-5 fw-normal text-secondary">Réseau</dt>
                                                <dd class="col-7">{{ $coupure->reseau->code }} · {{ $coupure->reseau->libelleType() }}</dd>
                                                <dt class="col-5 fw-normal text-secondary">Zone</dt>
                                                <dd class="col-7">{{ $coupure->reseau->zone->nom }}, {{ $coupure->reseau->zone->commune }}</dd>
                                                <dt class="col-5 fw-normal text-secondary">Durée</dt>
                                                <dd class="col-7">{{ $coupure->dureeHeures() }} h</dd>
                                                <dt class="col-5 fw-normal text-secondary">Planifiée par</dt>
                                                <dd class="col-7">{{ $coupure->auteur?->nom ?? '—' }}, le {{ $coupure->created_at->format('d/m/Y') }}</dd>
                                                @if ($coupure->annulee_le)
                                                    <dt class="col-5 fw-normal text-secondary">Annulée le</dt>
                                                    <dd class="col-7">{{ $coupure->annulee_le->format('d/m/Y H:i') }}</dd>
                                                @endif
                                                <dt class="col-5 fw-normal text-secondary">Informations</dt>
                                                <dd class="col-7 mb-0">{{ $coupure->description ?: '—' }}</dd>
                                            </dl>
                                        </div>

                                        <div class="col-lg-8">
                                            <h4 class="mb-2">
                                                <i class="ti ti-messages me-1"></i> Commentaires des citoyens
                                                <span class="text-secondary fw-normal">({{ $coupure->commentaires->count() }})</span>
                                            </h4>
                                            @forelse ($coupure->commentaires as $commentaire)
                                                <div class="d-flex gap-2 bg-white border rounded p-2 mb-2">
                                                    <span class="avatar avatar-sm bg-green-lt flex-shrink-0">{{ $commentaire->auteur->initiales() }}</span>
                                                    <div class="flex-fill">
                                                        <div class="small">
                                                            <strong>{{ $commentaire->auteur->nom }}</strong>
                                                            <span class="text-secondary">· {{ $commentaire->auteur->email }} · {{ $commentaire->created_at->format('d/m/Y H:i') }}</span>
                                                        </div>
                                                        <div style="white-space: pre-line">{{ $commentaire->contenu }}</div>
                                                        <span class="badge bg-red-lt mt-1"><i class="ti ti-heart me-1"></i>{{ $commentaire->likes_count }} j'aime</span>
                                                    </div>
                                                    <form method="POST" action="{{ route('admin.commentaires.destroy', $commentaire) }}" class="align-self-start">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-ghost-danger" title="Supprimer ce commentaire"
                                                                data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                                data-confirm-type="danger"
                                                                data-confirm-title="Supprimer ce commentaire ?"
                                                                data-confirm-message="Le commentaire de {{ $commentaire->auteur->nom }} et ses {{ $commentaire->likes_count }} « j'aime » seront définitivement supprimés."
                                                                data-confirm-button="Oui, supprimer">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            @empty
                                                <p class="small text-secondary mb-0">Aucun commentaire sur cette coupure.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-calendar-off" style="font-size: 3rem"></i></div>
                                    <p class="empty-title">Aucune coupure</p>
                                    <p class="empty-subtitle text-secondary">Programmez une coupure avant des travaux pour prévenir les habitants.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $coupures->links() }}
    </div>
@endsection

@push('scripts')
    <script>
        // Après la suppression d'un commentaire, on revient avec #details-12 : on rouvre ce panneau
        (function () {
            const panneau = location.hash.startsWith('#details-') && document.querySelector(location.hash);
            if (panneau) {
                if (window.bootstrap) {
                    bootstrap.Collapse.getOrCreateInstance(panneau, { toggle: false }).show();
                } else {
                    panneau.classList.add('show');
                }
                panneau.previousElementSibling?.scrollIntoView({ block: 'center' });
            }
        })();
    </script>
@endpush
