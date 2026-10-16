{{--
    Widgets "Qualité de l'eau" du tableau de bord (module binôme 5).
    Données : App\Services\QualiteEau::tableauDeBord()
--}}
@php
    $qualite = \App\Services\QualiteEau::tableauDeBord();
    $nbSurveiller = $qualite['aSurveiller']->count();
    $moisEnCours = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][now()->month - 1];
@endphp

<div class="col-12">
    <h3 class="mb-0 mt-2">
        Qualité de l'eau <span class="text-secondary fw-normal">· {{ $moisEnCours }} {{ now()->year }}</span>
    </h3>
</div>

@foreach ([
    ['shield-check',  \App\Models\PointPrelevement::couleurTaux($qualite['taux']),
        $qualite['taux'] === null ? '—' : number_format($qualite['taux'], 1, ',', ' ') . ' %',
        'Taux de conformité du mois', $qualite['mois'] . ' analyse' . ($qualite['mois'] > 1 ? 's' : ''),
        route('admin.analyses.index', ['du' => now()->startOfMonth()->toDateString()])],
    ['alert-octagon', $qualite['nonConformes'] ? 'red' : 'green', $qualite['nonConformes'],
        'Analyses non conformes ce mois', 'eau hors norme',
        route('admin.analyses.index', ['conforme' => '0', 'du' => now()->startOfMonth()->toDateString()])],
    ['eye-exclamation', $nbSurveiller ? 'orange' : 'green', $nbSurveiller,
        'Points à surveiller', 'non conformes ou en retard',
        route('admin.prelevements.carte')],
] as [$icone, $couleur, $valeur, $libelle, $detail, $lien])
    <div class="col-sm-6 col-lg-4">
        <a href="{{ $lien }}" class="card card-sm card-link text-reset text-decoration-none">
            <div class="card-body d-flex align-items-center">
                <span class="avatar bg-{{ $couleur }} text-white me-3"><i class="ti ti-{{ $icone }}"></i></span>
                <div>
                    <div class="h2 mb-0">{{ $valeur }}</div>
                    <div class="text-secondary">{{ $libelle }} <span class="small">· {{ $detail }}</span></div>
                </div>
            </div>
        </a>
    </div>
@endforeach

<div class="col-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-eye-exclamation me-1"></i> Points à surveiller</h3>
            <div class="card-actions">
                <a href="{{ route('admin.prelevements.carte') }}" class="btn btn-sm"><i class="ti ti-map-pin me-1"></i> Voir sur la carte</a>
            </div>
        </div>
        @if ($qualite['aSurveiller']->isEmpty())
            <div class="card-body text-center text-secondary">
                <i class="ti ti-circle-check text-green me-1"></i> Tous les points en service ont une eau conforme et une analyse récente.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr><th>Point</th><th>Commune</th><th>Dernière analyse</th><th>Raison</th><th class="w-1"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($qualite['aSurveiller']->take(6) as ['point' => $point, 'derniere' => $derniere, 'raisons' => $raisons, 'contamine' => $contamine])
                            <tr>
                                <td>
                                    <span class="badge bg-{{ $contamine ? 'red' : 'orange' }} me-1"></span>
                                    <a href="{{ route('admin.prelevements.show', $point) }}" class="font-monospace">{{ $point->code }}</a>
                                    <div class="small text-secondary">{{ $point->nom }}</div>
                                </td>
                                <td class="text-secondary">{{ $point->reseau->zone->commune }}</td>
                                <td class="text-secondary">{{ $derniere?->date_prelevement->format('d/m/Y') ?? '—' }}</td>
                                <td class="small">
                                    @foreach ($raisons as $raison)
                                        <div class="{{ $raison['retard'] ? 'text-orange' : 'text-red' }}">{{ $raison['texte'] }}</div>
                                    @endforeach
                                </td>
                                <td>
                                    <a href="{{ route('admin.analyses.create', ['point' => $point->id]) }}" class="btn btn-sm" title="Saisir une nouvelle analyse">
                                        <i class="ti ti-flask me-1"></i> Analyser
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($nbSurveiller > 6)
                <div class="card-footer text-secondary small">
                    … et {{ $nbSurveiller - 6 }} autre{{ $nbSurveiller - 6 > 1 ? 's' : '' }} point{{ $nbSurveiller - 6 > 1 ? 's' : '' }}.
                    <a href="{{ route('admin.prelevements.carte') }}">Tout voir sur la carte</a>
                </div>
            @endif
        @endif
    </div>
</div>
