@extends('layouts.front')

@use('App\Services\Renovation\ObservatoireCitoyen')
@php
    $t = fn ($cle, $p = []) => __('observatoire.' . $cle, $p, $langue);
    $dt = fn ($v, $d = 0) => number_format((float) $v, $d, ',', ' ');
    $rtl = $langue === 'ar';
@endphp

@section('title', $t('titre_page'))

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H" crossorigin="anonymous">
@if ($rtl)
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
@endif
<style>
    /* Binôme 3 — Observatoire citoyen (couleurs du site public) */
    .obs-ar, .obs-ar .btn, .obs-ar .form-select, .obs-ar .form-control { font-family: 'Cairo', 'Poppins', sans-serif; }
    .obs-hero { background: linear-gradient(135deg, var(--eau-fonce) 0%, var(--eau-bleu) 70%, #1aa3d9 100%); color: #fff; padding: 70px 0 110px; position: relative; overflow: hidden; }
    .obs-hero::after { content: ""; position: absolute; left: -10%; right: -10%; bottom: -60px; height: 120px; background: #f6f9fc; border-radius: 50% 50% 0 0; }
    .obs-compteur { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); border-radius: 16px; padding: 18px; backdrop-filter: blur(4px); height: 100%; }
    .obs-compteur .valeur { font-size: 1.9rem; font-weight: 700; line-height: 1.1; direction: ltr; unicode-bidi: isolate; }
    .obs-quartier { margin-top: -70px; position: relative; z-index: 2; }
    .obs-carte-section { border: none; border-radius: 18px; box-shadow: 0 6px 24px rgba(10,61,98,.08); }
    #obs-carte { height: 420px; border-radius: 14px; direction: ltr; }
    .obs-mosaique { display: grid; grid-template-columns: repeat(10, 1fr); gap: 4px; }
    .obs-mosaique span { aspect-ratio: 1; border-radius: 4px; display: block; opacity: 0; transform: scale(.4); transition: all .35s ease; }
    .obs-mosaique.visible span { opacity: 1; transform: scale(1); }
    .obs-projet { border: none; border-radius: 16px; transition: transform .2s, box-shadow .2s; box-shadow: 0 4px 16px rgba(10,61,98,.07); }
    .obs-projet:hover { transform: translateY(-4px); box-shadow: 0 10px 28px rgba(10,61,98,.14); }
    .obs-suivre { border: none; background: none; font-size: 1.3rem; color: #adb5bd; line-height: 1; }
    .obs-suivre.actif { color: #f59f00; }
    .obs-pastille { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
    .obs-timeline { border-inline-start: 3px solid var(--eau-clair); padding-inline-start: 1.25rem; }
    .obs-timeline .point { width: 14px; height: 14px; border-radius: 50%; position: absolute; inset-inline-start: -1.72rem; top: .35rem; border: 3px solid #fff; }
    .obs-jauge { height: 10px; border-radius: 10px; background: #e9eef3; overflow: hidden; }
    .obs-jauge div { height: 100%; border-radius: 10px; }
    .obs-langue { position: absolute; top: 18px; inset-inline-end: 18px; z-index: 2; }
</style>
@endpush

@section('content')
<div dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ $langue }}" class="{{ $rtl ? 'obs-ar' : '' }}">
{{-- ===== Bandeau et chiffres clés ===== --}}
<section class="obs-hero">
    <a href="{{ route('renovation.index', array_merge(request()->except('langue'), ['langue' => $t('langue_autre_code')])) }}"
       class="btn btn-light btn-sm rounded-pill obs-langue" lang="{{ $t('langue_autre_code') }}"><i class="bi bi-translate me-1"></i>{{ $t('langue_autre') }}</a>
    <div class="container position-relative" style="z-index: 1">
        <span class="badge rounded-pill bg-light text-primary mb-3"><i class="bi bi-eye me-1"></i> {{ $t('badge_transparence') }}</span>
        <h1 class="fw-bold mb-2">{{ $t('hero_titre') }}</h1>
        <p class="lead opacity-75 mb-4" style="max-width: 720px">{{ $t('hero_texte') }}</p>
        <div class="row g-3">
            @foreach ([
                ['bi-cash-coin', $indicateurs['montant'], $t('compteur_montant')],
                ['bi-cone-striped', $indicateurs['en_cours'], $t('compteur_en_cours')],
                ['bi-people', $indicateurs['habitants'], $t('compteur_habitants')],
                ['bi-geo-alt', $indicateurs['communes'], $t('compteur_communes')],
            ] as [$icone, $valeur, $libelle])
                <div class="col-6 col-lg-3">
                    <div class="obs-compteur">
                        <i class="bi {{ $icone }} fs-4 opacity-75"></i>
                        <div class="valeur" data-compteur="{{ (float) $valeur }}">{{ $dt($valeur) }}</div>
                        <div class="small opacity-75">{{ $libelle }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        @if ($t('note_contenu'))
            <div class="small opacity-75 mt-3"><i class="bi bi-info-circle me-1"></i>{{ $t('note_contenu') }}</div>
        @endif
    </div>
</section>

<div class="container obs-quartier">
    {{-- ===== Mon quartier ===== --}}
    <form method="GET" class="card obs-carte-section p-3 p-md-4 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold mb-1" for="f-commune"><i class="bi bi-house-heart me-1 text-primary"></i> {{ $t('mon_quartier') }}</label>
                <select name="commune" id="f-commune" class="form-select form-select-lg" onchange="this.form.submit()">
                    <option value="">{{ $t('choisir_commune') }}</option>
                    @foreach ($communes as $c)
                        <option value="{{ $c }}" @selected($commune === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-secondary mb-1" for="f-q">{{ $t('rechercher_chantier') }}</label>
                <input type="search" name="q" id="f-q" value="{{ request('q') }}" class="form-control form-control-lg" placeholder="{{ $t('placeholder_recherche') }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-eau btn-lg flex-fill"><i class="bi bi-search"></i> {{ $t('explorer') }}</button>
                @if (request()->hasAny(['commune', 'q', 'statut']))
                    <a href="{{ route('renovation.index') }}" class="btn btn-outline-secondary btn-lg" title="{{ $t('tout_afficher') }}"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>

        @if ($quartier)
            <hr class="my-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-5">
                    <div class="text-uppercase small text-secondary fw-semibold">{{ $t('votre_commune') }}</div>
                    <h3 class="fw-bold mb-1">{{ $quartier['commune'] }}</h3>
                    <p class="mb-0 text-secondary">{{ $t('quartier_resume', ['projets' => $quartier['projets'], 'en_cours' => $quartier['en_cours'], 'termines' => $quartier['termines']]) }}</p>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-secondary">{{ $t('financements_obtenus') }}</div>
                    <div class="fs-3 fw-bold"><bdi>{{ $dt($quartier['montant']) }}</bdi> <small class="fs-6">{{ $t('dt') }}</small></div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    @if ($quartier['par_habitant'] !== null && $moyenne)
                        @php $max = max($quartier['par_habitant'], $moyenne) ?: 1; @endphp
                        <div class="small text-secondary mb-1">{{ $t('par_habitant_titre') }}</div>
                        <div class="d-flex justify-content-between small"><span>{{ $quartier['commune'] }}</span><strong><bdi>{{ $dt($quartier['par_habitant'], 2) }}</bdi> {{ $t('dt') }}</strong></div>
                        <div class="obs-jauge mb-2"><div style="width: {{ 100 * $quartier['par_habitant'] / $max }}%; background: var(--eau-bleu)"></div></div>
                        <div class="d-flex justify-content-between small"><span>{{ $t('moyenne_communes') }}</span><span><bdi>{{ $dt($moyenne, 2) }}</bdi> {{ $t('dt') }}</span></div>
                        <div class="obs-jauge"><div style="width: {{ 100 * $moyenne / $max }}%; background: #adb5bd"></div></div>
                    @else
                        <div class="small text-secondary">{{ $t('par_habitant_indispo') }}</div>
                    @endif
                </div>
            </div>
            <div class="small text-secondary mt-3"><i class="bi bi-info-circle me-1"></i>{{ $t('par_habitant_note', ['habitants' => $dt($quartier['habitants'])]) }}</div>
        @endif
    </form>

    {{-- ===== Carte + Sur 100 DT ===== --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card obs-carte-section p-3 h-100">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h5 class="fw-semibold mb-0"><i class="bi bi-map me-1 text-primary"></i> {{ $t('carte_titre') }}</h5>
                    <div class="ms-auto small d-flex gap-3">
                        @foreach (['planifie', 'en_cours', 'termine'] as $s)
                            @php [$l, , , $c] = ObservatoireCitoyen::statut($s, $langue); @endphp
                            <span><span class="obs-pastille me-1" style="background: {{ $c }}"></span>{{ $l }}</span>
                        @endforeach
                    </div>
                </div>
                <div id="obs-carte"></div>
                <div class="small text-secondary mt-2">{{ $t('carte_note') }}</div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card obs-carte-section p-4 h-100">
                <h5 class="fw-semibold mb-1"><i class="bi bi-coin me-1 text-primary"></i> {{ $t('sur_cent_titre') }}</h5>
                <p class="small text-secondary">{{ $t('sur_cent_note') }}</p>
                @if ($surCent)
                    <div class="obs-mosaique mb-3" id="obs-mosaique">
                        @foreach ($surCent as $part)
                            @for ($i = 0; $i < $part['parts']; $i++)
                                <span style="background: {{ ObservatoireCitoyen::couleurSource($part['source']) }}" title="{{ $part['libelle'] }}"></span>
                            @endfor
                        @endforeach
                    </div>
                    @foreach ($surCent as $part)
                        <div class="d-flex align-items-center py-1">
                            <span class="obs-pastille me-2" style="background: {{ ObservatoireCitoyen::couleurSource($part['source']) }}"></span>
                            <span><strong>{{ $t('sur_cent_ligne', ['parts' => $part['parts'], 'source' => $part['libelle']]) }}</strong></span>
                            <span class="ms-auto small text-secondary"><bdi>{{ $dt($part['montant']) }}</bdi> {{ $t('dt') }}</span>
                        </div>
                    @endforeach
                @else
                    <div class="text-secondary py-4 text-center">{{ $t('aucun_financement_publie') }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===== Où va l'argent ===== --}}
    @if ($repartition->isNotEmpty())
        <div class="card obs-carte-section p-4 mb-4">
            <h5 class="fw-semibold mb-1"><i class="bi bi-diagram-3 me-1 text-primary"></i> {{ $t('argent_titre') }}</h5>
            <p class="small text-secondary mb-2">{{ $t('argent_note') }}</p>
            <div id="obs-treemap" style="min-height: 320px; direction: ltr"></div>
        </div>
    @endif

    {{-- ===== Mes projets suivis (mémorisés dans ce navigateur) ===== --}}
    <div class="card obs-carte-section p-4 mb-4 d-none" id="obs-suivis">
        <h5 class="fw-semibold mb-1"><i class="bi bi-star-fill text-warning me-1"></i> {{ $t('suivis_titre') }}</h5>
        <p class="small text-secondary mb-3">{{ $t('suivis_note') }}</p>
        <div class="row g-3" id="obs-suivis-liste"></div>
    </div>

    {{-- ===== Liste des projets ===== --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h4 class="fw-bold mb-0 me-3">{{ $commune ? $t('projets_a', ['commune' => $commune]) : $t('tous_les_projets') }}
            <span class="text-secondary fw-normal fs-6">({{ $liste->count() }})</span></h4>
        @foreach (['' => $t('filtre_tous'), 'en_cours' => $t('filtre_en_cours'), 'planifie' => $t('filtre_planifie'), 'termine' => $t('filtre_termine')] as $valeur => $libelle)
            <a href="{{ route('renovation.index', array_filter(['commune' => $commune, 'q' => request('q'), 'statut' => $valeur])) }}"
               class="btn btn-sm rounded-pill {{ (string) request('statut') === $valeur ? 'btn-eau' : 'btn-outline-secondary' }}">{{ $libelle }}</a>
        @endforeach
    </div>

    <div class="row g-4 mb-5">
        @forelse ($liste as $projet)
            @php
                [$libelle, $couleur, $icone] = ObservatoireCitoyen::statut($projet->statut, $langue);
                $finance = $projet->totalFinance();
                $pct = $projet->cout_estime ? min(100, round(100 * $finance / (float) $projet->cout_estime)) : null;
            @endphp
            <div class="col-md-6 col-lg-4">
                <div class="card obs-projet h-100">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-start mb-2">
                            <span class="badge bg-{{ $couleur }} bg-opacity-10 text-{{ $couleur }}"><i class="bi {{ $icone }} me-1"></i>{{ $libelle }}</span>
                            <button type="button" class="obs-suivre ms-auto" data-suivre="{{ $projet->id }}" title="{{ $t('suivre') }}" aria-label="{{ $t('suivre') }}">
                                <i class="bi bi-star"></i>
                            </button>
                        </div>
                        <h5 class="fw-semibold" dir="auto"><a href="{{ route('renovation.show', $projet) }}" class="text-reset text-decoration-none">{{ $projet->titre }}</a></h5>
                        <div class="small text-secondary mb-2"><i class="bi bi-geo-alt me-1"></i>{{ $projet->reseau?->zone?->nom }}, {{ $projet->reseau?->zone?->commune }}
                            · <i class="bi bi-people ms-1 me-1"></i><bdi>{{ $dt($projet->reseau?->zone?->population) }}</bdi> {{ $t('hab') }}</div>
                        <p class="small mb-3 flex-grow-1" dir="auto">{{ \Illuminate\Support\Str::limit($projet->description, 130) }}</p>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ $t('financement_obtenu') }}</span><strong><bdi>{{ $dt($finance) }}</bdi> {{ $t('dt') }}</strong>
                        </div>
                        @if ($pct !== null)
                            <div class="obs-jauge"><div style="width: {{ $pct }}%; background: var(--eau-bleu)"></div></div>
                            <div class="small text-secondary mt-1">{{ $t('pct_cout', ['pct' => $pct]) }}</div>
                        @else
                            <div class="small text-secondary">{{ $t('cout_non_communique') }}</div>
                        @endif
                        <a href="{{ route('renovation.show', $projet) }}" class="btn btn-outline-primary btn-sm mt-3">{{ $t('decouvrir') }} <i class="bi bi-arrow-{{ $rtl ? 'left' : 'right' }}"></i></a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="text-center text-secondary py-5">
                    <i class="bi bi-clipboard-x" style="font-size: 3rem"></i>
                    <p class="mt-2 mb-0">{{ $t('aucun_resultat') }}</p>
                </div>
            </div>
        @endforelse
    </div>

    {{-- ===== Dernières mises à jour ===== --}}
    @if ($recents->isNotEmpty())
        <div class="row g-4 mb-5">
            <div class="col-lg-6">
                <div class="card obs-carte-section p-4 h-100">
                    <h5 class="fw-semibold mb-3"><i class="bi bi-clock-history me-1 text-primary"></i> {{ $t('maj_titre') }}</h5>
                    <div class="obs-timeline">
                        @foreach ($recents as $r)
                            @php [$libelle, , , $couleur] = ObservatoireCitoyen::statut($r->statut, $langue); @endphp
                            <div class="position-relative mb-3">
                                <span class="point" style="background: {{ $couleur }}"></span>
                                <div class="small text-secondary">{{ $r->updated_at->locale($langue)->diffForHumans() }} · {{ $libelle }}</div>
                                <a href="{{ route('renovation.show', $r) }}" class="fw-semibold text-decoration-none" dir="auto">{{ $r->titre }}</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card obs-carte-section p-4 h-100 bg-light">
                    <h5 class="fw-semibold mb-3"><i class="bi bi-patch-question me-1 text-primary"></i> {{ $t('lire_titre') }}</h5>
                    <ul class="small mb-0 ps-3">
                        @foreach (['lire_1', 'lire_2', 'lire_3', 'lire_4'] as $cle)
                            <li class="mb-2">{{ $t($cle) }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif
</div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js" integrity="sha384-5u6RO0Q8xx8HD9yfm1MUPRgt4kTTlZxjJmXIFZxa5pEZy7U9XOtkAZgbF1fSYmsw" crossorigin="anonymous"></script>
@php $textesJs = ['obtenus' => $t('obtenus_popup'), 'du_cout' => $t('du_cout'), 'voir' => $t('voir_chantier'), 'suivre' => $t('suivre'), 'ne_plus' => $t('ne_plus_suivre'), 'dt' => $t('dt')]; @endphp
<script>
(function () {
    const echapper = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const nombre = v => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v);
    const projets = @json($projetsJson);
    const carte = @json($carte);
    const repartition = @json($repartition);
    const textes = @json($textesJs);

    // 1. Compteurs animés
    document.querySelectorAll('[data-compteur]').forEach(el => {
        const cible = parseFloat(el.dataset.compteur), debut = performance.now(), duree = 1200;
        const etape = t => {
            const p = Math.min(1, (t - debut) / duree);
            el.textContent = nombre(cible * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(etape);
        };
        requestAnimationFrame(etape);
    });

    // 2. Mosaïque « Sur 100 DT » : apparition progressive
    const mosaique = document.getElementById('obs-mosaique');
    if (mosaique) {
        mosaique.querySelectorAll('span').forEach((s, i) => s.style.transitionDelay = (i * 12) + 'ms');
        setTimeout(() => mosaique.classList.add('visible'), 200);
    }

    // 3. Carte des chantiers
    if (window.L && document.getElementById('obs-carte')) {
        const map = L.map('obs-carte', { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
        const points = [];
        carte.forEach(p => {
            points.push([p.lat, p.lng]);
            L.circleMarker([p.lat, p.lng], { radius: 11, color: '#fff', weight: 3, fillColor: p.couleur, fillOpacity: .95 })
                .addTo(map)
                .bindPopup(`<div style="min-width:200px" dir="auto"><div class="small text-secondary">${echapper(p.statut)} · ${echapper(p.commune)}</div>
                    <strong>${echapper(p.titre)}</strong><div class="small my-1">${nombre(p.finance)} ${echapper(textes.obtenus)}${p.pct !== null ? ' · ' + p.pct + ' % ' + echapper(textes.du_cout) : ''}</div>
                    <a href="${echapper(p.url)}">${echapper(textes.voir)}</a></div>`);
        });
        points.length ? map.fitBounds(points, { padding: [40, 40], maxZoom: 12 }) : map.setView([34.5, 9.5], 6);
    }

    // 4. Où va l'argent : carte proportionnelle (treemap)
    const zoneTreemap = document.getElementById('obs-treemap');
    if (zoneTreemap && window.ApexCharts) {
        new ApexCharts(zoneTreemap, {
            chart: { type: 'treemap', height: 340, fontFamily: 'Poppins, Cairo, sans-serif', toolbar: { show: false } },
            series: repartition,
            legend: { show: true, position: 'top' },
            dataLabels: { style: { fontSize: '12px' }, formatter: (t, o) => [t.length > 28 ? t.slice(0, 26) + '…' : t, nombre(o.value) + ' ' + textes.dt] },
            tooltip: { y: { formatter: v => nombre(v) + ' ' + textes.dt } },
            plotOptions: { treemap: { distributed: false, enableShades: true, shadeIntensity: .35 } },
            colors: ['#0b6fb8', '#198754', '#6f42c1', '#f59f00', '#d63384', '#0dcaf0', '#fd7e14'],
        }).render();
    }

    // 5. Projets suivis : stockés dans ce navigateur uniquement
    const CLE = 'aquasecure-renovation-suivis';
    const lire = () => { try { return JSON.parse(localStorage.getItem(CLE)) || []; } catch (e) { return []; } };
    const ecrire = ids => { try { localStorage.setItem(CLE, JSON.stringify(ids)); } catch (e) {} };

    function rafraichir() {
        const suivis = lire().filter(id => projets[id]);
        document.querySelectorAll('[data-suivre]').forEach(b => {
            const actif = suivis.includes(Number(b.dataset.suivre));
            b.classList.toggle('actif', actif);
            b.innerHTML = `<i class="bi ${actif ? 'bi-star-fill' : 'bi-star'}"></i>`;
            b.title = actif ? textes.ne_plus : textes.suivre;
        });
        const bloc = document.getElementById('obs-suivis');
        bloc.classList.toggle('d-none', suivis.length === 0);
        document.getElementById('obs-suivis-liste').innerHTML = suivis.map(id => {
            const p = projets[id];
            return `<div class="col-md-6 col-lg-4"><a href="${echapper(p.url)}" class="d-block p-3 rounded-3 border text-reset text-decoration-none h-100">
                <div class="small" style="color:${echapper(p.couleur)}"><i class="bi bi-circle-fill me-1"></i>${echapper(p.statut)}</div>
                <div class="fw-semibold" dir="auto">${echapper(p.titre)}</div>
                <div class="small text-secondary">${echapper(p.commune)} · ${nombre(p.finance)} ${echapper(textes.dt)}${p.pct !== null ? ' · ' + p.pct + ' %' : ''}</div></a></div>`;
        }).join('');
    }

    document.addEventListener('click', e => {
        const b = e.target.closest('[data-suivre]');
        if (!b) return;
        const id = Number(b.dataset.suivre), suivis = lire();
        ecrire(suivis.includes(id) ? suivis.filter(x => x !== id) : [...suivis, id]);
        rafraichir();
    });
    rafraichir();
})();
</script>
@endpush
