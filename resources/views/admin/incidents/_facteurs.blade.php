{{-- Détail des points du score (moteur de règles). Variable : $priorite --}}
<div class="d-flex flex-column gap-2">
    @foreach ($priorite['facteurs'] as $facteur)
        <div>
            <div class="d-flex justify-content-between small">
                <span><strong>{{ $facteur['libelle'] }}</strong> · <span class="text-secondary">{{ $facteur['explication'] }}</span></span>
                <span class="text-nowrap ms-2" style="font-variant-numeric: tabular-nums">{{ $facteur['points'] }} / {{ $facteur['max'] }}</span>
            </div>
            <div class="progress progress-sm">
                <div class="progress-bar bg-{{ $priorite['niveau']['couleur'] }}"
                     style="width: {{ $facteur['max'] ? round($facteur['points'] / $facteur['max'] * 100) : 0 }}%"
                     role="progressbar" aria-valuenow="{{ $facteur['points'] }}" aria-valuemin="0" aria-valuemax="{{ $facteur['max'] }}"
                     aria-label="{{ $facteur['libelle'] }}"></div>
            </div>
        </div>
    @endforeach
</div>
