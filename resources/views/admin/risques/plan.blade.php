@extends('layouts.admin')

@section('title', 'Plan de renouvellement')
@section('pretitle', 'Infrastructure · Analyse des risques')

@section('actions')
    <a href="{{ route('admin.risques.index') }}" class="btn">
        <i class="ti ti-arrow-left me-1"></i> Analyse des risques
    </a>
@endsection

@section('content')
    {{-- Budget : contrôle de saisie navigateur (validation.js) + serveur (RisqueController@plan) --}}
    <form method="GET" action="{{ route('admin.risques.plan') }}" class="card mb-3" data-validate>
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-5">
                    <x-input name="budget" type="number" label="Budget disponible (TND)" icon="coin" required
                             :value="request('budget')"
                             min="10000" max="100000000" step="1000" placeholder="Ex : 500000"
                             hint="Entre 10 000 et 100 000 000 TND."
                             data-msg-required="Indiquez le budget disponible."
                             data-msg-min="Le budget doit être d'au moins 10 000 TND."
                             data-msg-max="Le budget ne peut pas dépasser 100 000 000 TND."
                             data-msg-type="Saisissez un montant en dinars."
                             valid-message="Budget valide." />
                </div>
                <div class="col-md-auto mb-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-calculator me-1"></i> Calculer le meilleur plan
                    </button>
                </div>
            </div>
            <p class="text-secondary small mb-0">
                L'algorithme (problème du sac à dos, programmation dynamique) choisit la combinaison d'éléments à remplacer
                qui supprime le plus de points de risque sans dépasser le budget.
                Coûts estimés : canalisation {{ number_format(\App\Services\PlanRenovation::COUT_TYPE['canalisation'], 0, ',', ' ') }} TND,
                réservoir {{ number_format(\App\Services\PlanRenovation::COUT_TYPE['reservoir'], 0, ',', ' ') }} TND,
                station {{ number_format(\App\Services\PlanRenovation::COUT_TYPE['station'], 0, ',', ' ') }} TND
                (+30 % pour l'amiante-ciment, +10 % pour la fonte).
            </p>
        </div>
    </form>

    @if ($plan)
        @php
            $baisse = $plan['risqueAvant'] ? round($plan['gain'] / $plan['risqueAvant'] * 100) : 0;
            $mieux = $plan['gain'] - $plan['naif']['gain'];
        @endphp

        <div class="row row-cards mb-3">
            @foreach ([
                ['Éléments à rénover', $plan['choisis']->count() . ' / ' . $plan['nbCandidats'], 'tool', 'blue'],
                ['Coût du plan', number_format($plan['cout'], 0, ',', ' ') . ' TND', 'coin', 'azure'],
                ['Budget restant', number_format($plan['budget'] - $plan['cout'], 0, ',', ' ') . ' TND', 'wallet', 'secondary'],
                ['Risque supprimé', $plan['gain'] . ' pts (−' . $baisse . ' %)', 'trending-down', 'green'],
            ] as [$libelle, $valeur, $icone, $couleur])
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body d-flex align-items-center">
                            <span class="avatar bg-{{ $couleur }}-lt me-3"><i class="ti ti-{{ $icone }}"></i></span>
                            <div>
                                <div class="fw-semibold">{{ $valeur }}</div>
                                <div class="text-secondary small">{{ $libelle }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Comparaison avec la méthode "naïve" --}}
        <div class="alert alert-{{ $mieux > 0 ? 'success' : 'info' }}">
            <i class="ti ti-bulb me-1"></i>
            @if ($mieux > 0)
                Le plan optimisé supprime <strong>{{ $mieux }} points de risque de plus</strong> que la méthode simple
                « rénover les scores les plus élevés d'abord » ({{ $plan['naif']['gain'] }} pts pour {{ $plan['naif']['nombre'] }} éléments).
            @else
                Avec ce budget, la méthode simple « scores les plus élevés d'abord » donne le même résultat
                ({{ $plan['naif']['gain'] }} pts) : le plan optimisé le confirme.
            @endif
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Éléments à rénover</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Zone</th>
                            <th>Matériau · âge</th>
                            <th>État</th>
                            <th class="text-end">Score actuel</th>
                            <th class="text-end">Risque supprimé</th>
                            <th class="text-end">Coût estimé</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($plan['choisis'] as ['reseau' => $reseau, 'risque' => $risque, 'cout' => $cout, 'gain' => $gain])
                            <tr>
                                <td>
                                    <a href="{{ route('admin.reseaux.edit', $reseau) }}" class="font-monospace fw-medium">{{ $reseau->code }}</a>
                                    <div class="small text-secondary">{{ $reseau->libelleType() }}</div>
                                </td>
                                <td>{{ $reseau->zone->nom }} <div class="small text-secondary">{{ $reseau->zone->commune }}</div></td>
                                <td class="text-secondary">{{ $reseau->libelleMateriau() }} · {{ $reseau->age() }} ans</td>
                                <td><span class="badge bg-{{ $reseau->couleurEtat() }}-lt">{{ $reseau->libelleEtat() }}</span></td>
                                <td class="text-end"><span class="badge bg-{{ $risque['couleur'] }}-lt">{{ $risque['score'] }}</span></td>
                                <td class="text-end text-success fw-medium">−{{ $gain }} pts</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums">{{ number_format($cout, 0, ',', ' ') }} TND</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary py-4">
                                Budget insuffisant pour rénover un seul élément (le moins cher coûte {{ number_format(\App\Services\PlanRenovation::COUT_TYPE['canalisation'], 0, ',', ' ') }} TND).
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($plan['choisis']->isNotEmpty())
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="5">Total</td>
                                <td class="text-end text-success">−{{ $plan['gain'] }} pts</td>
                                <td class="text-end">{{ number_format($plan['cout'], 0, ',', ' ') }} TND</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif
@endsection
