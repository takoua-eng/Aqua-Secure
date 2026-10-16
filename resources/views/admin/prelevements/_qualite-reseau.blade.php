{{--
    Qualité de l'eau d'un réseau, affichée sur sa fiche (admin/reseaux/edit) — module binôme 5.
    Variable attendue : $reseau
--}}
@php $qualite = \App\Services\QualiteEau::pourReseau($reseau); @endphp

<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-droplet me-1"></i> Qualité de l'eau</h3>
        <div class="card-actions d-flex align-items-center gap-2">
            @if ($qualite['taux'] !== null)
                <span class="badge bg-{{ \App\Models\PointPrelevement::couleurTaux($qualite['taux']) }} text-white fs-4">
                    {{ number_format($qualite['taux'], 1, ',', ' ') }} % conforme
                </span>
            @endif
            <a href="{{ route('admin.prelevements.create') }}" class="btn btn-sm"><i class="ti ti-plus me-1"></i> Point de prélèvement</a>
        </div>
    </div>
    @if ($qualite['points']->isEmpty())
        <div class="card-body text-secondary">Aucun point de prélèvement sur ce réseau.</div>
    @else
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr><th>Point</th><th>Analyses</th><th>Dernière analyse</th><th>Conformité</th></tr>
                </thead>
                <tbody>
                    @foreach ($qualite['points'] as $point)
                        @php
                            $derniere = $point->derniereAnalyse;
                            $taux = $point->analyses_count ? round($point->analyses_conformes_count / $point->analyses_count * 100, 1) : null;
                            $retard = \App\Models\PointPrelevement::retardAnalyse($point->actif, $derniere?->date_prelevement);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.prelevements.show', $point) }}" class="font-monospace">{{ $point->code }}</a>
                                <div class="small text-secondary">{{ $point->nom }}@unless ($point->actif) · hors service @endunless</div>
                            </td>
                            <td class="text-secondary">{{ $point->analyses_count }}</td>
                            <td>
                                @if ($derniere)
                                    <span class="badge bg-{{ $derniere->couleurConformite() }}-lt">{{ $derniere->libelleConformite() }}</span>
                                    <span class="small text-secondary ms-1">{{ $derniere->date_prelevement->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                                @if ($retard)
                                    <div><span class="badge bg-orange-lt mt-1">{{ $retard }}</span></div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ \App\Models\PointPrelevement::couleurTaux($taux) }}-lt">
                                    {{ $taux === null ? '—' : number_format($taux, 1, ',', ' ') . ' %' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
