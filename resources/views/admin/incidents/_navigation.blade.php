{{--
    Navigation du module Exploitation (binôme 2), en haut de chaque page du module.
    Le menu latéral partagé n'est pas modifié : il pointe déjà vers admin.incidents.index et admin.interventions.index.
--}}
@php
    $onglets = [
        [['admin.incidents.index', 'admin.incidents.create', 'admin.incidents.show', 'admin.incidents.edit'],
            'admin.incidents.index', 'alert-triangle', 'Incidents'],
        [['admin.interventions.index', 'admin.interventions.show', 'admin.interventions.edit', 'admin.incidents.interventions.*'],
            'admin.interventions.index', 'tool', 'Interventions'],
        [['admin.incidents.priorites'], 'admin.incidents.priorites', 'list-numbers', 'Priorités'],
        [['admin.incidents.statistiques'], 'admin.incidents.statistiques', 'chart-bar', 'Statistiques'],
        [['admin.incidents.ia'], 'admin.incidents.ia', 'brain', 'Analyse IA'],
        [['admin.interventions.historique'], 'admin.interventions.historique', 'history', 'Historique'],
    ];
@endphp

<div class="card mb-3">
    <div class="card-body py-2">
        <ul class="nav nav-pills flex-wrap gap-1">
            @foreach ($onglets as [$motifs, $route, $icone, $libelle])
                <li class="nav-item">
                    <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs(...$motifs) ? 'active' : '' }}">
                        <i class="ti ti-{{ $icone }} me-1"></i> {{ $libelle }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
