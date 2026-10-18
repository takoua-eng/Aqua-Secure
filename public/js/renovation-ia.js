/*
 * Binôme 3 — affichage des analyses IA (ou du mode secours) renvoyées par le serveur en JSON.
 * Tout texte est échappé : une réponse de l'IA ne peut jamais injecter de HTML dans la page.
 */
window.RenovationIA = (function () {
    const echapper = t => String(t ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function liste(elements, icone, couleur) {
        if (!elements || !elements.length) return '<div class="text-secondary small">—</div>';
        return '<ul class="list-unstyled mb-0">' + elements.map(t =>
            `<li class="d-flex gap-2 mb-1"><i class="ti ti-${icone} text-${couleur} mt-1"></i><span>${echapper(t)}</span></li>`
        ).join('') + '</ul>';
    }

    // Bandeau d'origine : interprétation IA ou mode secours, toujours visible
    function entete(r) {
        if (r.origine === 'ia') {
            return `<div class="bg-pink-lt rounded px-3 py-2 mb-3 d-flex align-items-start gap-2">
                <i class="ti ti-sparkles mt-1"></i>
                <div><strong>Interprétation IA</strong> (${echapper(r.modele)}, ${echapper(r.genereLe)}).
                Elle commente les résultats calculés, ne modifie aucune donnée et doit être vérifiée par un gestionnaire.</div></div>`;
        }
        return `<div class="bg-secondary-lt rounded px-3 py-2 mb-3 d-flex align-items-start gap-2">
            <i class="ti ti-list-check mt-1"></i>
            <div><strong>Mode secours</strong> — analyse rédigée par des règles fixes, sans IA (${echapper(r.genereLe)}).
            <div class="small">${echapper(r.avertissement || '')}</div></div></div>`;
    }

    function scenarios(r, noms) {
        const a = r.analyse;
        const nom = cle => echapper((noms && noms[cle]) || cle);
        let html = entete(r);
        html += `<p class="mb-3">${echapper(a.synthese)}</p>`;
        html += `<div class="card card-sm bg-primary-lt mb-3"><div class="card-body">
            <div class="text-uppercase small fw-bold mb-1"><i class="ti ti-thumb-up me-1"></i>Recommandation : ${nom(a.recommandation.strategie)}</div>
            <div>${echapper(a.recommandation.justification)}</div></div></div>`;
        html += '<div class="row g-2 mb-3">';
        (a.strategies || []).forEach(s => {
            html += `<div class="col-md-6"><div class="border rounded p-2 h-100">
                <div class="fw-bold mb-1">${nom(s.strategie)}</div>
                <div class="small text-secondary">Points forts</div>${liste(s.points_forts, 'check', 'green')}
                <div class="small text-secondary mt-1">Compromis</div>${liste(s.compromis, 'arrows-left-right', 'orange')}
                <div class="small text-secondary mt-1">Risques</div>${liste(s.risques, 'alert-triangle', 'red')}
            </div></div>`;
        });
        html += '</div><div class="row g-3">';
        html += `<div class="col-md-6"><div class="fw-bold mb-1"><i class="ti ti-bulb me-1"></i>Améliorations proposées</div>${liste(a.ameliorations, 'arrow-right', 'blue')}</div>`;
        html += `<div class="col-md-6"><div class="fw-bold mb-1"><i class="ti ti-database-off me-1"></i>Données manquantes</div>${liste(a.donnees_manquantes, 'point', 'secondary')}</div>`;
        return html + '</div>';
    }

    function alertes(r) {
        const a = r.analyse;
        let html = entete(r) + `<p>${echapper(a.synthese)}</p>`;
        if (a.actions && a.actions.length) {
            html += '<div class="fw-bold mb-1">Mesures correctives proposées</div><ul class="list-unstyled mb-2">'
                + a.actions.map(x => `<li class="d-flex gap-2 mb-1"><span class="badge bg-secondary-lt font-monospace">${echapper(x.code)}</span><span>${echapper(x.action)}</span></li>`).join('')
                + '</ul>';
        }
        if (a.donnees_manquantes && a.donnees_manquantes.length) {
            html += '<div class="fw-bold mb-1">Données manquantes</div>' + liste(a.donnees_manquantes, 'point', 'secondary');
        }
        return html;
    }

    // Requête POST JSON avec le jeton CSRF ; renvoie [ok, donnees]
    async function envoyer(url, corps) {
        const reponse = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(corps || {}),
        });
        const donnees = await reponse.json().catch(() => ({}));
        if (!reponse.ok) {
            const premier = donnees.errors ? Object.values(donnees.errors)[0][0] : null;
            const message = premier || donnees.erreur || donnees.message
                || (reponse.status === 429 ? 'Trop de demandes : réessayez dans une minute.' : 'La demande a échoué.');
            return [false, message];
        }
        return [true, donnees];
    }

    function attente(cible, texte) {
        cible.innerHTML = `<div class="d-flex align-items-center gap-2 py-3 text-secondary">
            <span class="spinner-border spinner-border-sm"></span> ${echapper(texte)}</div>`;
    }

    function erreur(cible, message) {
        cible.innerHTML = `<div class="alert alert-warning mb-0"><i class="ti ti-alert-circle me-1"></i>${echapper(message)}</div>`;
    }

    return { echapper, scenarios, alertes, envoyer, attente, erreur };
})();
