{{-- Binôme 3 — navigation interne du module (la barre latérale partagée n'a qu'une entrée « Projets de rénovation ») --}}
@php
    $onglets = [
        ['admin.renovation.tableau', 'admin.renovation.tableau', 'chart-donut-3', 'Tableau financier'],
        ['admin.projets.index', 'admin.projets.*', 'building', 'Projets'],
        ['admin.financements.index', 'admin.financements.*', 'coin', 'Financements'],
        ['admin.whatif.index', 'admin.whatif.index', 'adjustments-alt', 'What If? Engine'],
        ['admin.whatif.pluriannuel', 'admin.whatif.pluriannuel', 'calendar-stats', 'Plan pluriannuel'],
        ['admin.simulations.index', 'admin.simulations.*', 'history', 'Simulations'],
        ['admin.renovation.territoires', 'admin.renovation.territoires', 'map-2', 'Territoires'],
        ['admin.renovation.modeles', 'admin.renovation.modeles*', 'brain', 'Modèles IA'],
    ];
    $enAttente = auth()->user()->isAdmin() ? \App\Models\SimulationFinancement::where('statut', 'soumise')->count() : 0;
    // Questions de citoyens sans réponse (si la participation citoyenne est installée)
    $questionsEnAttente = \App\Services\Renovation\ObservatoireCitoyen::participationDisponible()
        ? \App\Models\QuestionProjet::enAttente()->count() : 0;
@endphp

<div class="card mb-3">
    <div class="card-body py-2 px-2">
        <ul class="nav nav-pills flex-nowrap overflow-auto gap-1" style="scrollbar-width: thin">
            @foreach ($onglets as [$route, $motif, $icone, $libelle])
                <li class="nav-item">
                    <a href="{{ route($route) }}" class="nav-link text-nowrap {{ request()->routeIs($motif) ? 'active' : '' }}">
                        <i class="ti ti-{{ $icone }} me-1"></i> {{ $libelle }}
                        @if ($route === 'admin.projets.index' && $questionsEnAttente)
                            <span class="badge bg-pink text-white ms-1" title="Questions de citoyens sans réponse (voir les fiches projet)">{{ $questionsEnAttente }}</span>
                        @endif
                        @if ($route === 'admin.simulations.index' && $enAttente)
                            <span class="badge bg-orange text-white ms-1" title="Scénarios en attente de votre validation">{{ $enAttente }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
