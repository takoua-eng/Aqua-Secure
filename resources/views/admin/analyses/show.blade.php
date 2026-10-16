@extends('layouts.admin')

@section('title', 'Bulletin d\'analyse du ' . $analyse->date_prelevement->format('d/m/Y'))
@section('pretitle', "Qualité de l'eau · Analyse")

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.analyses.index') }}" class="btn btn-link">
            <i class="ti ti-arrow-left me-1"></i> Retour à la liste
        </a>
        <a href="{{ route('admin.analyses.pdf', $analyse) }}" class="btn">
            <i class="ti ti-file-type-pdf me-1"></i> Exporter en PDF
        </a>
        <a href="{{ route('admin.analyses.edit', $analyse) }}" class="btn btn-primary">
            <i class="ti ti-edit me-1"></i> Modifier
        </a>
    </div>
@endsection

@section('content')
@php
    $horsNorme = $analyse->parametresHorsNorme();
    $point = $analyse->point;
@endphp

<div class="row row-cards">
    {{-- Verdict --}}
    <div class="col-12">
        <div class="alert alert-{{ $analyse->conforme ? 'success' : 'danger' }} mb-0">
            <div class="d-flex">
                <i class="ti ti-{{ $analyse->conforme ? 'circle-check' : 'alert-octagon' }} me-2" style="font-size: 1.5rem"></i>
                <div>
                    <h4 class="alert-title mb-1">Eau {{ mb_strtolower($analyse->libelleConformite()) }}</h4>
                    @if ($analyse->conforme)
                        Les 5 paramètres respectent les seuils de potabilité (OMS / NT 09.14).
                    @else
                        {{ count($horsNorme) }} paramètre{{ count($horsNorme) > 1 ? 's' : '' }} hors norme :
                        <ul class="mb-0 mt-1">
                            @foreach ($horsNorme as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Prélèvement --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-droplet me-1"></i> Prélèvement</h3></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-secondary">Point</dt>
                    <dd class="col-7">
                        <a href="{{ route('admin.prelevements.show', $point) }}" class="font-monospace">{{ $point->code }}</a>
                        <div class="small">{{ $point->nom }}</div>
                    </dd>
                    <dt class="col-5 fw-normal text-secondary">Type</dt>
                    <dd class="col-7">{{ $point->libelleType() }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Adresse</dt>
                    <dd class="col-7">{{ $point->adresse }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Réseau</dt>
                    <dd class="col-7 font-monospace">{{ $point->reseau->code }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Zone</dt>
                    <dd class="col-7">{{ $point->reseau->zone->libelle() }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Date</dt>
                    <dd class="col-7">{{ $analyse->date_prelevement->format('d/m/Y') }}</dd>
                    <dt class="col-5 fw-normal text-secondary">Laboratoire</dt>
                    <dd class="col-7 mb-0">{{ $analyse->laboratoire }}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Résultats --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-flask me-1"></i> Résultats</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Paramètre</th>
                            <th class="text-end">Valeur mesurée</th>
                            <th>Norme</th>
                            <th>Résultat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (\App\Models\AnalyseQualite::PARAMETRES as $cle => $infos)
                            @php $hors = isset($horsNorme[$cle]); @endphp
                            <tr>
                                <td class="fw-medium">{{ $infos['libelle'] }}</td>
                                <td class="text-end {{ $hors ? 'text-red fw-bold' : '' }}" style="font-variant-numeric: tabular-nums">
                                    {{ \App\Models\AnalyseQualite::formater($cle, $analyse->{$cle}) }}
                                </td>
                                <td class="text-secondary">{{ \App\Models\AnalyseQualite::norme($cle) }}</td>
                                <td>
                                    <span class="badge bg-{{ $hors ? 'red' : 'green' }}-lt">{{ $hors ? 'Hors norme' : 'Conforme' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-secondary">
                Seuils de potabilité : OMS / norme tunisienne NT 09.14. Saisie le {{ $analyse->created_at->format('d/m/Y à H:i') }}.
            </div>
        </div>
    </div>

    {{-- Détection de contamination (modèle IA) : ce qu'elle aurait annoncé avec les seules mesures de terrain, comparé au laboratoire --}}
    @if ($predictionIA)
        @php
            $alerte = $predictionIA['probabilite'] >= \App\Services\DetectionContamination::SEUIL_ALERTE;
            $contamine = $analyse->bacteries_coliformes > 0;
        @endphp
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-virus-search me-1"></i> Détection de contamination (IA)</h3>
                    <div class="card-actions">
                        <a href="{{ route('admin.detection.index') }}" class="btn btn-sm">Voir le modèle</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-3 text-center">
                            <div class="display-6 fw-bold text-{{ $predictionIA['niveau']['couleur'] }}">{{ round($predictionIA['probabilite'] * 100) }} %</div>
                            <span class="badge bg-{{ $predictionIA['niveau']['couleur'] }}-lt">{{ $predictionIA['niveau']['libelle'] }}</span>
                        </div>
                        <div class="col-md-5">
                            <div class="small text-secondary mb-1">Raisonnement du modèle (mesures de terrain uniquement) :</div>
                            <ul class="small mb-0 ps-3">
                                @foreach ($predictionIA['raisons'] as $raison)
                                    <li>{{ $raison }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-secondary mb-1">Résultat du laboratoire :</div>
                            <div class="fw-medium">
                                {{ $contamine ? 'Coliformes présents (' . $analyse->bacteries_coliformes . ' UFC/100 mL)' : 'Aucune bactérie coliforme' }}
                            </div>
                            @if ($alerte === $contamine)
                                <span class="badge bg-green-lt mt-1"><i class="ti ti-check me-1"></i> Prédiction juste</span>
                            @elseif ($alerte)
                                <span class="badge bg-yellow-lt mt-1"><i class="ti ti-alert-circle me-1"></i> Fausse alerte (prudence)</span>
                            @else
                                <span class="badge bg-red-lt mt-1"><i class="ti ti-x me-1"></i> Contamination non anticipée</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
