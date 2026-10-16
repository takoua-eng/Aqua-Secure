@if ($errors->any())
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle me-1"></i> Veuillez corriger les champs en rouge.
    </div>
@endif

@if (empty($reseaux))
    {{-- Un point de prélèvement doit appartenir à un réseau : on prévient s'il n'y en a aucun --}}
    <div class="alert alert-warning">
        <i class="ti ti-info-circle me-1"></i> Aucun réseau n'existe encore.
        <a href="{{ route('admin.reseaux.create') }}" class="alert-link">Créez d'abord un réseau</a>.
    </div>
@endif

<div class="row">
    <div class="col-md-4">
        <x-input name="code" label="Code" icon="barcode" :value="$point->code" required
                 placeholder="Ex : PP-TN-012"
                 minlength="3" maxlength="30" pattern="[A-Za-z0-9]+(-[A-Za-z0-9]+)*"
                 oninput="this.value = this.value.toUpperCase()"
                 hint="Lettres, chiffres et tirets. Enregistré en majuscules."
                 data-msg-required="Le code est obligatoire."
                 data-msg-minlength="Le code doit contenir au moins 3 caractères."
                 data-msg-maxlength="Le code ne peut pas dépasser 30 caractères."
                 data-msg-pattern="Lettres, chiffres et tirets uniquement (ex : PP-TN-012)."
                 valid-message="Code valide." />
    </div>

    <div class="col-md-8">
        <x-input name="nom" label="Nom du point" icon="droplet" :value="$point->nom" required
                 placeholder="Ex : Fontaine publique Bab Saadoun"
                 minlength="2" maxlength="100"
                 data-msg-required="Le nom du point est obligatoire."
                 data-msg-minlength="Le nom doit contenir au moins 2 caractères."
                 data-msg-maxlength="Le nom ne peut pas dépasser 100 caractères."
                 valid-message="Nom valide." />
    </div>

    <div class="col-md-6">
        <x-select name="reseau_id" label="Réseau" icon="share" :options="$reseaux"
                  :value="$point->reseau_id" required
                  placeholder="— Choisir un réseau —"
                  data-msg-required="Choisissez le réseau de rattachement."
                  valid-message="Réseau sélectionné." />
    </div>

    <div class="col-md-6">
        <x-select name="type_point" label="Type de point" icon="category" :options="\App\Models\PointPrelevement::TYPES"
                  :value="$point->type_point" required
                  placeholder="— Choisir un type —"
                  data-msg-required="Choisissez le type de point."
                  valid-message="Type sélectionné." />
    </div>

    <div class="col-md-8">
        <x-input name="adresse" label="Adresse" icon="map-pin" :value="$point->adresse" required
                 placeholder="Ex : Place Bab Saadoun, Tunis"
                 minlength="3" maxlength="255"
                 data-msg-required="L'adresse est obligatoire."
                 data-msg-minlength="L'adresse doit contenir au moins 3 caractères."
                 data-msg-maxlength="L'adresse ne peut pas dépasser 255 caractères."
                 valid-message="Adresse valide." />
    </div>

    <div class="col-md-4">
        <label class="form-label">État</label>
        {{-- Champ caché : une case décochée envoie quand même "0" --}}
        <input type="hidden" name="actif" value="0">
        <label class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="actif" value="1" @checked(old('actif', $point->actif))>
            <span class="form-check-label">En service</span>
        </label>
        <small class="form-hint">Un point hors service garde son historique mais ne reçoit plus de nouvelles analyses.</small>
    </div>
</div>

{{-- Position exacte (facultative) : un clic sur la carte remplit latitude et longitude --}}
<div class="row">
    <div class="col-lg-8 mb-3">
        <label class="form-label">Position sur la carte <span class="text-secondary fw-normal">(facultatif)</span></label>
        <div id="carte-position" class="rounded border" style="height: 280px"></div>
        <small class="form-hint">Cliquez sur la carte pour placer le point, ou faites glisser le repère.</small>
    </div>
    <div class="col-lg-4">
        <x-input name="latitude" type="number" label="Latitude" icon="world-latitude" :value="$point->latitude"
                 step="any" min="-90" max="90" placeholder="Ex : 36.80650"
                 data-msg-min="La latitude doit être comprise entre -90 et 90."
                 data-msg-max="La latitude doit être comprise entre -90 et 90."
                 valid-message="Latitude valide." />
        <x-input name="longitude" type="number" label="Longitude" icon="world-longitude" :value="$point->longitude"
                 step="any" min="-180" max="180" placeholder="Ex : 10.18150"
                 data-msg-min="La longitude doit être comprise entre -180 et 180."
                 data-msg-max="La longitude doit être comprise entre -180 et 180."
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
            const positions = @json($positionsReseaux);
            const champLat = document.querySelector('[name="latitude"]');
            const champLng = document.querySelector('[name="longitude"]');
            const champReseau = document.querySelector('[name="reseau_id"]');

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

            // Choix du réseau : on centre la carte sur lui (si aucune position n'est encore placée)
            champReseau.addEventListener('change', () => {
                const position = positions[champReseau.value];
                if (position && !repere) carte.setView(position, 15);
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
            } else if (positions[champReseau.value]) {
                carte.setView(positions[champReseau.value], 15);
            }
        })();
    </script>
@endpush
