@extends('layouts.front')

@use('App\Services\Renovation\ObservatoireCitoyen')
@php
    $t = fn ($cle, $p = []) => __('observatoire.' . $cle, $p, $langue);
    $dt = fn ($v, $d = 0) => number_format((float) $v, $d, ',', ' ');
    $rtl = $langue === 'ar';
    [$libelle, $couleur, $icone] = ObservatoireCitoyen::statut($projet->statut, $langue);
    $etape = ['planifie' => 1, 'en_cours' => 2, 'termine' => 3][$projet->statut] ?? 1;
    $zone = $projet->reseau?->zone;
    $type = __('observatoire.types', [], $langue)[$projet->reseau?->type] ?? '';
@endphp

@section('title', $projet->titre)

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H" crossorigin="anonymous">
@if ($rtl)
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
@endif
<style>
    .obs-ar, .obs-ar .btn, .obs-ar .form-control { font-family: 'Cairo', 'Poppins', sans-serif; }
    .chantier-hero { background: linear-gradient(135deg, var(--eau-fonce) 0%, var(--eau-bleu) 100%); color: #fff; padding: 50px 0 70px; position: relative; }
    .bloc { border: none; border-radius: 18px; box-shadow: 0 6px 24px rgba(10,61,98,.08); }
    .etapes { display: flex; position: relative; }
    .etapes::before { content: ""; position: absolute; top: 26px; inset-inline-start: 16%; inset-inline-end: 16%; height: 4px; background: #e9eef3; }
    .etapes .barre { position: absolute; top: 26px; inset-inline-start: 16%; height: 4px; background: var(--eau-bleu); transition: width 1s ease; }
    .etape { flex: 1; text-align: center; position: relative; }
    .etape .rond { width: 56px; height: 56px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem;
                   background: #e9eef3; color: #adb5bd; position: relative; z-index: 1; border: 4px solid #fff; }
    .etape.faite .rond { background: var(--eau-bleu); color: #fff; }
    .etape.actuelle .rond { box-shadow: 0 0 0 6px rgba(11,111,184,.18); }
    .fait { border-radius: 14px; padding: 16px; height: 100%; background: #f8fafc; border-inline-start: 4px solid; }
    .obs-mosaique { display: grid; grid-template-columns: repeat(10, 1fr); gap: 3px; }
    .obs-mosaique span { aspect-ratio: 1; border-radius: 3px; display: block; }
    .soutien-btn { border-radius: 50px; padding: .6rem 1.4rem; font-weight: 600; }
    .soutien-btn.actif { background: #d63384; border-color: #d63384; color: #fff; }
    .question { border-radius: 14px; background: #f8fafc; padding: 16px; }
    .reponse { border-inline-start: 3px solid var(--eau-bleu); padding-inline-start: 12px; margin-top: 10px; }
    #mini-carte { height: 220px; border-radius: 14px; direction: ltr; }
    .obs-langue { position: absolute; top: 18px; inset-inline-end: 18px; }
    @media (min-width: 992px) { .colonne-fixe { position: sticky; top: 90px; } }
</style>
@endpush

@section('content')
<div dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ $langue }}" class="{{ $rtl ? 'obs-ar' : '' }}">
<section class="chantier-hero">
    <a href="{{ route('renovation.show', ['projet' => $projet, 'langue' => $t('langue_autre_code')]) }}"
       class="btn btn-light btn-sm rounded-pill obs-langue" lang="{{ $t('langue_autre_code') }}"><i class="bi bi-translate me-1"></i>{{ $t('langue_autre') }}</a>
    <div class="container">
        <a href="{{ route('renovation.index') }}" class="text-white-50 text-decoration-none small"><i class="bi bi-arrow-{{ $rtl ? 'right' : 'left' }}"></i> {{ $t('titre_page') }}</a>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 mb-2">
            <span class="badge bg-light text-{{ $couleur }}"><i class="bi {{ $icone }} me-1"></i>{{ $libelle }}</span>
            <span class="badge bg-light bg-opacity-25">{{ $type }} · {{ $projet->reseau?->code }}</span>
        </div>
        <h1 class="fw-bold mb-2" dir="auto">{{ $projet->titre }}</h1>
        <p class="mb-0 opacity-75"><i class="bi bi-geo-alt me-1"></i>{{ $zone?->nom }}, {{ $zone?->commune }}
            · {{ $t('mis_a_jour', ['quand' => $projet->updated_at->locale($langue)->diffForHumans()]) }}</p>
    </div>
</section>

<section class="section pt-0" style="margin-top: -40px">
    <div class="container">
        @if ($errors->any())
            <div class="alert alert-danger rounded-4">{{ $errors->first() }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Avancement --}}
                <div class="card bloc p-4 mb-4">
                    <div class="fw-semibold mb-3"><i class="bi bi-signpost-split me-1 text-primary"></i> {{ $t('ou_en_est') }}</div>
                    <div class="etapes">
                        <div class="barre" id="barre-etapes" style="width: 0" data-largeur="{{ [1 => 0, 2 => 33.5, 3 => 67][$etape] }}%"></div>
                        @foreach ([[1, 'planifie', 'bi-calendar-event'], [2, 'en_cours', 'bi-cone-striped'], [3, 'termine', 'bi-check-circle-fill']] as [$n, $cle, $i])
                            <div class="etape {{ $n <= $etape ? 'faite' : '' }} {{ $n === $etape ? 'actuelle' : '' }}">
                                <span class="rond"><i class="bi {{ $i }}"></i></span>
                                <div class="fw-semibold small mt-2">{{ ObservatoireCitoyen::statut($cle, $langue)[0] }}</div>
                                <div class="small text-secondary d-none d-md-block px-2">{{ $t("etape_{$cle}_texte") }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Pourquoi ce chantier ? --}}
                @if ($pourquoi)
                    <div class="card bloc p-4 mb-4">
                        <div class="fw-semibold mb-1"><i class="bi bi-lightbulb me-1 text-primary"></i> {{ $t('pourquoi_titre') }}</div>
                        <p class="small text-secondary">{{ $t('pourquoi_note') }}</p>
                        <div class="row g-3">
                            @foreach ($pourquoi as $f)
                                <div class="col-sm-6">
                                    <div class="fait border-{{ $f['ton'] }}">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="bi {{ $f['icone'] }} text-{{ $f['ton'] }} fs-5"></i>
                                            <strong>{{ $f['titre'] }}</strong>
                                        </div>
                                        <div class="small text-secondary">{{ $f['texte'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="card bloc p-4 mb-4">
                    <div class="fw-semibold mb-2"><i class="bi bi-file-text me-1 text-primary"></i> {{ $t('le_projet') }}</div>
                    <p class="mb-0" dir="auto">{!! nl2br(e($projet->description)) !!}</p>
                    @if ($t('note_contenu'))
                        <div class="small text-secondary mt-2"><i class="bi bi-info-circle me-1"></i>{{ $t('note_contenu') }}</div>
                    @endif
                </div>

                {{-- Questions des habitants --}}
                <div class="card bloc p-4 mb-4" id="questions">
                    <div class="d-flex align-items-center mb-3">
                        <div class="fw-semibold"><i class="bi bi-chat-square-text me-1 text-primary"></i> {{ $t('questions_titre') }}</div>
                        <span class="badge bg-primary bg-opacity-10 text-primary ms-2">{{ $questions->count() }}</span>
                    </div>

                    @if (! $participation)
                        <p class="text-secondary small mb-0">{{ $t('questions_bientot') }}</p>
                    @else
                        @forelse ($questions as $q)
                            <div class="question mb-3">
                                <div class="small text-secondary mb-1"><i class="bi bi-person-circle me-1"></i>{{ $t('un_habitant') }} · {{ $q->created_at->format('d/m/Y') }}</div>
                                <div dir="auto">{{ $q->question }}</div>
                                <div class="reponse">
                                    <div class="small fw-semibold text-primary"><i class="bi bi-reply me-1"></i>{{ $t('reponse_gestionnaire') }} · {{ $q->repondu_le?->format('d/m/Y') }}</div>
                                    <div class="small" dir="auto">{!! nl2br(e($q->reponse)) !!}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-secondary small">{{ $t('aucune_question') }}</p>
                        @endforelse

                        @foreach ($mesQuestions as $q)
                            <div class="question mb-3 border border-warning-subtle">
                                <div class="small text-warning-emphasis mb-1"><i class="bi bi-hourglass-split me-1"></i>{{ $t('votre_question_attente') }}</div>
                                <div class="small" dir="auto">{{ $q->question }}</div>
                            </div>
                        @endforeach

                        @if ($peutParticiper)
                            <form method="POST" action="{{ route('renovation.questions.store', $projet) }}" class="mt-2">
                                @csrf
                                <label class="form-label small fw-semibold" for="question">{{ $t('votre_question') }}</label>
                                <textarea name="question" id="question" rows="3" dir="auto" class="form-control @error('question') is-invalid @enderror"
                                          minlength="10" maxlength="500" required placeholder="{{ $t('question_placeholder') }}">{{ old('question') }}</textarea>
                                <div class="d-flex align-items-center mt-2">
                                    <small class="text-secondary"><span id="compteur-question">0</span>/500 · {{ $t('question_note') }}</small>
                                    <button class="btn btn-eau ms-auto"><i class="bi bi-send me-1"></i> {{ $t('envoyer') }}</button>
                                </div>
                            </form>
                        @elseif (! auth()->check())
                            <div class="alert alert-light border rounded-4 small mb-0">
                                <i class="bi bi-box-arrow-in-right me-1"></i><a href="{{ route('login') }}">{{ $t('connectez_vous') }}</a> {{ $t('ou') }}
                                <a href="{{ route('register') }}">{{ $t('creez_compte') }}</a> {{ $t('pour_poser') }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Colonne latérale --}}
            <div class="col-lg-4">
                <div class="colonne-fixe">
                    <div class="card bloc p-4 mb-4">
                        <div class="fw-semibold mb-2"><i class="bi bi-cash-coin me-1 text-primary"></i> {{ $t('financement') }}</div>
                        <div class="fs-2 fw-bold lh-1"><bdi data-compteur="{{ $finance }}">{{ $dt($finance) }}</bdi></div>
                        <div class="small text-secondary mb-3">{{ $t('dt_obtenus') }}</div>

                        @if ($pct !== null)
                            <div class="progress rounded-pill mb-1" style="height: 12px">
                                <div class="progress-bar" style="width: {{ $pct }}%; background: var(--eau-bleu)"></div>
                            </div>
                            <div class="small text-secondary mb-3">{{ $t('pct_cout_prevu', ['pct' => $pct, 'cout' => $dt($cout)]) }}</div>
                        @else
                            <div class="small text-secondary mb-3">{{ $t('cout_pas_communique') }}</div>
                        @endif

                        @if ($parHabitant !== null)
                            <div class="rounded-3 p-3 mb-3" style="background: var(--eau-clair)">
                                <div class="small text-secondary">{{ $t('par_habitant_quartier') }}</div>
                                <div class="fs-4 fw-bold text-primary"><bdi>{{ $dt($parHabitant, 2) }}</bdi> {{ $t('dt') }}</div>
                            </div>
                        @endif

                        @if ($surCent)
                            <div class="small fw-semibold mb-2">{{ $t('sur_cent_obtenus') }}</div>
                            <div class="obs-mosaique mb-2">
                                @foreach ($surCent as $part)
                                    @for ($i = 0; $i < $part['parts']; $i++)
                                        <span style="background: {{ ObservatoireCitoyen::couleurSource($part['source']) }}" title="{{ $part['libelle'] }}"></span>
                                    @endfor
                                @endforeach
                            </div>
                            @foreach ($surCent as $part)
                                <div class="d-flex small py-1">
                                    <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background: {{ ObservatoireCitoyen::couleurSource($part['source']) }}"></span>{{ $part['libelle'] }}</span>
                                    <span class="ms-auto"><strong>{{ $part['parts'] }} {{ $t('dt') }}</strong> · <bdi>{{ $dt($part['montant']) }}</bdi> {{ $t('dt') }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="small text-secondary">{{ $t('aucun_financement') }}</div>
                        @endif
                    </div>

                    {{-- Soutien, suivi, partage --}}
                    <div class="card bloc p-4 mb-4 text-center">
                        @if ($participation)
                            <div class="display-6 fw-bold text-danger lh-1">{{ $soutiens }}</div>
                            <div class="small text-secondary mb-3">{{ $t('soutiens_texte') }}</div>
                            @if ($peutParticiper)
                                <form method="POST" action="{{ route('renovation.soutien', $projet) }}">
                                    @csrf
                                    <button class="btn btn-outline-danger soutien-btn {{ $dejaSoutenu ? 'actif' : '' }}">
                                        <i class="bi {{ $dejaSoutenu ? 'bi-heart-fill' : 'bi-heart' }} me-1"></i>
                                        {{ $dejaSoutenu ? $t('je_soutiens') : $t('me_concerne') }}
                                    </button>
                                </form>
                                <div class="small text-secondary mt-2">{{ $t('soutien_note') }}</div>
                            @elseif (! auth()->check())
                                <a href="{{ route('login') }}" class="btn btn-outline-danger soutien-btn"><i class="bi bi-heart me-1"></i> {{ $t('connexion_soutenir') }}</a>
                            @endif
                            <hr>
                        @endif
                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-warning rounded-pill" id="bouton-suivre"><i class="bi bi-star me-1"></i> {{ $t('suivre_court') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="bouton-partager"><i class="bi bi-share me-1"></i> {{ $t('partager') }}</button>
                            <a class="btn btn-sm btn-outline-success rounded-pill" target="_blank" rel="noopener"
                               href="https://wa.me/?text={{ urlencode($projet->titre . ' — ' . route('renovation.show', $projet)) }}"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a>
                        </div>
                        <div class="small text-success mt-2 d-none" id="lien-copie"><i class="bi bi-check2"></i> {{ $t('lien_copie') }}</div>
                    </div>

                    @if ($position)
                        <div class="card bloc p-3 mb-4">
                            <div id="mini-carte"></div>
                            <div class="small text-secondary mt-2"><i class="bi bi-pin-map me-1"></i>
                                {{ $position[2] === 'exacte' ? $t('position_exacte') : $t('position_commune') }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Autres chantiers de la commune --}}
        @if ($voisins->isNotEmpty())
            <h4 class="fw-bold mt-5 mb-3">{{ $t('autres_chantiers', ['commune' => $zone?->commune]) }}</h4>
            <div class="row g-4">
                @foreach ($voisins as $v)
                    @php [$l, $c, $i] = ObservatoireCitoyen::statut($v->statut, $langue); @endphp
                    <div class="col-md-4">
                        <a href="{{ route('renovation.show', $v) }}" class="card bloc p-3 h-100 text-reset text-decoration-none">
                            <span class="badge bg-{{ $c }} bg-opacity-10 text-{{ $c }} align-self-start mb-2"><i class="bi {{ $i }} me-1"></i>{{ $l }}</span>
                            <div class="fw-semibold" dir="auto">{{ $v->titre }}</div>
                            <div class="small text-secondary"><bdi>{{ $dt($v->totalFinance()) }}</bdi> {{ $t('obtenus_popup') }}</div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH" crossorigin="anonymous"></script>
@php $textesJs = ['suivre' => $t('suivre_court'), 'suivi' => $t('suivi_court')]; @endphp
@php $partageJs = ['title' => $projet->titre, 'url' => route('renovation.show', $projet)]; @endphp
<script>
(function () {
    const nombre = v => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v);
    const textes = @json($textesJs);

    // Barre d'avancement et montant animés
    const barre = document.getElementById('barre-etapes');
    setTimeout(() => barre.style.width = barre.dataset.largeur, 150);
    document.querySelectorAll('[data-compteur]').forEach(el => {
        const cible = parseFloat(el.dataset.compteur), debut = performance.now();
        const etape = t => { const p = Math.min(1, (t - debut) / 1000); el.textContent = nombre(cible * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(etape); };
        requestAnimationFrame(etape);
    });

    // Compteur de caractères de la question
    const question = document.getElementById('question');
    question?.addEventListener('input', () => document.getElementById('compteur-question').textContent = question.value.length);

    // Mini-carte
    const position = @json($position);
    if (position && window.L) {
        const map = L.map('mini-carte', { scrollWheelZoom: false, zoomControl: false }).setView([position[0], position[1]], position[2] === 'exacte' ? 15 : 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
        L.circleMarker([position[0], position[1]], { radius: 12, color: '#fff', weight: 3, fillColor: '#0b6fb8', fillOpacity: .95 }).addTo(map);
    }

    // Suivre (mémorisé dans ce navigateur uniquement)
    const CLE = 'aquasecure-renovation-suivis', id = {{ $projet->id }};
    const lire = () => { try { return JSON.parse(localStorage.getItem(CLE)) || []; } catch (e) { return []; } };
    const suivre = document.getElementById('bouton-suivre');
    const maj = () => {
        const actif = lire().includes(id);
        suivre.innerHTML = `<i class="bi ${actif ? 'bi-star-fill' : 'bi-star'} me-1"></i> ${actif ? textes.suivi : textes.suivre}`;
        suivre.classList.toggle('btn-warning', actif);
        suivre.classList.toggle('btn-outline-warning', !actif);
    };
    suivre.addEventListener('click', () => {
        const ids = lire();
        try { localStorage.setItem(CLE, JSON.stringify(ids.includes(id) ? ids.filter(x => x !== id) : [...ids, id])); } catch (e) {}
        maj();
    });
    maj();

    // Partager : menu natif du téléphone, sinon copie du lien
    document.getElementById('bouton-partager').addEventListener('click', async () => {
        const donnees = @json($partageJs);
        try {
            if (navigator.share) { await navigator.share(donnees); return; }
            await navigator.clipboard.writeText(donnees.url);
            document.getElementById('lien-copie').classList.remove('d-none');
        } catch (e) {}
    });
})();
</script>
@endpush
