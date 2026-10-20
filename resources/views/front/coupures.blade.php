@extends('layouts.front')

@section('title', "Coupures d'eau programmées")

@php
    // Couleurs Bootstrap des statuts
    $couleurs = ['a_venir' => 'primary', 'en_cours' => 'warning', 'terminee' => 'secondary', 'annulee' => 'danger'];
    $moi = auth()->user();
    $peutParticiper = $moi?->isCitoyen() ?? false;
    $min = \App\Models\CommentaireCoupure::LONGUEUR_MIN;
    $max = \App\Models\CommentaireCoupure::LONGUEUR_MAX;
@endphp

@section('content')
<section class="section section-clair">
    <div class="container">
        <div class="d-flex flex-wrap align-items-end gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Coupures d'eau programmées</h1>
                <p class="text-secondary mb-0">
                    Travaux en cours, à venir et récents sur le réseau
                    @if (! $toutes && $zone) dans votre zone <strong>{{ $zone->nom }}</strong> ({{ $zone->commune }})@endif.
                    Les habitants peuvent commenter chaque coupure.
                </p>
            </div>
            @if ($zone)
                <div class="ms-auto btn-group" role="group">
                    <a href="{{ route('coupures.index') }}" class="btn btn-sm {{ $toutes ? 'btn-outline-primary' : 'btn-primary' }}">Ma zone</a>
                    <a href="{{ route('coupures.index', ['toutes' => 1]) }}" class="btn btn-sm {{ $toutes ? 'btn-primary' : 'btn-outline-primary' }}">Toutes les zones</a>
                </div>
            @endif
        </div>

        @if (session('succes_commentaire'))
            <div class="alert alert-success">{{ session('succes_commentaire') }}</div>
        @endif
        @if ($errors->has('like') || $errors->has('contenu'))
            <div class="alert alert-warning">{{ $errors->first('like') ?: $errors->first('contenu') }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                @forelse ($coupures as $coupure)
                    @php
                        $statut = $coupure->statut();
                        $sac = $errors->getBag('commentaire' . $coupure->id);
                    @endphp
                    <div class="card shadow-sm mb-4 {{ $statut === 'en_cours' ? 'border-warning' : '' }}" id="coupure-{{ $coupure->id }}">
                        <div class="card-body">
                            {{-- La coupure --}}
                            <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                                <h2 class="h6 fw-semibold mb-0">
                                    <i class="bi bi-droplet-half me-1"></i>
                                    {{ $coupure->reseau->zone->nom }} <span class="text-secondary fw-normal">· {{ $coupure->reseau->zone->commune }}</span>
                                </h2>
                                <span class="badge text-bg-{{ $couleurs[$statut] }} ms-auto">{{ $coupure->libelleStatut() }}</span>
                            </div>
                            <p class="mb-1">
                                <i class="bi bi-calendar-event me-1"></i>
                                Du <strong>{{ $coupure->debut->locale('fr')->isoFormat('dddd DD/MM/YYYY [à] HH:mm') }}</strong>
                                au <strong>{{ $coupure->fin->locale('fr')->isoFormat('dddd DD/MM/YYYY [à] HH:mm') }}</strong>
                                <span class="text-secondary">({{ $coupure->dureeHeures() }} h)</span>
                            </p>
                            <p class="mb-1"><i class="bi bi-tools me-1"></i> {{ $coupure->motif }}</p>
                            @if ($coupure->description)
                                <p class="small text-secondary mb-0"><i class="bi bi-info-circle me-1"></i> {{ $coupure->description }}</p>
                            @endif
                        </div>

                        {{-- Commentaires des citoyens --}}
                        <div class="card-footer bg-white">
                            <h3 class="h6 fw-semibold mb-3">
                                <i class="bi bi-chat-dots me-1"></i> Commentaires
                                <span class="text-secondary fw-normal">({{ $coupure->commentaires->count() }})</span>
                            </h3>

                            @forelse ($coupure->commentaires as $commentaire)
                                @php
                                    $estMoi = $moi && $commentaire->user_id === $moi->id;
                                    $aime = in_array($commentaire->id, $aimes);
                                @endphp
                                <div class="d-flex gap-2 mb-3">
                                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                                          style="width: 34px; height: 34px; background: var(--eau-bleu); font-size: .8rem">{{ $commentaire->auteur->initiales() }}</span>
                                    <div class="flex-fill">
                                        <div class="small">
                                            <strong>{{ $commentaire->nomPublic() }}</strong>
                                            @if ($estMoi) <span class="badge text-bg-light border">Vous</span> @endif
                                            <span class="text-secondary">· {{ $commentaire->created_at->locale('fr')->diffForHumans() }}</span>
                                        </div>
                                        <p class="mb-1" style="white-space: pre-line">{{ $commentaire->contenu }}</p>

                                        {{-- J'aime : un citoyen peut aimer les commentaires des autres --}}
                                        @if ($peutParticiper && ! $estMoi)
                                            <form method="POST" action="{{ route('commentaires.like', $commentaire) }}" class="d-inline">
                                                @csrf
                                                @if ($toutes && $zone) <input type="hidden" name="toutes" value="1"> @endif
                                                <button type="submit" class="btn btn-sm {{ $aime ? 'btn-danger' : 'btn-outline-danger' }} py-0"
                                                        title="{{ $aime ? 'Je n\'aime plus' : 'J\'aime' }}" aria-pressed="{{ $aime ? 'true' : 'false' }}">
                                                    <i class="bi {{ $aime ? 'bi-heart-fill' : 'bi-heart' }}"></i> {{ $commentaire->likes_count }}
                                                </button>
                                            </form>
                                        @else
                                            <span class="small text-secondary"><i class="bi bi-heart"></i> {{ $commentaire->likes_count }}</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="small text-secondary">Aucun commentaire pour l'instant.</p>
                            @endforelse

                            {{-- Écrire un commentaire : citoyens connectés --}}
                            @if (! $coupure->accepteCommentaires())
                                <p class="small text-secondary mb-0">Les commentaires sont fermés pour cette coupure.</p>
                            @elseif ($peutParticiper)
                                <form method="POST" action="{{ route('coupures.commenter', $coupure) }}" data-validate class="mt-2">
                                    @csrf
                                    @if ($toutes && $zone) <input type="hidden" name="toutes" value="1"> @endif
                                    <label class="form-label small mb-1" for="contenu-{{ $coupure->id }}">Votre commentaire</label>
                                    <textarea name="contenu" id="contenu-{{ $coupure->id }}" rows="2" required
                                              minlength="{{ $min }}" maxlength="{{ $max }}"
                                              class="form-control {{ $sac->has('contenu') ? 'is-invalid' : '' }}"
                                              placeholder="Ex : L'eau est revenue à 15 h dans la rue de Marseille."
                                              data-msg-required="Écrivez votre commentaire."
                                              data-msg-minlength="Le commentaire doit contenir au moins {{ $min }} caractères."
                                              data-msg-maxlength="Le commentaire ne peut pas dépasser {{ $max }} caractères."
                                              data-compteur="compteur-{{ $coupure->id }}">{{ $sac->any() ? old('contenu') : '' }}</textarea>
                                    <div class="invalid-feedback {{ $sac->has('contenu') ? 'd-block' : '' }}" data-feedback-for="contenu">{{ $sac->first('contenu') }}</div>
                                    <div class="d-flex align-items-center mt-2">
                                        <small class="text-secondary" id="compteur-{{ $coupure->id }}">0 / {{ $max }}</small>
                                        <button type="submit" class="btn btn-eau btn-sm ms-auto"><i class="bi bi-send me-1"></i> Publier</button>
                                    </div>
                                </form>
                            @elseif (! $moi)
                                <p class="small mb-0"><a href="{{ route('login') }}">Connectez-vous</a> pour commenter et aimer les commentaires.</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-check-circle text-success d-block mb-2" style="font-size: 2.5rem"></i>
                            <p class="mb-0">Aucune coupure d'eau prévue {{ $toutes ? '' : 'dans votre zone' }} pour le moment.</p>
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="col-lg-4">
                @auth
                    {{-- Ma zone : avis par email --}}
                    <div class="card shadow-sm mb-3">
                        <div class="card-body">
                            <h2 class="h6 fw-semibold mb-3"><i class="bi bi-geo-alt me-1"></i> Ma zone</h2>
                            @include('front.partials.ma-zone')
                        </div>
                    </div>
                @endauth
                <div class="card shadow-sm">
                    <div class="card-body small">
                        <h2 class="h6 fw-semibold mb-2"><i class="bi bi-chat-heart me-1"></i> Règles des commentaires</h2>
                        <ul class="mb-0 ps-3">
                            <li>Partagez des informations utiles : retour de l'eau, citernes, pression faible…</li>
                            <li>Restez courtois : les messages déplacés sont supprimés par l'équipe AquaSecure.</li>
                            <li>Seuls votre prénom et l'initiale de votre nom sont affichés.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
    {{-- Contrôle de saisie du formulaire de commentaire (mêmes règles que le serveur) --}}
    <script src="{{ asset('js/validation.js') }}"></script>
    <script>
        // Compteur de caractères sous chaque zone de commentaire
        document.querySelectorAll('textarea[data-compteur]').forEach(zone => {
            const compteur = document.getElementById(zone.dataset.compteur);
            const majour = () => compteur.textContent = `${zone.value.length} / ${zone.maxLength}`;
            zone.addEventListener('input', majour);
            majour();
        });
    </script>
@endpush
