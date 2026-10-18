{{-- Carte d'un incident dans la liste publique. Variables : $incident, $etats --}}
@php
    $etat = $etats[$incident->id];
    [$libelleEtat, $couleurEtat, $iconeEtat] = \App\Http\Controllers\Front\IncidentPublicController::ETATS[$etat];
    [$libelleImpact, $couleurImpact] = \App\Http\Controllers\Front\IncidentPublicController::IMPACTS[$incident->gravite];
    $icone = \App\Http\Controllers\Front\IncidentPublicController::ICONES[$incident->type] ?? 'bi-exclamation-triangle';
@endphp
<div class="col-md-6 col-lg-4">
    <a href="{{ route('incidents.show', $incident) }}" class="card carte-service border-0 shadow-sm rounded-4 h-100 text-decoration-none text-reset border-start border-5 border-{{ $couleurEtat }}">
        <div class="card-body">
            <div class="d-flex align-items-start mb-2">
                <span class="icone-rond bg-{{ $couleurEtat }} bg-opacity-10 text-{{ $couleurEtat }} me-3 flex-shrink-0" style="width: 46px; height: 46px; font-size: 1.3rem">
                    <i class="bi {{ $icone }}"></i>
                </span>
                <div class="flex-grow-1">
                    <h3 class="h6 fw-bold mb-0">{{ $incident->libelleType() }} — {{ $incident->reseau->zone->nom }}</h3>
                    <div class="small text-secondary"><i class="bi bi-geo-alt"></i> {{ $incident->reseau->zone->commune }} · {{ $incident->reseau->libelleType() }}</div>
                </div>
            </div>
            <div class="small text-{{ $couleurEtat }} fw-semibold"><i class="bi {{ $iconeEtat }}"></i> {{ $libelleEtat }}</div>
            <div class="small text-secondary mb-2">{{ \App\Http\Controllers\Front\IncidentPublicController::detail($incident, $etat) }}</div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="badge text-bg-{{ $couleurImpact }} bg-opacity-75">{{ $libelleImpact }}</span>
                <span class="small text-primary">Voir le suivi <i class="bi bi-chevron-right"></i></span>
            </div>
        </div>
    </a>
</div>
