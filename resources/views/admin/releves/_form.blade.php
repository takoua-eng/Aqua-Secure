@if ($errors->any())
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle me-1"></i> Veuillez corriger les champs en rouge.
    </div>
@endif

@if (empty($capteurs))
    <div class="alert alert-warning">
        <i class="ti ti-info-circle me-1"></i> Aucun capteur en service.
        <a href="{{ route('admin.capteurs.create') }}" class="alert-link">Installez d'abord un capteur</a>.
    </div>
@endif

<div class="row">
    <div class="col-md-6">
        <x-select name="capteur_id" label="Capteur" icon="wifi" :options="$capteurs"
                  :value="$releve->capteur_id" required
                  placeholder="— Choisir un capteur —"
                  data-msg-required="Choisissez le capteur."
                  valid-message="" />
        {{-- Rempli en direct selon le capteur choisi --}}
        <div id="info-seuils" class="small text-secondary mt-n2 mb-3"></div>
    </div>

    <div class="col-md-3">
        <x-input name="valeur" type="number" step="any" label="Valeur mesurée" icon="ruler-measure"
                 :value="$releve->valeur" required placeholder="Ex : 4.25"
                 data-msg-required="La valeur mesurée est obligatoire."
                 valid-message="" />
        {{-- Badge Normal / Hors seuil calculé pendant la frappe --}}
        <div id="etat-valeur" class="mt-n2 mb-3"></div>
    </div>

    <div class="col-md-3">
        <x-input name="date_releve" type="datetime-local" label="Date et heure" icon="clock"
                 :value="$releve->date_releve?->format('Y-m-d\TH:i')" required
                 :max="now()->format('Y-m-d\TH:i')"
                 data-msg-required="La date et l'heure du relevé sont obligatoires."
                 data-msg-max="Le relevé ne peut pas être dans le futur."
                 valid-message="" />
    </div>

    <div class="col-12">
        <x-input name="remarque" label="Remarque du technicien" icon="message" :value="$releve->remarque"
                 maxlength="255" placeholder="Facultatif — ex : contrôle après réparation de la vanne"
                 data-msg-maxlength="La remarque ne peut pas dépasser 255 caractères."
                 valid-message="" />
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const infos = @json($infosCapteurs);
        const capteur = document.getElementById('champ-capteur_id');
        const valeur = document.getElementById('champ-valeur');
        const infoSeuils = document.getElementById('info-seuils');
        const etat = document.getElementById('etat-valeur');

        function nombre(v) {
            return Number(v).toLocaleString('fr-FR', { maximumFractionDigits: 2 });
        }

        function mettreAJour() {
            const c = infos[capteur.value];

            // Plage physique du capteur : valeur impossible = erreur (rouge), en dehors des seuils = simple alerte
            if (c) {
                valeur.min = c.limMin;
                valeur.max = c.limMax;
                valeur.dataset.msgMin = c.limMin === 0
                    ? `Une mesure de ${c.type.toLowerCase()} ne peut pas être négative.`
                    : `Valeur impossible : minimum ${c.limMin} ${c.unite}.`;
                valeur.dataset.msgMax = `Valeur impossible : un capteur de ${c.type.toLowerCase()} mesure au plus ${c.limMax} ${c.unite}.`;
            }
            const decimales = (valeur.value.split('.')[1] || '').length;
            valeur.setCustomValidity(decimales > 3 ? 'Au plus 3 chiffres après la virgule.' : '');

            // Seuils du capteur choisi
            infoSeuils.innerHTML = c ? '<i class="ti ti-info-circle"></i> Seuils normaux : ' + c.plage : '';

            // Normal / Hors seuil selon la valeur saisie
            if (!c || valeur.value === '' || isNaN(parseFloat(valeur.value)) || !valeur.checkValidity()) {
                etat.innerHTML = '';
                return;
            }
            const v = parseFloat(valeur.value);
            if (v < c.min) {
                etat.innerHTML = '<span class="badge bg-red-lt"><i class="ti ti-arrow-down"></i> Hors seuil : trop bas</span>';
            } else if (v > c.max) {
                etat.innerHTML = '<span class="badge bg-red-lt"><i class="ti ti-arrow-up"></i> Hors seuil : trop haut</span>';
            } else {
                etat.innerHTML = '<span class="badge bg-green-lt"><i class="ti ti-check"></i> Normal (' + nombre(v) + ' ' + c.unite + ')</span>';
            }
        }

        capteur?.addEventListener('change', function () {
            mettreAJour();
            if (valeur.value !== '') valeur.dispatchEvent(new Event('change'));   // réaffiche rouge/vert
        });
        valeur?.addEventListener('input', function () {
            this.dataset.touche = '1';   // rouge/vert dès la première frappe
            mettreAJour();
        });

        // Clavier : pas de - + e E (le "-" n'est accepté que pour une température)
        valeur?.addEventListener('keydown', function (e) {
            const c = infos[capteur.value];
            const negatifAutorise = c && c.limMin < 0;
            if (['e', 'E', '+'].includes(e.key)) {
                e.preventDefault();
            } else if (e.key === '-' && !negatifAutorise) {
                e.preventDefault();
                // Touche refusée : le champ passe en rouge avec l'explication
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                const retour = this.form.querySelector('[data-feedback-for="valeur"]');
                retour.textContent = c
                    ? `Une mesure de ${c.type.toLowerCase()} ne peut pas être négative.`
                    : "Choisissez d'abord le capteur.";
                retour.classList.add('d-block');
            }
        });

        mettreAJour();
    })();
</script>
@endpush