{{--
    Badges d'état d'un relevé : Normal / Hors seuil (trop bas, trop haut) + Anomalie détectée par l'IA.
    Exemple : <x-surveillance.etat-releve :releve="$releve" :anomalie="$anomalies[$releve->id] ?? null" />
--}}
@props([
    'releve',
    'anomalie' => null,   // ['score' => ..., 'attendu' => ...] ou null
])

<span class="d-inline-flex flex-wrap gap-1">
    @if ($releve->hors_seuil)
        <span class="badge bg-red-lt text-nowrap">
            <i class="ti ti-arrow-{{ $releve->sens() === 'trop bas' ? 'down' : 'up' }}"></i> Hors seuil · {{ $releve->sens() }}
        </span>
    @else
        <span class="badge bg-green-lt">Normal</span>
    @endif

    @if ($anomalie)
        <span class="badge bg-orange-lt text-nowrap"
              title="Valeur attendue vers {{ $releve->capteur->avecUnite($anomalie['attendu']) }} — score z = {{ $anomalie['score'] }}">
            <i class="ti ti-sparkles"></i> Anomalie
        </span>
    @endif
</span>