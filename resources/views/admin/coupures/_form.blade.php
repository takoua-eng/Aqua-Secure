@if ($errors->any())
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle me-1"></i> Veuillez corriger les champs en rouge.
    </div>
@endif

@php
    $maintenant = now()->format('Y-m-d\TH:i');
    $erreurDescription = $errors->first('description');
@endphp

<div class="row">
    <div class="col-md-6">
        <x-select name="reseau_eau_id" label="Réseau concerné par les travaux" :options="$reseaux"
                  :value="$coupure->reseau_eau_id" required
                  placeholder="Rechercher un réseau…"
                  data-recherche="Rechercher un réseau (code ou zone)…"
                  data-msg-required="Choisissez le réseau concerné par les travaux."
                  valid-message="Réseau sélectionné." />
    </div>

    <div class="col-md-6">
        <x-input name="motif" label="Motif" icon="tool" :value="$coupure->motif" required
                 placeholder="Ex : Remplacement d'une vanne de sectionnement"
                 minlength="5" maxlength="150"
                 data-msg-required="Le motif est obligatoire."
                 data-msg-minlength="Le motif doit contenir au moins 5 caractères."
                 data-msg-maxlength="Le motif ne peut pas dépasser 150 caractères."
                 valid-message="Motif valide." />
    </div>

    <div class="col-md-6">
        <x-input name="debut" type="datetime-local" label="Début de la coupure" icon="calendar-event" required
                 :value="$coupure->debut?->format('Y-m-d\TH:i')" :min="$maintenant"
                 data-msg-required="La date de début est obligatoire."
                 data-msg-min="La coupure doit commencer dans le futur."
                 valid-message="Date de début valide." />
    </div>

    <div class="col-md-6">
        <x-input name="fin" type="datetime-local" label="Fin de la coupure" icon="calendar-check" required
                 :value="$coupure->fin?->format('Y-m-d\TH:i')" :min="$maintenant"
                 hint="7 jours au plus."
                 data-msg-required="La date de fin est obligatoire."
                 data-msg-min="La fin doit être après le début."
                 data-msg-max="Une coupure programmée ne peut pas durer plus de 7 jours."
                 valid-message="Date de fin valide." />
    </div>

    <div class="col-12">
        <div class="mb-3">
            <label class="form-label" for="champ-description">Informations pour les habitants <span class="text-secondary fw-normal">(facultatif)</span></label>
            <textarea name="description" id="champ-description" rows="3" maxlength="1000"
                      class="form-control {{ $erreurDescription ? 'is-invalid' : '' }}"
                      placeholder="Ex : Des citernes seront installées place de la République."
                      data-msg-maxlength="La description ne peut pas dépasser 1 000 caractères.">{{ old('description', $coupure->description) }}</textarea>
            <div class="invalid-feedback {{ $erreurDescription ? 'd-block' : '' }}" data-feedback-for="description">{{ $erreurDescription }}</div>
            <div class="valid-feedback" data-valid-for="description">Description enregistrée.</div>
        </div>
    </div>
</div>

<div class="alert alert-info mb-0">
    <i class="ti ti-bell me-1"></i>
    Les citoyens qui habitent la zone de ce réseau seront prévenus par email et dans leur espace dès l'enregistrement.
</div>

@push('styles')
    {{-- integrity : le navigateur refuse le fichier s'il a été modifié sur le CDN (empreinte SHA-384) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css"
          integrity="sha384-piG3EtH1fBnPi68q4spy+Qgpb0dHK1D1dwk0GaHwFkvmUxYi526bBlk3xJcjEBsD" crossorigin="anonymous">
    <style>
        .ts-wrapper.is-invalid .ts-control { border-color: var(--tblr-danger); }
        .ts-wrapper.is-valid .ts-control { border-color: var(--tblr-success); }
        .ts-dropdown .optgroup-header { font-weight: 600; color: var(--tblr-secondary); }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"
            integrity="sha384-cnROoUgVILyibe3J0zhzWoJ9p2WmdnK7j/BOTSWqVDbC1pVw2d+i6Q/1ESKJKCYf" crossorigin="anonymous"></script>
    <script src="{{ asset('js/select-recherche.js') }}"></script>
    <script>
        // La fin doit suivre le début et ne pas dépasser 7 jours : bornes mises à jour pendant la saisie
        (function () {
            const debut = document.querySelector('[name="debut"]');
            const fin = document.querySelector('[name="fin"]');
            const local = d => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);

            function bornes() {
                if (!debut.value) return;
                const d = new Date(debut.value);
                fin.min = local(new Date(d.getTime() + 60000));
                fin.max = local(new Date(d.getTime() + 7 * 24 * 3600 * 1000));
                if (fin.dataset.touche) fin.dispatchEvent(new Event('change'));
            }
            debut.addEventListener('change', bornes);
            bornes();
        })();
    </script>
@endpush
