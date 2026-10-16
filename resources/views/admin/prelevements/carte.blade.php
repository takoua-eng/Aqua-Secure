@extends('layouts.admin')

@section('title', 'Carte de la qualité de l\'eau')
@section('pretitle', "Qualité de l'eau")
@section('subtitle')
    {{ count($carte) }} point{{ count($carte) > 1 ? 's' : '' }} placé{{ count($carte) > 1 ? 's' : '' }} sur la carte
@endsection

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.prelevements.index') }}" class="btn">
            <i class="ti ti-list me-1"></i> Liste des points
        </a>
        <a href="{{ route('admin.prelevements.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Nouveau point
        </a>
    </div>
@endsection

@section('content')
@php
    // État de la dernière analyse => libellé, couleur Tabler, couleur du repère
    $etats = [
        'conforme'     => ['Dernière analyse conforme',     'green',     '#2fb344'],
        'non_conforme' => ['Dernière analyse non conforme', 'red',       '#d63939'],
        'inconnu'      => ['Jamais analysé',                'secondary', '#9aa0ac'],
    ];
    $nombres = collect($carte)->countBy('etat');
    $enRetard = collect($carte)->whereNotNull('retard')->count();
@endphp

<div class="row row-deck row-cards">
    {{-- Compteurs = légende et filtre de la carte --}}
    @foreach ($etats as $cle => [$libelle, $couleur])
        <div class="col-6 col-lg-3">
            <label class="card card-sm card-link mb-0" style="cursor: pointer">
                <div class="card-body d-flex align-items-center">
                    <input type="checkbox" class="form-check-input me-3 filtre-etat" value="{{ $cle }}" checked>
                    <span class="avatar bg-{{ $couleur }} text-white me-3"><i class="ti ti-droplet"></i></span>
                    <div>
                        <div class="h2 mb-0">{{ $nombres[$cle] ?? 0 }}</div>
                        <div class="text-secondary">{{ $libelle }}</div>
                    </div>
                </div>
            </label>
        </div>
    @endforeach
    <div class="col-6 col-lg-3">
        <label class="card card-sm card-link mb-0" style="cursor: pointer">
            <div class="card-body d-flex align-items-center">
                <input type="checkbox" class="form-check-input me-3" id="filtre-retard">
                <span class="avatar bg-orange text-white me-3"><i class="ti ti-clock-exclamation"></i></span>
                <div>
                    <div class="h2 mb-0">{{ $enRetard }}</div>
                    <div class="text-secondary">En retard d'analyse (seulement)</div>
                </div>
            </div>
        </label>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Points de prélèvement</h3>
                    <div class="card-subtitle">
                        Couleur = résultat de la dernière analyse · contour orange = pas d'analyse depuis plus de
                        {{ \App\Models\PointPrelevement::JOURS_RETARD }} jours · repère vide = point hors service.
                    </div>
                </div>
            </div>
            <div id="carte-qualite" style="height: 560px"></div>
        </div>
    </div>

    @if ($sansPosition->isNotEmpty())
        <div class="col-12">
            <div class="alert alert-info mb-0">
                <i class="ti ti-map-off me-1"></i>
                {{ $sansPosition->count() }} point{{ $sansPosition->count() > 1 ? 's' : '' }} sans position ne
                {{ $sansPosition->count() > 1 ? 'sont' : 'est' }} pas sur la carte :
                @foreach ($sansPosition as $point)
                    <a href="{{ route('admin.prelevements.edit', $point) }}" class="font-monospace">{{ $point->code }}</a>@if (! $loop->last), @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function () {
            const points = @json($carte);
            const etats = @json($etats);
            const orange = '#f76707';
            const echapper = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const pourcent = v => v === null ? '—' : v.toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' %';

            const carte = L.map('carte-qualite').setView([34.8, 9.6], 6);   // Tunisie entière
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(carte);

            // Résumé affiché au clic sur un repère
            function resume(p) {
                const [libelle, couleur] = etats[p.etat];
                let html = `<div style="min-width: 220px">
                    <div class="fw-bold">${echapper(p.code)} · ${echapper(p.nom)}</div>
                    <div class="text-secondary small mb-2">${echapper(p.adresse)} · ${echapper(p.commune)}<br>${echapper(p.type)} · réseau ${echapper(p.reseau)}</div>
                    <span class="badge bg-${couleur}-lt">${libelle}</span>`;
                if (!p.actif) html += ' <span class="badge bg-secondary-lt">Hors service</span>';
                if (p.date) html += `<div class="small mt-1">Dernière analyse le ${p.date}</div>`;
                if (p.problemes.length) html += `<ul class="small text-red ps-3 my-1">${p.problemes.map(m => `<li>${echapper(m)}</li>`).join('')}</ul>`;
                if (p.retard) html += `<div class="small mt-1" style="color:${orange}"><i class="ti ti-clock-exclamation"></i> ${echapper(p.retard)}</div>`;
                html += `<div class="small mt-1">Taux de conformité : <b>${pourcent(p.taux)}</b></div>
                    <a href="${p.url}" class="btn btn-sm btn-primary w-100 mt-2">Voir la fiche</a></div>`;
                return html;
            }

            const reperes = points.map(p => ({
                point: p,
                repere: L.circleMarker([p.lat, p.lng], {
                    radius: 10,
                    color: p.retard ? orange : '#ffffff',
                    weight: p.retard ? 4 : 2,
                    fillColor: etats[p.etat][2],
                    fillOpacity: p.actif ? .9 : .15,
                }).bindPopup(resume(p)),
            }));

            // Filtres : cases des compteurs
            function filtrer() {
                const choisis = [...document.querySelectorAll('.filtre-etat:checked')].map(c => c.value);
                const retardSeulement = document.getElementById('filtre-retard').checked;
                reperes.forEach(({ point, repere }) => {
                    const visible = choisis.includes(point.etat) && (!retardSeulement || point.retard);
                    visible ? repere.addTo(carte) : repere.remove();
                });
            }
            document.querySelectorAll('.filtre-etat, #filtre-retard').forEach(c => c.addEventListener('change', filtrer));
            filtrer();

            if (reperes.length) carte.fitBounds(L.featureGroup(reperes.map(r => r.repere)).getBounds().pad(0.1), { maxZoom: 14 });
        })();
    </script>
@endpush
