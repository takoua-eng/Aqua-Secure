@if ($errors->any())
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle me-1"></i> Veuillez corriger les champs en rouge.
    </div>
@endif

<div class="row">
    <div class="col-md-6">
        <x-input name="nom" label="Nom de la zone" icon="map-pin" :value="$zone->nom" required
                 placeholder="Ex : Cité El Khadra"
                 minlength="2" maxlength="100" pattern="[A-Za-zÀ-ÿ0-9' \-]+"
                 data-msg-required="Le nom de la zone est obligatoire."
                 data-msg-minlength="Le nom doit contenir au moins 2 caractères."
                 data-msg-maxlength="Le nom ne peut pas dépasser 100 caractères."
                 data-msg-pattern="Lettres, chiffres, espaces, tirets et apostrophes uniquement."
                 valid-message="Nom valide." />
    </div>

    <div class="col-md-3">
        {{-- Commune choisie dans la liste (config/communes.php), avec recherche --}}
        <x-select name="commune" label="Commune" :options="\App\Models\Zone::communesParGouvernorat()"
                  :value="$zone->commune" required
                  placeholder="Rechercher une commune…"
                  data-recherche="Rechercher une commune…"
                  data-msg-required="Choisissez une commune dans la liste."
                  valid-message="Commune sélectionnée." />
    </div>

    <div class="col-md-3">
        <x-input name="population" type="number" label="Population desservie" icon="users" :value="$zone->population" required
                 min="0" max="5000000" step="1" placeholder="Ex : 25000"
                 hint="Nombre d'habitants approvisionnés par la zone."
                 data-msg-required="La population desservie est obligatoire."
                 data-msg-min="La population ne peut pas être négative."
                 data-msg-max="La population ne peut pas dépasser 5 000 000 habitants."
                 data-msg-type="Saisissez un nombre entier d'habitants."
                 valid-message="Population valide." />
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
    <style>
        /* États de validation sur la liste avec recherche (mêmes couleurs que Tabler) */
        .ts-wrapper.is-invalid .ts-control { border-color: var(--tblr-danger); }
        .ts-wrapper.is-valid .ts-control { border-color: var(--tblr-success); }
        .ts-dropdown .optgroup-header { font-weight: 600; color: var(--tblr-secondary); }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script src="{{ asset('js/select-recherche.js') }}"></script>
@endpush
