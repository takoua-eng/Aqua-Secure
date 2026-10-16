@if ($errors->any())
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle me-1"></i> Veuillez corriger les champs en rouge.
    </div>
@endif

@if (empty($reseaux))
    {{-- Un capteur doit être installé sur un réseau : on prévient s'il n'y en a aucun --}}
    <div class="alert alert-warning">
        <i class="ti ti-info-circle me-1"></i> Aucun réseau n'existe encore.
        <a href="{{ route('admin.reseaux.create') }}" class="alert-link">Créez d'abord un réseau</a>.
    </div>
@endif

<h3 class="card-title">Identification</h3>
<div class="row">
    <div class="col-md-4">
        <x-input name="code" label="Code" icon="barcode" :value="$capteur->code" required
                 placeholder="Ex : CAP-TN-001"
                 maxlength="11" :pattern="\App\Models\Capteur::FORMAT_CODE"
                 autocomplete="off"
                 hint="Format : CAP-XX-000 (CAP, 2 ou 3 lettres, 3 ou 4 chiffres)."
                 data-msg-required="Le code est obligatoire."
                 data-msg-pattern="Format attendu : CAP-XX-000 (ex : CAP-TN-001)."
                 valid-message="Code valide." />
    </div>

    <div class="col-md-4">
        <x-input name="modele" label="Modèle / fabricant" icon="cpu" :value="$capteur->modele" required
                 placeholder="Ex : Siemens SITRANS P320"
                 minlength="2" maxlength="100" pattern="(?=.*[A-Za-zÀ-ÿ])[A-Za-zÀ-ÿ0-9 .+\-\/\(\)]+"
                 data-msg-required="Le modèle est obligatoire."
                 data-msg-minlength="Le modèle doit contenir au moins 2 caractères."
                 data-msg-pattern="Lettres obligatoires ; seuls les chiffres, espaces et . + - / ( ) sont permis en plus."
                 valid-message="Modèle valide." />
    </div>

    <div class="col-md-4">
        <x-select name="reseau_id" label="Réseau équipé" icon="share" :options="$reseaux"
                  :value="$capteur->reseau_id" required
                  placeholder="— Choisir un réseau —"
                  data-msg-required="Choisissez le réseau équipé."
                  valid-message="Réseau sélectionné." />
    </div>
</div>

<h3 class="card-title mt-2">Mesure</h3>
<div class="row">
    <div class="col-md-4">
        <x-select name="type_mesure" label="Type de mesure" icon="activity" :options="\App\Models\Capteur::TYPES_MESURE"
                  :value="$capteur->type_mesure" required
                  placeholder="— Choisir un type —"
                  data-msg-required="Choisissez le type de mesure."
                  valid-message="Type sélectionné." />
    </div>

    {{-- L'unité n'est pas saisie : elle dépend du type de mesure (affichage seulement) --}}
    <div class="col-md-2">
        <div class="mb-3">
            <label class="form-label" for="unite-affichee">Unité</label>
            <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-ruler-2"></i></span>
                <input type="text" id="unite-affichee" class="form-control"
                       value="{{ \App\Models\Capteur::UNITES[old('type_mesure', $capteur->type_mesure)] ?? '—' }}" disabled>
            </div>
            <small class="form-hint">Automatique.</small>
        </div>
    </div>

    <div class="col-md-3">
        <x-input name="seuil_min" type="number" step="any" label="Seuil minimum" icon="arrow-bar-to-down"
                 :value="$capteur->seuil_min" required placeholder="Ex : 2"
                 hint="Choisissez d'abord le type de mesure."
                 data-msg-required="Le seuil minimum est obligatoire."
                 valid-message="Seuil valide." />
    </div>

    <div class="col-md-3">
        <x-input name="seuil_max" type="number" step="any" label="Seuil maximum" icon="arrow-bar-to-up"
                 :value="$capteur->seuil_max" required placeholder="Ex : 6"
                 hint="Doit être supérieur au minimum."
                 data-msg-required="Le seuil maximum est obligatoire."
                 valid-message="Seuil valide." />
    </div>
</div>

