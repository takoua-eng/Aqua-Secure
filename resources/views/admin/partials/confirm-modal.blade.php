{{--
    Fenêtre de confirmation Tabler, partagée par toute l'application.
    Sur un bouton placé dans un <form> :
        data-bs-toggle="modal" data-bs-target="#modal-confirm"
        data-confirm-type="danger"   (rouge, suppression)  ou  "primary" (bleu, modification)
        data-confirm-title="..."  data-confirm-message="..."  data-confirm-button="..."
--}}
<div class="modal modal-blur fade" id="modal-confirm" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            <div class="modal-status bg-danger" id="modal-confirm-status"></div>

            <div class="modal-body text-center py-4">
                <i class="ti ti-alert-triangle text-danger mb-2" id="modal-confirm-icon" style="font-size: 3rem"></i>
                <h3 id="modal-confirm-title">Êtes-vous sûr ?</h3>
                <div class="text-secondary" id="modal-confirm-message">Cette action est irréversible.</div>
            </div>

            <div class="modal-footer">
                <div class="w-100">
                    <div class="row">
                        <div class="col">
                            <button type="button" class="btn w-100" data-bs-dismiss="modal">Annuler</button>
                        </div>
                        <div class="col">
                            <button type="button" class="btn btn-danger w-100" id="modal-confirm-button">Confirmer</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('modal-confirm');
        const status = document.getElementById('modal-confirm-status');
        const icone = document.getElementById('modal-confirm-icon');
        const boutonOk = document.getElementById('modal-confirm-button');
        let formulaire = null;

        // Apparence selon le type d'action
        const styles = {
            danger:  { couleur: 'danger',  icone: 'ti-alert-triangle' },
            primary: { couleur: 'primary', icone: 'ti-pencil-check' },
            success: { couleur: 'success', icone: 'ti-circle-check' },
        };

        modal.addEventListener('show.bs.modal', function (event) {
            const bouton = event.relatedTarget;
            formulaire = bouton.closest('form');

            // Contrôle de saisie : si un champ est invalide, on n'ouvre pas la fenêtre
            // et les champs fautifs passent en rouge avec leur message (validation.js)
            if (formulaire) {
                const valide = window.validerFormulaire
                    ? window.validerFormulaire(formulaire)
                    : formulaire.reportValidity();
                if (!valide) {
                    event.preventDefault();
                    return;
                }
            }

            const style = styles[bouton.dataset.confirmType] || styles.danger;

            status.className = 'modal-status bg-' + style.couleur;
            icone.className = 'ti ' + style.icone + ' text-' + style.couleur + ' mb-2';
            boutonOk.className = 'btn btn-' + style.couleur + ' w-100';
            boutonOk.disabled = false;

            document.getElementById('modal-confirm-title').textContent =
                bouton.dataset.confirmTitle || 'Êtes-vous sûr ?';
            document.getElementById('modal-confirm-message').textContent =
                bouton.dataset.confirmMessage || 'Cette action est irréversible.';
            boutonOk.textContent = bouton.dataset.confirmButton || 'Confirmer';
        });

        // Clic sur le bouton de confirmation : on envoie le formulaire
        boutonOk.addEventListener('click', function () {
            if (formulaire) {
                this.disabled = true;
                formulaire.submit();
            }
        });
    })();
</script>