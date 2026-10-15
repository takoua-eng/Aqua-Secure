/*
 * Contrôle de saisie en direct pour tous les formulaires marqués data-validate.
 * Règles lues sur le champ : required, type="email", minlength, maxlength, pattern, min, max,
 * et data-match="autreChamp" (confirmation du mot de passe).
 * Messages personnalisés : data-msg-required, data-msg-type, data-msg-minlength,
 * data-msg-maxlength, data-msg-pattern, data-msg-min, data-msg-max, data-msg-match
 */
(function () {
    function message(champ) {
        const v = champ.validity;
        const d = champ.dataset;

        if (v.valueMissing)    return d.msgRequired  || 'Ce champ est obligatoire.';
        if (v.typeMismatch)    return d.msgType      || (champ.type === 'email' ? "L'adresse email n'est pas valide." : 'Format invalide.');
        if (v.tooShort)        return d.msgMinlength || `Au moins ${champ.minLength} caractères (actuellement ${champ.value.length}).`;
        if (v.tooLong)         return d.msgMaxlength || `Au plus ${champ.maxLength} caractères.`;
        if (v.patternMismatch) return d.msgPattern   || 'Le format saisi est incorrect.';
        if (v.rangeUnderflow)  return d.msgMin       || `La valeur doit être au moins ${champ.min}.`;
        if (v.rangeOverflow)   return d.msgMax       || `La valeur doit être au plus ${champ.max}.`;
        return champ.validationMessage;
    }

    // data-match : le champ doit être identique à un autre
    function verifierCorrespondance(champ) {
        if (!champ.dataset.match) return;
        const autre = champ.form.querySelector(`[name="${champ.dataset.match}"]`);
        const different = autre && champ.value !== autre.value;
        champ.setCustomValidity(different ? (champ.dataset.msgMatch || 'Les deux valeurs ne correspondent pas.') : '');
    }

    // Met le champ en rouge ou en vert et affiche le bon message
    function afficher(champ) {
        verifierCorrespondance(champ);

        const form = champ.form;
        const erreur = form.querySelector(`[data-feedback-for="${champ.name}"]`);
        const succes = form.querySelector(`[data-valid-for="${champ.name}"]`);

        // Champ facultatif laissé vide : ni rouge ni vert
        if (!champ.required && champ.value === '' && !champ.validity.customError) {
            champ.classList.remove('is-valid', 'is-invalid');
            erreur?.classList.remove('d-block');
            succes?.classList.remove('d-block');
            return true;
        }

        const valide = champ.checkValidity();
        champ.classList.toggle('is-valid', valide);
        champ.classList.toggle('is-invalid', !valide);

        if (erreur) {
            erreur.textContent = valide ? '' : message(champ);
            erreur.classList.toggle('d-block', !valide);
        }
        succes?.classList.toggle('d-block', valide);

        return valide;
    }

    function champsDe(form) {
        return [...form.querySelectorAll('input, select, textarea')]
            .filter(ch => ch.name && ch.type !== 'hidden' && !ch.disabled);
    }

    // Vérifie tout le formulaire (utilisé aussi par la fenêtre de confirmation)
    window.validerFormulaire = function (form) {
        let premierInvalide = null;
        champsDe(form).forEach(ch => {
            ch.dataset.touche = '1';
            if (!afficher(ch) && !premierInvalide) premierInvalide = ch;
        });
        premierInvalide?.focus();
        return premierInvalide === null;
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-validate]').forEach(form => {
            // On remplace les bulles du navigateur par nos messages Tabler
            form.setAttribute('novalidate', '');

            champsDe(form).forEach(ch => {
                ch.addEventListener('blur', () => { ch.dataset.touche = '1'; afficher(ch); });
                ch.addEventListener('change', () => { ch.dataset.touche = '1'; afficher(ch); });
                ch.addEventListener('input', () => {
                    if (ch.dataset.touche) afficher(ch);
                    // Le mot de passe change : on revérifie sa confirmation
                    form.querySelectorAll(`[data-match="${ch.name}"]`).forEach(c => {
                        if (c.dataset.touche) afficher(c);
                    });
                });
            });

            // Envoi classique (sans fenêtre de confirmation)
            form.addEventListener('submit', function (e) {
                if (!window.validerFormulaire(form)) e.preventDefault();
            });
        });
    });
})();