<h3 class="card-title mt-2">Installation</h3>
<div class="row">
    <div class="col-md-4">
        <x-input name="date_installation" type="date" label="Date d'installation" icon="calendar"
                 :value="$capteur->date_installation?->format('Y-m-d')" required
                 min="1990-01-01" :max="now()->format('Y-m-d')"
                 data-msg-required="La date d'installation est obligatoire."
                 data-msg-min="La date ne peut pas être avant 1990."
                 data-msg-max="La date d'installation ne peut pas être dans le futur."
                 valid-message="Date valide." />
    </div>

    <div class="col-md-4">
        <x-select name="statut" label="Statut" icon="toggle-right" :options="\App\Models\Capteur::STATUTS"
                  :value="$capteur->statut" required
                  placeholder="— Choisir un statut —"
                  data-msg-required="Choisissez le statut."
                  valid-message="Statut sélectionné." />
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const unites = @json(\App\Models\Capteur::UNITES);
        const limites = @json(\App\Models\Capteur::LIMITES);
        const type = document.getElementById('champ-type_mesure');
        const unite = document.getElementById('unite-affichee');
        const code = document.getElementById('champ-code');
        const min = document.getElementById('champ-seuil_min');
        const max = document.getElementById('champ-seuil_max');
        const aideMin = min.closest('.mb-3').querySelector('.form-hint');

        // Affiche l'état rouge/vert dès la première frappe (validation.js attend sinon que l'on quitte le champ)
        const enDirect = champ => champ.dataset.touche = '1';
        code.form.querySelectorAll('input').forEach(ch => ch.addEventListener('input', () => enDirect(ch)));

        // Touche refusée : le champ passe en rouge avec l'explication
        function refuser(champ, texte) {
            champ.classList.remove('is-valid');
            champ.classList.add('is-invalid');
            const retour = champ.form.querySelector(`[data-feedback-for="${champ.name}"]`);
            retour.textContent = texte;
            retour.classList.add('d-block');
        }

        // 1. Code : majuscules automatiques, seuls lettres, chiffres et tirets sont acceptés au clavier
        code?.addEventListener('input', function () {
            enDirect(this);
            // espace ou _ deviennent un tiret, les autres caractères sont supprimés
            this.value = this.value.toUpperCase().replace(/[\s_]+/g, '-').replace(/[^A-Z0-9-]/g, '');
        });

        // 2. Seuils : interdit les touches - + e E (sauf "-" pour une température)
        [min, max].forEach(champ => champ?.addEventListener('keydown', function (e) {
            const negatifAutorise = type.value === 'temperature';
            if (['e', 'E', '+'].includes(e.key)) {
                e.preventDefault();
            } else if (e.key === '-' && !negatifAutorise) {
                e.preventDefault();
                refuser(this, type.value
                    ? 'Valeur négative impossible : seule une température peut être négative.'
                    : "Choisissez d'abord le type de mesure.");
            }
        }));

        // 3. Le type de mesure fixe l'unité et la plage autorisée des seuils (ex : 0 – 25 bar)
        function appliquerType() {
            unite.value = unites[type.value] ?? '—';
            const lim = limites[type.value];

            [min, max].forEach(champ => {
                if (lim) {
                    champ.min = lim[0];
                    champ.max = lim[1];
                    champ.dataset.msgMin = lim[0] === 0
                        ? 'Un seuil ne peut pas être négatif pour ce type de mesure.'
                        : `Le seuil doit être au moins ${lim[0]} ${unites[type.value]}.`;
                    champ.dataset.msgMax = `Le seuil ne peut pas dépasser ${lim[1]} ${unites[type.value]}.`;
                } else {
                    champ.removeAttribute('min');
                    champ.removeAttribute('max');
                }
            });
            aideMin.textContent = lim
                ? `Plage autorisée : ${lim[0]} à ${lim[1]} ${unites[type.value]}.`
                : "Choisissez d'abord le type de mesure.";

            verifier();
            [min, max].forEach(champ => champ.value !== '' && champ.dispatchEvent(new Event('change')));
        }

        // 4. Règles en plus : 2 décimales au plus, maximum > minimum
        function verifier() {
            [min, max].forEach(champ => {
                const decimales = (champ.value.split('.')[1] || '').length;
                champ.setCustomValidity(decimales > 2 ? 'Au plus 2 chiffres après la virgule.' : '');
            });
            if (!max.validationMessage && min.value !== '' && max.value !== ''
                && parseFloat(max.value) <= parseFloat(min.value)) {
                max.setCustomValidity('Le seuil maximum doit être supérieur au seuil minimum.');
            }
        }

        // Ces écouteurs sont ajoutés avant ceux de validation.js : les règles sont vérifiées avant l'affichage rouge/vert
        type?.addEventListener('change', appliquerType);
        min?.addEventListener('input', function () {
            enDirect(this);
            verifier();
            if (max.value !== '') { enDirect(max); max.dispatchEvent(new Event('input')); }
        });
        max?.addEventListener('input', function () { enDirect(this); verifier(); });
        appliquerType();
    })();
</script>
@endpush