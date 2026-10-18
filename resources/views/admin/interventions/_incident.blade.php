{{-- Rappel de l'incident concerné (formulaires d'intervention). Variable : $incident --}}
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="avatar bg-{{ $incident->couleurGravite() }}-lt"><i class="ti ti-{{ $incident->iconeType() }}"></i></span>
        <div class="flex-fill">
            <div>
                <a href="{{ route('admin.incidents.show', $incident) }}" class="fw-bold">Incident n° {{ $incident->id }} · {{ $incident->libelleType() }}</a>
                <span class="badge bg-{{ $incident->couleurGravite() }}-lt ms-1">{{ $incident->libelleGravite() }}</span>
                <span class="badge bg-{{ $incident->couleurStatut() }}-lt ms-1">{{ $incident->libelleStatut() }}</span>
            </div>
            <div class="small text-secondary">
                Réseau <span class="font-monospace">{{ $incident->reseau->code }}</span> · {{ $incident->reseau->zone->libelle() }}
                · signalé le {{ $incident->date_signalement->format('d/m/Y') }}
            </div>
        </div>
    </div>
</div>
