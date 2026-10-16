@if ($errors->any())
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle me-1"></i> Veuillez corriger les champs en rouge.
    </div>
@endif

@if (empty($zones))
    {{-- Un réseau doit appartenir à une zone : on prévient s'il n'y en a aucune --}}
    <div class="alert alert-warning">
        <i class="ti ti-info-circle me-1"></i> Aucune zone n'existe encore.
        <a href="{{ route('admin.zones.create') }}" class="alert-link">Créez d'abord une zone</a>.
    </div>
@endif

<div class="row">
    <div class="col-md-4">
        <x-input name="code" label="Code" icon="barcode" :value="$reseau->code" required
                 placeholder="Ex : CAN-TUN-001"
                 minlength="3" maxlength="30" pattern="[A-Za-z0-9]+(-[A-Za-z0-9]+)*"
                 oninput="this.value = this.value.toUpperCase()"
                 hint="Lettres, chiffres et tirets. Enregistré en majuscules."
                 data-msg-required="Le code est obligatoire."
                 data-msg-minlength="Le code doit contenir au moins 3 caractères."
                 data-msg-maxlength="Le code ne peut pas dépasser 30 caractères."
                 data-msg-pattern="Lettres, chiffres et tirets uniquement (ex : CAN-TUN-001)."
                 valid-message="Code valide." />
    </div>

    <div class="col-md-4">
        <x-select name="zone_id" label="Zone" icon="map" :options="$zones"
                  :value="$reseau->zone_id" required
                  placeholder="— Choisir une zone —"
                  data-msg-required="Choisissez la zone de rattachement."
                  valid-message="Zone sélectionnée." />
    </div>

    <div class="col-md-4">
        <x-select name="type" label="Type" icon="category" :options="\App\Models\ReseauEau::TYPES"
                  :value="$reseau->type" required
                  placeholder="— Choisir un type —"
                  data-msg-required="Choisissez le type d'élément."
                  valid-message="Type sélectionné." />
    </div>

    <div class="col-md-4">
        <x-select name="materiau" label="Matériau" icon="box" :options="\App\Models\ReseauEau::MATERIAUX"
                  :value="$reseau->materiau" required
                  placeholder="— Choisir un matériau —"
                  data-msg-required="Choisissez le matériau."
                  valid-message="Matériau sélectionné." />
    </div>

    <div class="col-md-4">
        <x-input name="date_mise_en_service" type="date" label="Date de mise en service" icon="calendar"
                 :value="$reseau->date_mise_en_service?->format('Y-m-d')" required
                 min="1900-01-01" :max="now()->format('Y-m-d')"
                 data-msg-required="La date de mise en service est obligatoire."
                 data-msg-min="La date ne peut pas être avant 1900."
                 data-msg-max="La date ne peut pas être dans le futur."
                 valid-message="Date valide." />
    </div>

    <div class="col-md-4">
        <x-select name="etat" label="État" icon="heartbeat" :options="\App\Models\ReseauEau::ETATS"
                  :value="$reseau->etat" required
                  placeholder="— Choisir un état —"
                  data-msg-required="Choisissez l'état de l'élément."
                  valid-message="État sélectionné." />
    </div>
</div>

{{-- Position exacte (facultative) : un clic sur la carte remplit latitude et longitude --}}
<div class="row">
    <div class="col-lg-8 mb-3">
        <label class="form-label">Position sur la carte <span class="text-secondary fw-normal">(facultatif)</span></label>
        <div id="carte-position" class="rounded border" style="height: 280px"></div>
        <small class="form-hint">Cliquez sur la carte pour placer l'élément, ou faites glisser le repère.</small>
    </div>
    <div class="col-lg-4">
        <x-input name="latitude" type="number" label="Latitude" icon="world-latitude" :value="$reseau->latitude"
                 step="any" min="30" max="38" placeholder="Ex : 36.80650"
                 data-msg-min="La latitude doit être comprise entre 30 et 38 (Tunisie)."
                 data-msg-max="La latitude doit être comprise entre 30 et 38 (Tunisie)."
                 valid-message="Latitude valide." />
        <x-input name="longitude" type="number" label="Longitude" icon="world-longitude" :value="$reseau->longitude"
                 step="any" min="7" max="12" placeholder="Ex : 10.18150"
                 data-msg-min="La longitude doit être comprise entre 7 et 12 (Tunisie)."
                 data-msg-max="La longitude doit être comprise entre 7 et 12 (Tunisie)."
                 valid-message="Longitude valide." />
        <button type="button" class="btn btn-sm btn-ghost-secondary" id="effacer-position">
            <i class="ti ti-eraser me-1"></i> Effacer la position
        </button>
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function () {
            const centres = @json($centresZones);
            const champLat = document.querySelector('[name="latitude"]');
            const champLng = document.querySelector('[name="longitude"]');
            const champZone = document.querySelector('[name="zone_id"]');

            const carte = L.map('carte-position').setView([34.8, 9.6], 6);   // Tunisie entière
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(carte);
            let repere = null;

            // Remplit les champs et relance le contrôle de saisie (validation.js)
            function ecrire(lat, lng) {
                champLat.value = lat === '' ? '' : lat.toFixed(6);
                champLng.value = lng === '' ? '' : lng.toFixed(6);
                [champLat, champLng].forEach(ch => { ch.dataset.touche = '1'; ch.dispatchEvent(new Event('change')); });
            }

            function placer(lat, lng) {
                if (!repere) {
                    repere = L.marker([lat, lng], { draggable: true }).addTo(carte);
                    repere.on('dragend', () => { const p = repere.getLatLng(); ecrire(p.lat, p.lng); });
                } else {
                    repere.setLatLng([lat, lng]);
                }
            }

            carte.on('click', e => { placer(e.latlng.lat, e.latlng.lng); ecrire(e.latlng.lat, e.latlng.lng); });

            // Saisie à la main : le repère suit
            [champLat, champLng].forEach(ch => ch.addEventListener('change', () => {
                const lat = parseFloat(champLat.value), lng = parseFloat(champLng.value);
                if (!isNaN(lat) && !isNaN(lng)) placer(lat, lng);
            }));

            // Choix de la zone : on centre la carte sur sa commune (si aucune position n'est encore placée)
            champZone.addEventListener('change', () => {
                const centre = centres[champZone.value];
                if (centre && !repere) carte.setView(centre, 14);
            });

            document.getElementById('effacer-position').addEventListener('click', () => {
                if (repere) { carte.removeLayer(repere); repere = null; }
                ecrire('', '');
            });

            // Position déjà connue (modification ou retour après erreur)
            const lat = parseFloat(champLat.value), lng = parseFloat(champLng.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                placer(lat, lng);
                carte.setView([lat, lng], 15);
            } else if (centres[champZone.value]) {
                carte.setView(centres[champZone.value], 14);
            }
        })();
    </script>
@endpush
