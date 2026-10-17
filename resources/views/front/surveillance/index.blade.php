@extends('layouts.front')

@section('title', 'Surveillance du réseau')

@section('content')
@php
    // [libellé, couleur, icône] de chaque état (défini dans le contrôleur)
    $apparences = \App\Http\Controllers\Front\SurveillanceController::ETATS;
    $icones = ['pression' => 'bi-speedometer2', 'debit' => 'bi-water', 'niveau' => 'bi-bar-chart-steps', 'temperature' => 'bi-thermometer-half'];
@endphp

{{-- Bandeau --}}
<section class="hero" style="padding: 70px 0 60px">
    <div class="container">
        <h1 class="fw-bold mb-2"><i class="bi bi-broadcast me-2"></i>Surveillance du réseau en temps réel</h1>
        <p class="lead opacity-75 mb-0">
            Des capteurs mesurent en continu la pression, le débit, le niveau des réservoirs et la température de l'eau
            dans votre quartier. Une mesure anormale peut annoncer une fuite ou une coupure.
        </p>
    </div>
</section>

<section class="section pt-5">
    <div class="container">

        {{-- Filtre par commune --}}
        <form method="GET" class="card border-0 shadow-sm rounded-4 p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small text-secondary mb-1">Commune</label>
                    <select name="commune" class="form-select" onchange="this.form.submit()">
                        <option value="">Toutes les communes</option>
                        @foreach ($communes as $commune)
                            <option value="{{ $commune }}" @selected(request('commune') === $commune)>{{ $commune }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 text-md-end small text-secondary">
                    <i class="bi bi-clock-history"></i> Données mises à jour le {{ now()->format('d/m/Y à H:i') }}
                </div>
            </div>
        </form>

        {{-- Indicateurs --}}
        <div class="row g-3 mb-4">
            @foreach ([
                ['bi-broadcast',               'primary', $indicateurs['capteurs'], 'Capteurs en service'],
                ['bi-check-circle',            'success', $indicateurs['normaux'],  'Mesures normales'],
                ['bi-exclamation-triangle',    'danger',  $indicateurs['alertes'],  'Mesures anormales'],
                ['bi-activity',                'info',    number_format($indicateurs['mesures'], 0, ',', ' '), 'Mesures sur les dernières 24 h'],
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

        @if ($indicateurs['alertes'] > 0)
            <div class="alert alert-warning d-flex rounded-4">
                <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                <div>
                    {{ $indicateurs['alertes'] }} capteur{{ $indicateurs['alertes'] > 1 ? 's signalent' : ' signale' }} une mesure anormale.
                    Les équipes de la régie des eaux sont prévenues automatiquement. Des baisses de pression ou de courtes
                    coupures sont possibles dans les zones concernées. Vous constatez un problème ?
                    @if (auth()->user()->isCitoyen())
                        <a href="{{ route('signalement.create') }}" class="alert-link">Signalez-le</a>.
                    @endif
                </div>
            </div>
        @endif

        {{-- Carte --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-map me-1"></i> Carte des capteurs</div>
            <div id="carte-capteurs" style="height: 380px"></div>
            <div class="card-footer bg-white d-flex flex-wrap gap-3 small text-secondary">
                @foreach ($apparences as [$libelle, $couleur])
                    <span><span class="badge text-bg-{{ $couleur }} me-1">&nbsp;</span> {{ $libelle }}</span>
                @endforeach
            </div>
        </div>

        {{-- Situation par zone --}}
        <h2 class="fw-bold h4 mb-3">Situation par quartier</h2>
        <div class="row g-4">
            @forelse ($zones as $z)
                @php [$libelleZone, $couleurZone] = $z['etat'] === 'alerte' ? ['Perturbations possibles', 'danger'] : ['Situation normale', 'success']; @endphp
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-5 border-{{ $couleurZone }}">
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-3">
                                <div>
                                    <h3 class="h5 fw-bold mb-0">{{ $z['zone']->nom }}</h3>
                                    <div class="small text-secondary">{{ $z['zone']->commune }}</div>
                                </div>
                                <span class="badge text-bg-{{ $couleurZone }} ms-auto">{{ $libelleZone }}</span>
                            </div>

                            <div class="list-group list-group-flush">
                                @foreach ($z['capteurs'] as $capteur)
                                    @php [$libelle, $couleur, $icone] = $apparences[$etats[$capteur->id]]; @endphp
                                    <a href="{{ route('surveillance.show', $capteur) }}" class="list-group-item list-group-item-action d-flex align-items-center px-0">
                                        <span class="icone-rond bg-{{ $couleur }} bg-opacity-10 text-{{ $couleur }} me-3 flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.1rem">
                                            <i class="bi {{ $icones[$capteur->type_mesure] ?? 'bi-broadcast' }}"></i>
                                        </span>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold">{{ $capteur->libelleTypeMesure() }}</div>
                                            <div class="small text-secondary">{{ $capteur->reseau->libelleType() }}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-semibold">{{ $capteur->dernierReleve ? $capteur->avecUnite($capteur->dernierReleve->valeur) : '—' }}</div>
                                            <span class="small text-{{ $couleur }}"><i class="bi {{ $icone }}"></i> {{ $libelle }}</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-broadcast d-block mb-2" style="font-size: 2.5rem"></i>
                        Aucun capteur n'est installé dans cette commune pour le moment.
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</section>

{{-- Comprendre les mesures --}}
<section class="section section-clair">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Comprendre les mesures</h2>
            <p class="text-secondary">Chaque capteur a une plage normale : en dehors, la régie des eaux reçoit une alerte.</p>
        </div>
        <div class="row g-3">
            @foreach (\App\Http\Controllers\Front\SurveillanceController::EXPLICATIONS as $type => $texte)
                <div class="col-md-6 col-lg-3">
                    <div class="card carte-service border-0 shadow-sm h-100 p-3">
                        <span class="icone-rond bg-primary bg-opacity-10 text-primary mb-2"><i class="bi {{ $icones[$type] }}"></i></span>
                        <h6 class="fw-semibold mb-1">{{ \App\Models\Capteur::TYPES_MESURE[$type] }} ({{ \App\Models\Capteur::UNITES[$type] }})</h6>
                        <p class="small text-secondary mb-0">{{ $texte }}</p>
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
            // Un rond coloré par capteur, placé sur son réseau
            const capteurs = @json($carte);
            const couleurs = { normal: '#198754', alerte: '#dc3545', maintenance: '#ffc107', inconnu: '#6c757d' };
            const libelles = @json(collect($apparences)->map(fn ($a) => $a[0]));
            const echapper = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

            const carte = L.map('carte-capteurs').setView([34.8, 9.6], 6);   // Tunisie entière
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(carte);

            const reperes = capteurs.map(c => L.circleMarker([c.lat, c.lng], {
                radius: 9, color: '#ffffff', weight: 2, fillColor: couleurs[c.etat], fillOpacity: .9,
            }).addTo(carte).bindPopup(
                `<b>${echapper(c.nom)}</b><br>` +
                (c.valeur ? `Dernière mesure : <b>${echapper(c.valeur)}</b><br>` : '') +
                `<span style="color:${couleurs[c.etat]}">● ${libelles[c.etat]}</span>` +
                `<br><a href="${c.url}">Voir le détail</a>`
            ));

            if (reperes.length) carte.fitBounds(L.featureGroup(reperes).getBounds().pad(0.2), { maxZoom: 14 });
        })();
    </script>
@endpush