/*
 * Demande le rapport IA d'une zone au serveur et l'affiche dans un élément de la page.
 * analyserZone(url, cible, bouton) — url : route admin.zones.analyse-ia, cible : élément où écrire le rapport.
 */
window.analyserZone = async function (url, cible, bouton) {
    const jeton = document.querySelector('meta[name="csrf-token"]').content;
    const echapper = texte => String(texte).replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    if (bouton) bouton.disabled = true;
    cible.innerHTML = `<div class="text-secondary d-flex align-items-center gap-2 py-3">
            <span class="spinner-border spinner-border-sm"></span> L'IA analyse la zone… (jusqu'à une minute)
        </div>`;

    try {
        const reponse = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': jeton, 'Accept': 'application/json' },
        });
        const donnees = await reponse.json().catch(() => ({}));

        if (!reponse.ok) {
            const message = donnees.erreur
                || (reponse.status === 429 ? 'Trop de demandes : réessayez dans une minute.' : "L'analyse a échoué. Réessayez plus tard.");
            cible.innerHTML = `<div class="alert alert-warning mb-0"><i class="ti ti-alert-circle me-1"></i>${echapper(message)}</div>`;
            return;
        }

        // Le HTML est produit par le serveur à partir du Markdown de l'IA, avec tout HTML brut échappé
        const origine = donnees.demo
            ? `<i class="ti ti-info-circle me-1"></i>Mode démo : rapport rédigé automatiquement à partir du score de risque, sans IA (aucune clé API configurée). Le ${echapper(donnees.genereLe)}.`
            : `<i class="ti ti-sparkles me-1"></i>Généré par IA (${echapper(donnees.modele)}) le ${echapper(donnees.genereLe)}. À vérifier par un gestionnaire.`;
        cible.innerHTML = `<div class="rapport-ia">${donnees.html}</div>
            <div class="small text-secondary mt-2">${origine}</div>`;
    } catch (e) {
        cible.innerHTML = '<div class="alert alert-warning mb-0">Connexion au serveur impossible.</div>';
    } finally {
        if (bouton) bouton.disabled = false;
    }
};
