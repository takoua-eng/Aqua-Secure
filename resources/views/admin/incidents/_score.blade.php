{{-- Badge du score de priorité. Variable : $priorite (App\Services\PrioriteIncident::calculer) --}}
@if ($priorite['resolu'])
    <span class="badge bg-green-lt" title="Incident résolu : hors classement">Résolu</span>
@else
    <span class="badge bg-{{ $priorite['niveau']['couleur'] }} text-{{ $priorite['niveau']['couleur'] }}-fg"
          title="Priorité {{ mb_strtolower($priorite['niveau']['libelle']) }} : {{ $priorite['score'] }} points sur 100">
        {{ $priorite['score'] }}<span class="opacity-75">/100</span> · {{ $priorite['niveau']['libelle'] }}
    </span>
@endif
