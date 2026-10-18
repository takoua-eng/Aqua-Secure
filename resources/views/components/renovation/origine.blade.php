{{--
    Binôme 3 — badge qui indique d'où vient une valeur (règle d'or du module : ne jamais confondre).
    <x-renovation.origine type="enregistre" />   donnée réelle lue en base
    <x-renovation.origine type="hypothese" />    valeur saisie dans le simulateur
    <x-renovation.origine type="calcule" />      résultat d'un calcul déterministe
    <x-renovation.origine type="ia" />           interprétation rédigée par l'IA
    <x-renovation.origine type="estime" />       estimation (barème), pas un devis
    <x-renovation.origine type="regles" />       texte du mode secours (règles fixes)
--}}
@props(['type'])

@php
    $types = [
        'enregistre' => ['Enregistré', 'blue', 'database', 'Donnée réelle enregistrée en base'],
        'hypothese'  => ['Hypothèse', 'purple', 'bulb', 'Valeur hypothétique saisie dans le simulateur'],
        'calcule'    => ['Calculé', 'teal', 'calculator', 'Résultat calculé par le moteur (règles déterministes)'],
        'ia'         => ['IA', 'pink', 'sparkles', "Interprétation rédigée par l'IA : à vérifier"],
        'estime'     => ['Estimé', 'yellow', 'ruler-measure', 'Estimation selon un barème, pas un devis'],
        'declare'    => ['Devis', 'green', 'file-certificate', 'Coût déclaré par le gestionnaire'],
        'regles'     => ['Mode secours', 'secondary', 'list-check', 'Texte rédigé par des règles fixes (sans IA)'],
    ];
    [$libelle, $couleur, $icone, $titre] = $types[$type] ?? $types['calcule'];
@endphp

<span {{ $attributes->merge(['class' => "badge bg-{$couleur}-lt text-nowrap"]) }} title="{{ $titre }}">
    <i class="ti ti-{{ $icone }} me-1"></i>{{ $libelle }}
</span>
