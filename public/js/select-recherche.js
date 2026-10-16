/*
 * Listes déroulantes avec recherche (Tom Select) pour les <select data-recherche>.
 * Le <select> d'origine reste dans le formulaire : validation.js continue de le contrôler,
 * et on recopie son état rouge / vert sur la zone de recherche visible.
 */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('select[data-recherche]').forEach(select => {
            const ts = new TomSelect(select, {
                create: false,          // uniquement les valeurs de la liste
                maxOptions: null,       // afficher toutes les correspondances
                allowEmptyOption: false,
                placeholder: select.dataset.recherche || 'Rechercher…',
                render: {
                    no_results: () => '<div class="no-results">Aucun résultat</div>',
                },
            });

            const synchroniser = () => {
                ['is-valid', 'is-invalid'].forEach(classe =>
                    ts.wrapper.classList.toggle(classe, select.classList.contains(classe)));
            };
            new MutationObserver(synchroniser).observe(select, { attributes: true, attributeFilter: ['class'] });
            synchroniser();

            // Quitter la recherche = champ "touché" : on lance le contrôle comme pour un champ normal
            ts.on('blur', () => select.dispatchEvent(new Event('blur')));
        });
    });
})();
