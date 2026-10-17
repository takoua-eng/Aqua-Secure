@extends('layouts.front')

@section('title', 'Incidents et travaux')

@section('content')
@php
    // [libellé, couleur, icône] de chaque état et apparence de chaque type (définis dans le contrôleur)
    $apparences = \App\Http\Controllers\Front\IncidentPublicController::ETATS;
    $impacts = \App\Http\Controllers\Front\IncidentPublicController::IMPACTS;
    $icones = \App\Http\Controllers\Front\IncidentPublicController::ICONES;
    $filtre = request()->hasAny(['commune', 'type', 'situation']) && array_filter(request()->only(['commune', 'type', 'situation']));
@endphp

{{-- Bandeau --}}
<section class="hero" style="padding: 70px 0 60px">
    <div class="container">
        <h1 class="fw-bold mb-2"><i class="bi bi-cone-striped me-2"></i>Incidents et travaux sur le réseau</h1>
        <p class="lead opacity-75 mb-0">
            Fuites, coupures et contrôles de la qualité de l'eau dans votre commune : où en est la réparation,
            et ce que cela change pour vous.
        </p>
    </div>
</section>

<section class="section pt-5">
    <div class="container">

        {{-- Filtres --}}
        <form method="GET" class="card border-0 shadow-sm rounded-4 p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-secondary mb-1" for="filtre-commune">Commune</label>
                    <select id="filtre-commune" name="commune" class="form-select" onchange="this.form.submit()">
                        <option value="">Toutes les communes</option>
                        @foreach ($communes as $commune)
                            <option value="{{ $commune }}" @selected(request('commune') === $commune)>{{ $commune }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary mb-1" for="filtre-type">Type</label>
                    <select id="filtre-type" name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous les types</option>
                        @foreach (\App\Models\Incident::TYPES as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected(request('type') === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary mb-1" for="filtre-situation">Situation</label>
                    <select id="filtre-situation" name="situation" class="form-select" onchange="this.form.submit()">
                        <option value="">En cours et résolus récemment</option>
                        <option value="en_cours" @selected(request('situation') === 'en_cours')>En cours</option>
                        <option value="resolus" @selected(request('situation') === 'resolus')>Résolus ({{ \App\Models\Incident::JOURS_RESOLUS_PUBLICS }} derniers jours)</option>
                    </select>
                </div>
                <div class="col-md-2 text-md-end">
                    @if ($filtre)
                        <a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-x-lg"></i> Effacer</a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Indicateurs --}}
        <div class="row g-3 mb-4">
            @foreach ([
                ['bi-exclamation-triangle', 'danger',  $indicateurs['en_cours'],      'Incidents en cours'],
                ['bi-tools',                'warning', $indicateurs['en_reparation'], 'Réparations en cours'],
                ['bi-calendar-event',       'info',    $indicateurs['programmes'],    'Interventions programmées'],
                ['bi-check-circle',         'success', $indicateurs['resolus'],       'Résolus en ' . \App\Models\Incident::JOURS_RESOLUS_PUBLICS . ' jours'],
            ] as [$icone, $couleur, $valeur, $libelle])
                <div class="col-6 col-lg-3">
                    <div class="card carte-service border-0 shadow-sm h-100 p-3">
                        <div class="d-flex align-items-center">
                            <span class="icone-rond bg-{{ $couleur }} bg-opacity-10 text-{{ $couleur }} me-3 flex-shrink-0"><i class="bi {{ $icone }}"></i></span>
                            <div>
                                <div class="fs-3 fw-bold lh-1">{{ $valeur }}</div>
                                <div class="small text-secondary">{{ $libelle }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="alert alert-info d-flex rounded-4">
            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
            <div>
                Ces informations sont mises à jour par les équipes de la régie des eaux à chaque étape de la réparation.
                Vous constatez un problème qui n'apparaît pas ici ?
                @if (auth()->user()->isCitoyen())
                    <a href="{{ route('signalement.create') }}" class="alert-link">Signalez-le</a>.
                @else
                    Les citoyens peuvent le signaler depuis leur compte.
                @endif
            </div>
        </div>

        {{-- Carte --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-map me-1"></i> Carte des incidents</div>
            <div id="carte-incidents" style="height: 380px"></div>
            <div class="card-footer bg-white d-flex flex-wrap gap-3 small text-secondary">
                @foreach ($apparences as [$libelle, $couleur])
                    <span><span class="badge text-bg-{{ $couleur }} me-1">&nbsp;</span> {{ $libelle }}</span>
                @endforeach
            </div>
        </div>

        {{-- En cours --}}
        @if (request('situation') !== 'resolus')
            <h2 class="fw-bold h4 mb-3">En cours <span class="text-secondary fw-normal fs-6">· {{ $enCours->count() }}</span></h2>
            <div class="row g-4 mb-5">
                @forelse ($enCours as $incident)
                    @include('front.incidents._carte', ['incident' => $incident])
                @empty
                    <div class="col-12">
                        <div class="text-center text-secondary py-5">
                            <i class="bi bi-check2-circle d-block mb-2 text-success" style="font-size: 2.5rem"></i>
                            {{ $filtre ? 'Aucun incident en cours pour ces critères.' : "Aucun incident en cours sur le réseau : l'alimentation en eau est normale." }}
                        </div>
                    </div>
                @endforelse
            </div>
        @endif

        {{-- Résolus récemment --}}
        @if (request('situation') !== 'en_cours')
            <h2 class="fw-bold h4 mb-3">Résolus récemment <span class="text-secondary fw-normal fs-6">· {{ $resolus->count() }}</span></h2>
            <div class="row g-4">
                @forelse ($resolus as $incident)
                    @include('front.incidents._carte', ['incident' => $incident])
                @empty
                    <div class="col-12">
                        <div class="text-center text-secondary py-4">Aucun incident résolu ces {{ \App\Models\Incident::JOURS_RESOLUS_PUBLICS }} derniers jours.</div>
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</section>

{{-- Bons réflexes --}}
<section class="section section-clair">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Les bons réflexes</h2>
            <p class="text-secondary">Ce que chaque type d'incident peut changer chez vous, et comment réagir.</p>
        </div>
        <div class="row g-3">
            @foreach (\App\Http\Controllers\Front\IncidentPublicController::CONSIGNES as $type => $consigne)
                <div class="col-md-4">
                    <div class="card carte-service border-0 shadow-sm h-100 p-3">
                        <span class="icone-rond bg-primary bg-opacity-10 text-primary mb-2"><i class="bi {{ $icones[$type] }}"></i></span>
                        <h6 class="fw-semibold mb-1">{{ \App\Models\Incident::TYPES[$type] }}</h6>
                        <p class="small mb-1">{{ $consigne['effet'] }}</p>
                        <p class="small text-secondary mb-0">{{ $consigne['conseil'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function () {
            // Un rond coloré par incident, placé sur son réseau (ou au centre de sa commune)
            const incidents = @json($carte);
            const couleurs = { signale: '#dc3545', programme: '#0dcaf0', en_reparation: '#ffc107', resolu: '#198754' };
            const libelles = @json(collect($apparences)->map(fn ($a) => $a[0]));
            const echapper = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

            const carte = L.map('carte-incidents').setView([34.8, 9.6], 6);   // Tunisie entière
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(carte);

            const reperes = incidents.map(i => L.circleMarker([i.lat, i.lng], {
                radius: 9, color: '#ffffff', weight: 2, fillColor: couleurs[i.etat], fillOpacity: .9,
            }).addTo(carte).bindPopup(
                `<b>${echapper(i.type)}</b><br>${echapper(i.zone)}<br>` +
                `<span style="color:${couleurs[i.etat]}">● ${echapper(libelles[i.etat])}</span>` +
                (i.approximative ? '<br><small>Position approximative (centre de la commune)</small>' : '') +
                `<br><a href="${i.url}">Voir le suivi</a>`
            ));

            if (reperes.length) carte.fitBounds(L.featureGroup(reperes).getBounds().pad(0.2), { maxZoom: 14 });
        })();
    </script>
@endpush
