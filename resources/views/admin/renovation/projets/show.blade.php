@extends('layouts.admin')

@section('title', $projet->titre)
@section('pretitle', 'Rénovation · Projet')

@section('actions')
    <div class="btn-list">
        <a href="{{ route('admin.projets.panneau', $projet) }}" class="btn" target="_blank" title="Panneau A4 avec QR code vers la page publique">
            <i class="ti ti-qrcode me-1"></i> Panneau de chantier
        </a>
        @can('update', $projet)
            <a href="{{ route('admin.projets.edit', $projet) }}" class="btn"><i class="ti ti-edit me-1"></i> Modifier</a>
        @endcan
        @if ($projet->statut !== 'termine' || auth()->user()->isAdmin())
            <a href="{{ route('admin.financements.create', ['projet' => $projet->id]) }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Ajouter un financement
            </a>
        @endif
    </div>
@endsection

@section('content')
    @include('admin.renovation._nav')

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @php
        $pct = $cout['montant'] ? round(100 * $finance / $cout['montant'], 1) : null;
        $reste = $cout['montant'] ? max(0, $cout['montant'] - $finance) : null;
        $niveaux = ['danger' => ['red', 'alert-octagon'], 'warning' => ['orange', 'alert-triangle'], 'info' => ['azure', 'info-circle']];
    @endphp

    @foreach ($alertes as $a)
        @php [$couleur, $icone] = $niveaux[$a['niveau']]; @endphp
        <div class="alert alert-{{ $a['niveau'] === 'danger' ? 'danger' : ($a['niveau'] === 'warning' ? 'warning' : 'info') }} d-flex gap-2">
            <i class="ti ti-{{ $icone }} mt-1"></i>
            <div><strong>{{ $a['titre'] }}</strong> <span class="badge bg-secondary-lt font-monospace ms-1">{{ $a['code'] }}</span>
                <div>{{ $a['message'] }}</div></div>
        </div>
    @endforeach

    <div class="row row-cards">
        {{-- Synthèse financière --}}
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge bg-{{ $projet->couleurStatut() }}-lt fs-5"><i class="ti ti-{{ $projet->iconeStatut() }} me-1"></i>{{ $projet->libelleStatut() }}</span>
                        @if ($projet->publie)
                            <a href="{{ route('renovation.show', $projet) }}" class="badge bg-azure-lt fs-5" target="_blank"><i class="ti ti-world me-1"></i>Publié</a>
                        @else
                            <span class="badge bg-secondary-lt fs-5"><i class="ti ti-lock me-1"></i>Interne</span>
                        @endif
                        <span class="text-secondary small ms-auto">Créé le {{ $projet->created_at->format('d/m/Y') }} · modifié le {{ $projet->updated_at->format('d/m/Y') }}</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="text-secondary small">Déjà financé <x-renovation.origine type="enregistre" /></div>
                            <div class="h2 mb-0"><x-renovation.montant :valeur="$finance" /></div>
                        </div>
                        <div class="col-sm-4">
                            <div class="text-secondary small">Coût total <x-renovation.origine :type="in_array($cout['source'], ['declare', 'predit'], true) ? $cout['source'] : 'estime'" /></div>
                            <div class="h2 mb-0">
                                @if ($cout['montant'] !== null) <x-renovation.montant :valeur="$cout['montant']" /> @else <span class="text-secondary">Inconnu</span> @endif
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="text-secondary small">Reste à financer <x-renovation.origine type="calcule" /></div>
                            <div class="h2 mb-0">@if ($reste !== null) <x-renovation.montant :valeur="$reste" /> @else — @endif</div>
                        </div>
                    </div>

                    @if ($pct !== null)
                        <div class="progress progress-lg mt-3" style="height: 1.25rem">
                            <div class="progress-bar bg-{{ $cout['source'] === 'declare' ? 'teal' : 'yellow' }}" style="width: {{ min(100, $pct) }}%">
                                {{ number_format($pct, 1, ',', ' ') }} %
                            </div>
                        </div>
                        <div class="small text-secondary mt-1">
                            {{ $cout['detail'] }}
                            @if ($cout['source'] !== 'declare')
                                <strong>Ce taux est indicatif</strong> : saisissez le coût du devis pour un taux fiable.
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Financements --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Financements ({{ $projet->financements->count() }})</h3>
                    <div class="card-actions"><x-renovation.origine type="enregistre" /></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>Date</th><th>Source</th><th>Origine</th><th class="text-end">Montant</th><th class="w-1"></th></tr></thead>
                        <tbody>
                            @forelse ($projet->financements as $f)
                                <tr>
                                    <td class="text-secondary">{{ $f->created_at->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge bg-{{ \App\Models\Financement::couleurSourceDe($f->source) }}-lt">
                                            <i class="ti ti-{{ \App\Models\Financement::iconeSourceDe($f->source) }} me-1"></i>{{ $f->libelleSource() }}
                                        </span>
                                    </td>
                                    <td class="small">
                                        @if ($f->simulation)
                                            <a href="{{ route('admin.simulations.show', $f->simulation_id) }}"><i class="ti ti-adjustments-alt me-1"></i>Scénario « {{ $f->simulation->nom }} »</a>
                                        @else
                                            <span class="text-secondary">Saisie manuelle</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-medium"><x-renovation.montant :valeur="$f->montant" /></td>
                                    <td>
                                        <div class="btn-list flex-nowrap">
                                            <a href="{{ route('admin.financements.show', $f) }}" class="btn btn-sm btn-icon" title="Voir"><i class="ti ti-eye"></i></a>
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
                                <tr><td colspan="5" class="text-center text-secondary py-4">Aucun financement enregistré pour ce projet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($parSource->count() > 1)
                    <div class="card-footer">
                        <div class="progress progress-separated mb-2" style="height: .75rem">
                            @foreach ($parSource as $s)
                                <div class="progress-bar bg-{{ $s['couleur'] }}" style="width: {{ $finance > 0 ? 100 * $s['montant'] / $finance : 0 }}%"
                                     title="{{ $s['libelle'] }}"></div>
                            @endforeach
                        </div>
                        <div class="d-flex flex-wrap gap-3 small">
                            @foreach ($parSource as $s)
                                <span><span class="badge bg-{{ $s['couleur'] }} me-1"></span>{{ $s['libelle'] }} : <x-renovation.montant :valeur="$s['montant']" :decimales="0" /></span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            @if ($projet->description)
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title">Description</h3></div>
                    <div class="card-body">{!! nl2br(e($projet->description)) !!}</div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            {{-- Réseau --}}
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-share me-1"></i> Réseau concerné</h3></div>
                <div class="card-body">
                    @if ($projet->reseau)
                        <div class="d-flex align-items-center mb-2">
                            <span class="avatar bg-azure-lt me-2"><i class="ti ti-{{ $projet->reseau->iconeType() }}"></i></span>
                            <div>
                                <div class="font-monospace fw-bold">{{ $projet->reseau->code }}</div>
                                <div class="small text-secondary">{{ $projet->reseau->libelleType() }} · {{ $projet->reseau->libelleMateriau() }}</div>
                            </div>
                            <span class="badge bg-{{ $projet->reseau->couleurEtat() }}-lt ms-auto">{{ $projet->reseau->libelleEtat() }}</span>
                        </div>
                        <div class="small text-secondary">
                            Zone : {{ $projet->reseau->zone?->libelle() }}<br>
                            Mis en service en {{ $projet->reseau->date_mise_en_service?->format('Y') }} ({{ $projet->reseau->age() }} ans)<br>
                            Population de la zone : {{ number_format((int) $projet->reseau->zone?->population, 0, ',', ' ') }} habitants
                        </div>
                        @if ($priorite['risque'])
                            <div class="mt-2">
                                Score de risque du réseau :
                                <span class="badge bg-{{ $priorite['risque']['couleur'] }} text-white">{{ $priorite['risque']['score'] }}/100</span>
                                <span class="small">{{ $priorite['risque']['libelle'] }}</span>
                            </div>
                        @endif
                    @else
                        <span class="text-secondary">Réseau introuvable.</span>
                    @endif
                </div>
            </div>

            {{-- Score de priorité expliqué --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-target-arrow me-1"></i> Score de priorité</h3>
                    <div class="card-actions"><x-renovation.origine type="calcule" /></div>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="display-6 fw-bold me-2 text-{{ $priorite['couleur'] }}">{{ $priorite['score'] }}</div>
                        <div class="text-secondary">/ 100<br><span class="badge bg-{{ $priorite['couleur'] }}-lt">{{ $priorite['libelle'] }}</span></div>
                    </div>
                    @foreach ($priorite['facteurs'] as $f)
                        <div class="mb-2">
                            <div class="d-flex small"><span>{{ $f['libelle'] }}</span><span class="ms-auto fw-medium">{{ $f['points'] }}/{{ $f['max'] }}</span></div>
                            <div class="progress progress-xs"><div class="progress-bar bg-primary" style="width: {{ $f['max'] ? 100 * $f['points'] / $f['max'] : 0 }}%"></div></div>
                        </div>
                    @endforeach
                    <div class="small text-secondary mt-3">
                        @foreach ($priorite['limites'] as $limite)
                            <div><i class="ti ti-info-circle me-1"></i>{{ $limite }}</div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Modèle IA entraîné : source de financement à solliciter --}}
            @if ($recommandation)
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-brain me-1"></i> Source à solliciter</h3>
                        <div class="card-actions"><x-renovation.origine type="predit" /></div>
                    </div>
                    <div class="card-body">
                        @foreach ($recommandation['probabilites'] as $source => $proba)
                            <div class="mb-2">
                                <div class="d-flex small">
                                    <span class="{{ $loop->first ? 'fw-bold' : '' }}">{{ \App\Models\Financement::libelleSourceDe($source) }}</span>
                                    <span class="ms-auto">{{ number_format($proba, 0) }} %</span>
                                </div>
                                <div class="progress progress-xs"><div class="progress-bar bg-{{ \App\Models\Financement::couleurSourceDe($source) }}" style="width: {{ $proba }}%"></div></div>
                            </div>
                        @endforeach
                        <div class="small text-secondary mt-2"><i class="ti ti-info-circle me-1"></i>{{ $recommandation['explication'] }}</div>
                        <div class="small text-secondary">Modèle bayésien entraîné sur {{ $recommandation['n'] }} financements ·
                            <a href="{{ route('admin.renovation.modeles') }}">voir ses performances</a></div>
                    </div>
                </div>
            @endif

            {{-- Voix des citoyens : soutiens et questions (publiées seulement avec une réponse) --}}
            @if ($participation)
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-speakerphone me-1"></i> Voix des citoyens</h3>
                        <div class="card-actions">
                            @if ($projet->publie)
                                <a href="{{ route('renovation.show', $projet) }}" target="_blank" class="small"><i class="ti ti-external-link"></i> Page publique</a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="avatar bg-pink-lt me-2"><i class="ti ti-heart"></i></span>
                            <div><strong>{{ $soutiens }}</strong> citoyen(s) ont indiqué « Ce projet me concerne »
                                <div class="small text-secondary">Signal informatif : il n'entre ni dans le score ni dans les décisions de financement.</div></div>
                        </div>
                        @unless ($projet->publie)
                            <div class="small text-secondary"><i class="ti ti-lock me-1"></i>Projet non publié : les citoyens ne peuvent pas encore le voir.</div>
                        @endunless
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($questions as $q)
                            <div class="list-group-item">
                                <div class="d-flex small text-secondary mb-1">
                                    <span><i class="ti ti-message-question me-1"></i>Citoyen · {{ $q->created_at->format('d/m/Y') }}</span>
                                    <span class="ms-auto badge bg-{{ $q->reponse ? ($q->publiee ? 'green' : 'secondary') : 'orange' }}-lt">
                                        {{ $q->reponse ? ($q->publiee ? 'Publiée' : 'Répondue, non publiée') : 'En attente' }}
                                    </span>
                                </div>
                                <div class="mb-2">{{ $q->question }}</div>
                                @if ($q->reponse)
                                    <div class="small border-start border-3 border-primary ps-2 mb-2">{{ $q->reponse }}
                                        <div class="text-secondary">— {{ $q->repondant?->nom }}, {{ $q->repondu_le?->format('d/m/Y') }}</div></div>
                                @endif
                                <form method="POST" action="{{ route('admin.projets.questions.repondre', [$projet, $q]) }}">
                                    @csrf
                                    <textarea name="reponse" rows="2" class="form-control form-control-sm mb-1" required minlength="5" maxlength="1500"
                                              placeholder="Réponse publique…">{{ $q->reponse }}</textarea>
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="form-check mb-0 small">
                                            <input type="hidden" name="publiee" value="0">
                                            <input type="checkbox" class="form-check-input" name="publiee" value="1" @checked($q->publiee || ! $q->reponse)>
                                            <span class="form-check-label">Publier</span>
                                        </label>
                                        <button class="btn btn-sm btn-primary ms-auto"><i class="ti ti-send me-1"></i>{{ $q->reponse ? 'Modifier' : 'Répondre' }}</button>
                                    </div>
                                </form>
                                @can('update', $projet)
                                    <form method="POST" action="{{ route('admin.projets.questions.destroy', [$projet, $q]) }}" class="mt-1 text-end">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-ghost-danger"
                                                data-bs-toggle="modal" data-bs-target="#modal-confirm" data-confirm-type="danger"
                                                data-confirm-title="Supprimer cette question ?" data-confirm-message="La question (et sa réponse) sera définitivement supprimée."
                                                data-confirm-button="Oui, supprimer"><i class="ti ti-trash me-1"></i>Supprimer</button>
                                    </form>
                                @endcan
                            </div>
                        @empty
                            <div class="list-group-item text-secondary small">Aucune question de citoyen pour ce projet.</div>
                        @endforelse
                    </div>
                </div>
            @endif

            @can('delete', $projet)
                <form method="POST" action="{{ route('admin.projets.destroy', $projet) }}" class="card card-body">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-outline-danger w-100"
                            data-bs-toggle="modal" data-bs-target="#modal-confirm"
                            data-confirm-type="danger"
                            data-confirm-title="Supprimer ce projet ?"
                            data-confirm-message="Le projet « {{ $projet->titre }} » sera définitivement supprimé."
                            data-confirm-button="Oui, supprimer">
                        <i class="ti ti-trash me-1"></i> Supprimer le projet
                    </button>
                </form>
            @else
                <div class="small text-secondary"><i class="ti ti-lock me-1"></i>Un projet qui a des financements ne peut pas être supprimé.</div>
            @endcan
        </div>
    </div>
@endsection
