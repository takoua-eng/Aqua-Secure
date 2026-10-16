@extends('layouts.admin')

@section('title', 'Capteurs')
@section('pretitle', 'Surveillance')
@section('subtitle')
    {{ $capteurs->firstItem() ?? 0 }}-{{ $capteurs->lastItem() ?? 0 }} sur {{ $capteurs->total() }} capteurs
@endsection

@section('actions')
    <a href="{{ route('admin.capteurs.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i> Nouveau capteur
    </a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- 4 indicateurs (composant Blade x-surveillance.kpi) --}}
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <x-surveillance.kpi libelle="Capteurs installés" :valeur="$stats['total']" icone="wifi" couleur="blue"
                                :lien="route('admin.capteurs.index')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-surveillance.kpi libelle="Capteurs actifs" :valeur="$stats['actifs']" icone="circle-check" couleur="green"
                                :lien="route('admin.capteurs.index', ['statut' => 'actif'])" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-surveillance.kpi libelle="En panne / hors service" :valeur="$stats['panne']" icone="plug-connected-x" couleur="yellow"
                                :lien="route('admin.capteurs.index', ['statut' => 'en_panne'])" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-surveillance.kpi libelle="Alertes sur 24 h" :valeur="$stats['alertes']" icone="alert-triangle" couleur="red"
                                :lien="route('admin.capteurs.index', ['alerte' => 1])" />
        </div>
    </div>

    <div class="card">
        {{-- Filtres --}}
            {{-- Analyse globale par l'IA : tous les capteurs, regroupés par zone --}}
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-sparkles text-purple me-1"></i> Analyse globale par l'IA</h3>
        </div>
        <div class="card-body">
            <x-surveillance.rapport-ia :url="route('admin.capteurs.analyse-globale')"
                                       bouton="Analyser tous les capteurs"
                                       attente="L'IA compare les capteurs de chaque zone… (jusqu'à une minute)">
                L'IA compare les capteurs d'une même zone : si plusieurs sont en alerte en même temps, le problème vient
                probablement du réseau ; si un seul l'est, c'est peut-être le capteur. Elle propose ensuite les priorités d'intervention.
            </x-surveillance.rapport-ia>
        </div>
    </div>
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Recherche</label>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Code ou modèle…">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Réseau</label>
                    <select name="reseau" class="form-select">
                        <option value="">Tous les réseaux</option>
                        @foreach ($reseaux as $id => $libelle)
                            <option value="{{ $id }}" @selected((string) request('reseau') === (string) $id)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mesure</label>
                    <select name="type" class="form-select">
                        <option value="">Toutes</option>
                        @foreach (\App\Models\Capteur::TYPES_MESURE as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected(request('type') === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="">Tous</option>
                        @foreach (\App\Models\Capteur::STATUTS as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected(request('statut') === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill"><i class="ti ti-filter me-1"></i> Filtrer</button>
                    <a href="{{ route('admin.capteurs.index') }}" class="btn btn-icon" title="Effacer les filtres"><i class="ti ti-x"></i></a>
                </div>
                @if (request()->boolean('alerte'))
                    <input type="hidden" name="alerte" value="1">
                    <div class="col-12">
                        <span class="badge bg-red-lt"><i class="ti ti-alert-triangle me-1"></i> Seulement les capteurs en alerte sur 24 h</span>
                    </div>
                @endif
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Mesure</th>
                        <th>Réseau</th>
                        <th>Seuils normaux</th>
                        <th>Dernier relevé</th>
                        <th>Statut</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($capteurs as $capteur)
                        <tr>
                            <td>
                                <a href="{{ route('admin.capteurs.show', $capteur) }}" class="fw-medium font-monospace text-nowrap">{{ $capteur->code }}</a>
                                <div class="small text-secondary">{{ $capteur->modele }}</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm me-2 bg-azure-lt"><i class="ti ti-{{ $capteur->iconeType() }}"></i></span>
                                    {{ $capteur->libelleTypeMesure() }}
                                </div>
                            </td>
                            <td>
                                <span class="font-monospace text-nowrap">{{ $capteur->reseau->code }}</span>
                                <div class="small text-secondary">{{ $capteur->reseau->zone->nom }}</div>
                            </td>
                            <td class="text-secondary text-nowrap">{{ $capteur->plageSeuils() }}</td>
                            <td>
                                @if ($capteur->dernierReleve)
                                    <span class="badge bg-{{ $capteur->estHorsSeuil($capteur->dernierReleve->valeur) ? 'red' : 'green' }}-lt">
                                        {{ $capteur->avecUnite($capteur->dernierReleve->valeur) }}
                                    </span>
                                    <div class="small text-secondary">{{ $capteur->dernierReleve->date_releve->locale('fr')->diffForHumans() }}</div>
                                @else
                                    <span class="text-secondary">Aucun</span>
                                @endif
                                @if ($capteur->estMuet())
                                    <div class="small text-warning" title="Capteur actif sans mesure depuis plus de {{ \App\Models\Capteur::HEURES_SILENCE }} h">
                                        <i class="ti ti-antenna-bars-off"></i> Capteur muet
                                    </div>
                                @endif
                                @if ($capteur->alertes_count)
                                    <div class="small text-danger" title="Relevés hors seuil sur les 7 derniers jours">
                                        <i class="ti ti-alert-triangle"></i> {{ $capteur->alertes_count }} alerte{{ $capteur->alertes_count > 1 ? 's' : '' }} / 7 j
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $capteur->couleurStatut() }}-lt">{{ $capteur->libelleStatut() }}</span>
                            </td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.capteurs.show', $capteur) }}" class="btn btn-sm">
                                        <i class="ti ti-chart-line me-1"></i> Voir
                                    </a>
                                    <a href="{{ route('admin.capteurs.edit', $capteur) }}" class="btn btn-sm">
                                        <i class="ti ti-edit me-1"></i> Modifier
                                    </a>
                                    <form method="POST" action="{{ route('admin.capteurs.destroy', $capteur) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-ghost-danger"
                                                data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                                data-confirm-type="danger"
                                                data-confirm-title="Supprimer ce capteur ?"
                                                data-confirm-message="Le capteur {{ $capteur->code }} et tous ses relevés seront définitivement supprimés."
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
                                    <div class="empty-icon"><i class="ti ti-wifi" style="font-size: 3rem"></i></div>
                                    <p class="empty-title">Aucun capteur trouvé</p>
                                    <p class="empty-subtitle text-secondary">Modifiez les filtres ou installez un nouveau capteur.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $capteurs->links() }}
    </div>
@endsection