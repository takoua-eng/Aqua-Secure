{{-- Binôme 3 — montant en dinars : <x-renovation.montant :valeur="1250000.5" /> => 1 250 000,50 DT --}}
@props(['valeur', 'decimales' => null])

@php
    $v = (float) $valeur;
    $d = $decimales ?? (fmod($v, 1) == 0.0 ? 0 : 2);
@endphp
<span {{ $attributes->merge(['class' => 'text-nowrap']) }}>{{ number_format($v, $d, ',', ' ') }}&nbsp;DT</span>
