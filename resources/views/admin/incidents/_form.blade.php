{{-- Formulaire d'incident (création et modification). Variables : $incident, $reseaux, $reseauVerrouille --}}
@if ($errors->any())
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle me-1"></i>
        {{ $errors->has('regle') ? $errors->first('regle') : 'Veuillez corriger les champs en rouge.' }}
    </div>
@endif

@if (empty($reseaux))
    {{-- Un incident doit appartenir à un réseau : on prévient s'il n'y en a aucun --}}
    <div class="alert alert-warning">
        <i class="ti ti-info-circle me-1"></i> Aucun réseau n'existe encore.
        <a href="{{ route('admin.reseaux.create') }}" class="alert-link">Créez d'abord un réseau</a>.
    </div>
@endif

<div class="row">
    <div class="col-lg-8">
        @if ($reseauVerrouille)
            {{-- Règle I3 : une intervention a commencé, le réseau ne peut plus changer --}}
            <div class="mb-3">
                <label class="form-label required" for="reseau-affiche">Réseau concerné</label>
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-lock"></i></span>
                    <input type="text" id="reseau-affiche" class="form-control" value="{{ $reseaux[$incident->reseau_id] ?? '' }}" disabled>
                </div>
                <input type="hidden" name="reseau_id" value="{{ $incident->reseau_id }}">
                <small class="form-hint">Une intervention a déjà commencé sur cet incident : son réseau ne peut plus être changé.</small>
                @error('reseau_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
        @else
            <x-select name="reseau_id" label="Réseau concerné" :options="$reseaux"
                      :value="$incident->reseau_id" required
                      placeholder="— Choisir un réseau —"
                      data-recherche="Rechercher un réseau (code, zone)…"
                      data-msg-required="Choisissez le réseau concerné par l'incident."
                      valid-message="Réseau sélectionné." />
        @endif
    </div>

    <div class="col-lg-4">
        <x-input name="date_signalement" type="date" label="Date de signalement" icon="calendar"
                 :value="optional($incident->date_signalement)->format('Y-m-d')" required
                 max="{{ today()->format('Y-m-d') }}"
                 hint="Date de constat de l'incident. Jamais dans le futur."
                 data-msg-required="La date de signalement est obligatoire."
                 data-msg-max="La date de signalement ne peut pas être dans le futur."
                 valid-message="Date valide." />
    </div>
</div>

{{-- Type et gravité : boutons à choix unique --}}
@php
    $typeChoisi = old('type', $incident->type);
    $graviteChoisie = old('gravite', $incident->gravite);
    $iconesType = ['fuite' => 'droplet', 'contamination' => 'virus', 'coupure' => 'droplet-off'];
    $aidesType = [
        'fuite'         => "Pertes d'eau sur le réseau",
        'contamination' => 'Risque sanitaire',
        'coupure'       => "Privation d'accès à l'eau",
    ];
@endphp

<div class="mb-3">
    <label class="form-label required">Type d'incident</label>
    <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column flex-md-row gap-2">
        @foreach (\App\Models\Incident::TYPES as $valeur => $libelle)
            <label class="form-selectgroup-item flex-fill">
                <input type="radio" name="type" value="{{ $valeur }}" class="form-selectgroup-input" @checked($typeChoisi === $valeur)>
                <span class="form-selectgroup-label d-flex align-items-center p-3">
                    <span class="me-3"><span class="form-selectgroup-check"></span></span>
                    <span class="form-selectgroup-label-content d-flex align-items-center">
                        <span class="avatar me-3 bg-azure-lt"><i class="ti ti-{{ $iconesType[$valeur] }}"></i></span>
                        <span>
                            <span class="font-weight-medium d-block">{{ $libelle }}</span>
                            <span class="small text-secondary">{{ $aidesType[$valeur] }}</span>
                        </span>
                    </span>
                </span>
            </label>
        @endforeach
    </div>
    @error('type') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label required">Gravité</label>
    <div class="form-selectgroup">
        @foreach (\App\Models\Incident::GRAVITES as $valeur => $libelle)
            <label class="form-selectgroup-item">
                <input type="radio" name="gravite" value="{{ $valeur }}" class="form-selectgroup-input" @checked($graviteChoisie === $valeur)>
                <span class="form-selectgroup-label">
                    <span class="badge bg-{{ \App\Models\Incident::couleurGraviteDe($valeur) }} me-1 p-1"></span> {{ $libelle }}
                </span>
            </label>
        @endforeach
    </div>
    <small class="form-hint d-block mt-1">Critique : risque immédiat pour la population ou l'alimentation en eau.</small>
    @error('gravite') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script src="{{ asset('js/select-recherche.js') }}"></script>
@endpush
