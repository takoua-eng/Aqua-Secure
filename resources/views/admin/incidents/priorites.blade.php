@extends('layouts.admin')

@section('title', 'Priorités des incidents')
@section('pretitle', 'Exploitation · Aide à la décision')
@section('subtitle', 'Incidents non résolus classés par un moteur de règles métier explicable (score sur 100).')

@section('actions')
    <a href="{{ route('admin.incidents.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i> Nouvel incident
    </a>
@endsection

@section('content')
    @include('admin.incidents._navigation')

    {{-- Répartition par niveau (cliquer filtre la liste) --}}
    <div class="row row-deck row-cards mb-3">
        @foreach (\App\Services\PrioriteIncident::NIVEAUX as $n)
            <div class="col-6 col-lg-3">
                <a href="{{ route('admin.incidents.priorites', $niveau === $n['cle'] ? [] : ['niveau' => $n['cle']]) }}"
                   class="card card-sm card-link text-reset text-decoration-none {{ $niveau === $n['cle'] ? 'border-' . $n['couleur'] : '' }}">
                    <div class="card-body d-flex align-items-center">
                        <span class="avatar bg-{{ $n['couleur'] }} text-white me-3"><i class="ti ti-flag"></i></span>
                        <div>
                            <div class="h2 mb-0">{{ $parNiveau[$n['cle']] }}</div>
                            <div class="text-secondary">{{ $n['libelle'] }} <span class="small">({{ $n['seuil'] }}+ pts)</span></div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row row-cards">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Classement des incidents à traiter
                            @if ($niveau)
                                <span class="badge bg-secondary-lt ms-1">niveau : {{ collect(\App\Services\PrioriteIncident::NIVEAUX)->firstWhere('cle', $niveau)['libelle'] }}</span>
                                <a href="{{ route('admin.incidents.priorites') }}" class="ms-1 small">tout afficher</a>
                            @endif
                        </h3>
                        <div class="card-subtitle">À score égal, le plus ancien passe en premier.</div>
                    </div>
                </div>

                @forelse ($classement as $rang => $ligne)
                    @php
                        $incident = $ligne['incident'];
                        $priorite = $ligne['priorite'];
                    @endphp
                    <div class="card-body border-bottom">
                        <div class="row g-3">
                            <div class="col-auto text-center" style="min-width: 5.5rem">
                                <div class="text-secondary small">n° {{ $rang + 1 }}</div>
                                <div class="display-6 fw-bold text-{{ $priorite['niveau']['couleur'] }}" style="font-variant-numeric: tabular-nums">{{ $priorite['score'] }}</div>
                                <span class="badge bg-{{ $priorite['niveau']['couleur'] }} text-{{ $priorite['niveau']['couleur'] }}-fg">{{ $priorite['niveau']['libelle'] }}</span>
                            </div>
                            <div class="col">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <a href="{{ route('admin.incidents.show', $incident) }}" class="fw-bold">
                                        #{{ $incident->id }} · {{ $incident->libelleType() }}
                                    </a>
                                    <span class="badge bg-{{ $incident->couleurGravite() }}-lt">{{ $incident->libelleGravite() }}</span>
                                    <span class="badge bg-{{ $incident->couleurStatut() }}-lt">{{ $incident->libelleStatut() }}</span>
                                    <span class="text-secondary small">
                                        <span class="font-monospace">{{ $incident->reseau->code }}</span> · {{ $incident->reseau->zone->nom }}
                                        · signalé le {{ $incident->date_signalement->format('d/m/Y') }}
                                    </span>
                                </div>

                                {{-- Raison de la priorité : points de chaque règle --}}
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    @foreach ($priorite['facteurs'] as $facteur)
                                        <span class="badge bg-secondary-lt" title="{{ $facteur['explication'] }}">
                                            {{ $facteur['libelle'] }} <strong class="ms-1">{{ $facteur['points'] }}/{{ $facteur['max'] }}</strong>
                                        </span>
                                    @endforeach
                                </div>
                                <div class="small text-secondary mb-2">
                                    {{ collect($priorite['facteurs'])->where('points', '>', 0)->pluck('explication')->implode(' · ') }}
                                </div>

                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <div class="flex-fill"><i class="ti ti-bulb text-yellow me-1"></i><strong>Action :</strong> {{ $priorite['action'] }}</div>
                                    @if ($incident->statutActuel() === 'non_traite')
                                        <a href="{{ route('admin.incidents.interventions.create', $incident) }}" class="btn btn-sm btn-primary">
                                            <i class="ti ti-calendar-plus me-1"></i> Planifier
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.incidents.show', $incident) }}" class="btn btn-sm">
                                        <i class="ti ti-eye me-1"></i> Fiche
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="card-body">
                        <div class="empty">
                            <div class="empty-icon"><i class="ti ti-circle-check" style="font-size: 3rem"></i></div>
                            <p class="empty-title">{{ $niveau ? 'Aucun incident à ce niveau de priorité' : 'Aucun incident à traiter' }}</p>
                            <p class="empty-subtitle text-secondary">
                                {{ $niveau ? 'Choisissez un autre niveau.' : 'Tous les incidents enregistrés sont résolus, ou aucun incident n\'a encore été saisi.' }}
                            </p>
                            <div class="empty-action">
                                <a href="{{ route('admin.incidents.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Ajouter un incident</a>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="col-xl-4">
            <div class="row row-cards">
                {{-- Règle I7 --}}
                <div class="col-12">
                    <div class="card {{ $critiques->isNotEmpty() ? 'border-danger' : '' }}">
                        <div class="card-header">
                            <h3 class="card-title"><i class="ti ti-urgent me-1 text-red"></i> Critiques non pris en charge</h3>
                            <div class="card-actions"><span class="badge {{ $critiques->isNotEmpty() ? 'bg-red text-red-fg' : 'bg-green-lt' }}">{{ $critiques->count() }}</span></div>
                        </div>
                        @if ($critiques->isEmpty())
                            <div class="card-body text-secondary"><i class="ti ti-circle-check text-green me-1"></i> Aucun incident critique en attente d'intervention.</div>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach ($critiques as $critique)
                                    <a href="{{ route('admin.incidents.show', $critique) }}" class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between">
                                            <span><strong>#{{ $critique->id }}</strong> · {{ $critique->libelleType() }}</span>
                                            <span class="text-red small">depuis {{ $critique->joursDepuisSignalement() }} j</span>
                                        </div>
                                        <div class="small text-secondary"><span class="font-monospace">{{ $critique->reseau->code }}</span> · {{ $critique->reseau->zone->nom }}</div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Réseaux à incidents récurrents --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title"><i class="ti ti-repeat me-1"></i> Réseaux à incidents récurrents</h3>
                                <div class="card-subtitle">Au moins {{ \App\Services\PrioriteIncident::SEUIL_RECURRENCE }} incidents sur 12 mois : candidats à une rénovation.</div>
                            </div>
                        </div>
                        @if ($recurrents->isEmpty())
                            <div class="card-body text-secondary">Aucun réseau n'atteint ce seuil sur les 12 derniers mois.</div>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach ($recurrents as $ligne)
                                    <a href="{{ route('admin.incidents.index', ['reseau' => $ligne->reseau_id]) }}" class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between">
                                            <span class="font-monospace">{{ $ligne->reseau?->code }}</span>
                                            <span class="badge bg-orange-lt">{{ $ligne->nombre }} incidents</span>
                                        </div>
                                        <div class="small text-secondary">
                                            {{ $ligne->reseau?->zone?->nom }} · état : {{ $ligne->reseau?->libelleEtat() }}
                                            · dernier le {{ \Illuminate\Support\Carbon::parse($ligne->dernier)->format('d/m/Y') }}
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Barème : rend le score vérifiable par tous --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><i class="ti ti-scale me-1"></i> Barème du score</h3></div>
                        <div class="card-body small">
                            <table class="table table-sm mb-3">
                                <thead><tr><th>Règle</th><th>Points</th><th class="text-end">Max</th></tr></thead>
                                <tbody>
                                    <tr><td>Gravité</td><td>faible 10 · moyenne 25 · critique 40</td><td class="text-end">40</td></tr>
                                    <tr><td>Type</td><td>fuite 10 · coupure 15 · contamination 25</td><td class="text-end">25</td></tr>
                                    <tr><td>Ancienneté</td><td>1 pt / {{ \App\Services\PrioriteIncident::JOURS_PAR_POINT }} jours non résolus</td><td class="text-end">15</td></tr>
                                    <tr><td>Prise en charge</td><td>aucune 15 · planifiée en retard 12 · planifiée 8 · en cours 3</td><td class="text-end">15</td></tr>
                                    <tr><td>État du réseau</td><td>hors service 5 · dégradé 3 · bon 0</td><td class="text-end">5</td></tr>
                                </tbody>
                            </table>
                            <p class="text-secondary mb-0">
                                <i class="ti ti-info-circle me-1"></i>
                                Poids fixés par expertise métier, pas appris sur des données : ce n'est pas un modèle d'apprentissage automatique.
                                L'état du réseau provient du module Infrastructure (lecture seule).
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
