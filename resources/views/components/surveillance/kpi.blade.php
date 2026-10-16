{{--
    Carte indicateur (Tabler "card-sm").
    Exemple : <x-surveillance.kpi libelle="Capteurs actifs" :valeur="9" icone="circle-check" couleur="green" />
    Avec un lien : ajouter :lien="route('...')" pour rendre la carte cliquable.
--}}
@props([
    'libelle',
    'valeur',
    'icone' => 'info-circle',
    'couleur' => 'blue',
    'lien' => null,
])

<div {{ $attributes->merge(['class' => 'card card-sm' . ($lien ? ' card-link' : '')]) }}>
    @if ($lien)<a href="{{ $lien }}" class="text-reset text-decoration-none">@endif
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                <span class="bg-{{ $couleur }} text-white avatar"><i class="ti ti-{{ $icone }}"></i></span>
            </div>
            <div class="col">
                <div class="font-weight-medium h2 mb-0">{{ $valeur }}</div>
                <div class="text-secondary">{{ $libelle }}</div>
            </div>
        </div>
    </div>
    @if ($lien)</a>@endif
</div>