{{-- Binôme 3 — analyse IA (ou mode secours) enregistrée avec une simulation. Variables : $resultat, $noms --}}
@php $a = $resultat['analyse']; @endphp

@if ($resultat['origine'] === 'ia')
    <div class="bg-pink-lt rounded px-3 py-2 mb-3 d-flex gap-2">
        <i class="ti ti-sparkles mt-1"></i>
        <div><strong>Interprétation IA</strong> ({{ $resultat['modele'] }}, {{ $resultat['genereLe'] }}).
            Elle commente les résultats calculés, ne modifie aucune donnée et doit être vérifiée.</div>
    </div>
@else
    <div class="bg-secondary-lt rounded px-3 py-2 mb-3 d-flex gap-2">
        <i class="ti ti-list-check mt-1"></i>
        <div><strong>Mode secours</strong> — rédigé par des règles fixes, sans IA ({{ $resultat['genereLe'] }}).
            <div class="small">{{ $resultat['avertissement'] }}</div></div>
    </div>
@endif

<p>{{ $a['synthese'] }}</p>

<div class="card card-sm bg-primary-lt mb-3">
    <div class="card-body">
        <div class="text-uppercase small fw-bold mb-1"><i class="ti ti-thumb-up me-1"></i>Recommandation : {{ $noms[$a['recommandation']['strategie']] ?? $a['recommandation']['strategie'] }}</div>
        <div>{{ $a['recommandation']['justification'] }}</div>
    </div>
</div>

<div class="row g-2 mb-3">
    @foreach ($a['strategies'] as $s)
        <div class="col-md-6">
            <div class="border rounded p-2 h-100">
                <div class="fw-bold mb-1">{{ $noms[$s['strategie']] ?? $s['strategie'] }}</div>
                @foreach (['points_forts' => ['Points forts', 'check', 'green'], 'compromis' => ['Compromis', 'arrows-left-right', 'orange'], 'risques' => ['Risques', 'alert-triangle', 'red']] as $cle => [$titre, $icone, $couleur])
                    <div class="small text-secondary mt-1">{{ $titre }}</div>
                    <ul class="list-unstyled mb-0">
                        @forelse ($s[$cle] as $t)
                            <li class="d-flex gap-2"><i class="ti ti-{{ $icone }} text-{{ $couleur }} mt-1"></i><span>{{ $t }}</span></li>
                        @empty
                            <li class="text-secondary small">—</li>
                        @endforelse
                    </ul>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="fw-bold mb-1"><i class="ti ti-bulb me-1"></i>Améliorations proposées</div>
        <ul class="mb-0 ps-3">@forelse ($a['ameliorations'] as $t)<li>{{ $t }}</li>@empty<li class="text-secondary">—</li>@endforelse</ul>
    </div>
    <div class="col-md-6">
        <div class="fw-bold mb-1"><i class="ti ti-database-off me-1"></i>Données manquantes</div>
        <ul class="mb-0 ps-3">@forelse ($a['donnees_manquantes'] as $t)<li>{{ $t }}</li>@empty<li class="text-secondary">—</li>@endforelse</ul>
    </div>
</div>
