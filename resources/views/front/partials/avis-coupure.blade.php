{{-- Bandeau d'avis : coupure en cours ou prévue dans les 7 jours dans la zone du citoyen connecté --}}
@auth
    @php
        $prochaine = auth()->user()->zone_id
            ? \App\Models\CoupureProgrammee::with('reseau.zone')->actives()
                ->dansZone(auth()->user()->zone_id)
                ->where('debut', '<=', now()->addDays(7))
                ->orderBy('debut')->first()
            : null;
    @endphp

    @if ($prochaine)
        <div class="alert {{ $prochaine->statut() === 'en_cours' ? 'alert-warning' : 'alert-info' }} rounded-0 border-0 mb-0">
            <div class="container d-flex flex-wrap align-items-center gap-2">
                <i class="bi bi-droplet-half"></i>
                <span>
                    <strong>{{ $prochaine->statut() === 'en_cours' ? "Coupure d'eau en cours" : "Coupure d'eau prévue" }}</strong>
                    dans votre zone ({{ $prochaine->reseau->zone->nom }}) du {{ $prochaine->debut->format('d/m à H:i') }}
                    au {{ $prochaine->fin->format('d/m à H:i') }} : {{ $prochaine->motif }}.
                </span>
                <a href="{{ route('coupures.index') }}" class="alert-link ms-auto">Détails</a>
            </div>
        </div>
    @endif
@endauth
