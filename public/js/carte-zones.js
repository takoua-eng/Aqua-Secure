/*
 * Carte des zones (Leaflet) : un cercle par zone, coloré selon l'état le plus grave de ses réseaux.
 * Un clic sur une zone affiche la liste de ses réseaux d'eau dans le panneau à côté de la carte.
 *
 * carteZones({
 *     carte: 'id-de-la-carte', panneau: 'id-du-panneau', zones: [...],   // zones : Zone::pourCarte()
 *     badges: { bon: 'classes css', degrade: '...', hors_service: '...' },
 *     icones: { canalisation: 'classes css', reservoir: '...', station: '...' },
 *     lienReseau: '/admin/reseaux/__ID__/edit' (facultatif), lienZone: '/admin/reseaux?zone=__ID__' (facultatif),
 *     lienAnalyse: '/admin/zones/__ID__/analyse-ia' (facultatif), legende: 'id-de-la-legende' (facultatif)
 * })
 * Renvoie { changerMode('etat' | 'risque' | 'groupe') } pour recolorer les zones.
 */
window.carteZones = function (options) {
    const couleurs = { bon: '#2fb344', degrade: '#f59f00', hors_service: '#d63939' };
    const sansReseau = '#868e96';
    const panneau = document.getElementById(options.panneau);

    // Les noms viennent de la base : on les échappe avant de les mettre dans le HTML
    const echapper = texte => String(texte).replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const lien = (modele, id) => modele.replace('__ID__', id);

    const carte = L.map(options.carte);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(carte);

    if (!options.zones.length) {
        carte.setView([36.8065, 10.1815], 7);
        return { changerMode() {} };
    }

    // Back office = liens vers les fiches : on y montre aussi le code, le matériau et l'âge
    const backOffice = Boolean(options.lienReseau);
    const nombre = n => Number(n).toLocaleString('fr-FR');
    const calqueReseaux = L.layerGroup().addTo(carte);   // repères des réseaux de la zone choisie
    let marqueurActif = null;

    function afficherZone(zone, marqueur) {
        // Mettre en avant la zone sélectionnée
        marqueurActif?.setStyle({ weight: 2 });
        marqueur.setStyle({ weight: 5 });
        marqueurActif = marqueur;

        // Réseaux ayant une position exacte : petit repère coloré selon leur état
        calqueReseaux.clearLayers();
        zone.reseaux.filter(r => r.lat !== null && r.lng !== null).forEach(r => {
            L.circleMarker([r.lat, r.lng], {
                radius: 6, color: '#fff', weight: 2, fillColor: couleurs[r.etat] || sansReseau, fillOpacity: 1,
            }).bindTooltip(`${echapper(r.libelleType)}${backOffice ? ' ' + echapper(r.code) : ''} — ${echapper(r.libelleEtat)}`)
              .addTo(calqueReseaux);
        });

        const lignes = zone.reseaux.map(r => {
            const code = backOffice ? ` <span class="text-secondary font-monospace small">${echapper(r.code)}</span>` : '';
            const libelle = `<i class="${options.icones[r.type] || ''} me-2"></i>${echapper(r.libelleType)}${code}`;
            const titre = backOffice ? `<a href="${lien(options.lienReseau, r.id)}">${libelle}</a>` : `<span>${libelle}</span>`;
            const details = [
                backOffice ? `${echapper(r.materiau)} · ${r.age} an${r.age > 1 ? 's' : ''}` : '',
                r.etat !== 'bon' && r.etatDepuis ? `${echapper(r.libelleEtat)} depuis le ${r.etatDepuis}` : '',
            ].filter(Boolean).join(' · ');
            return `<div class="list-group-item">
                        <div class="d-flex align-items-center">
                            ${titre}
                            <span class="badge ${options.badges[r.etat] || ''} ms-auto">${echapper(r.libelleEtat)}</span>
                        </div>
                        ${details ? `<div class="small text-secondary mt-1">${details}</div>` : ''}
                    </div>`;
        }).join('');

        const compte = zone.reseaux.length;
        panneau.innerHTML = `
            <div class="mb-3">
                <h3 class="mb-0 h4">${echapper(zone.nom)}</h3>
                <div class="text-secondary">${echapper(zone.commune)} · ${compte} réseau${compte > 1 ? 'x' : ''}</div>
                <div class="text-secondary small">${nombre(zone.population)} habitants desservis</div>
                ${backOffice && compte ? `<div class="mt-2 d-flex flex-wrap gap-1">
                    <span class="badge bg-${zone.risque.couleur}-lt" title="Score de l'élément le plus à risque">Risque ${zone.risque.score}/100 · ${echapper(zone.risque.libelle)}</span>
                    ${zone.groupe ? `<span class="badge bg-${zone.groupe.couleur}-lt" title="Groupe K-means">Groupe : ${echapper(zone.groupe.libelle)}</span>` : ''}
                </div>` : ''}
            </div>
            ${compte
                ? `<div class="list-group list-group-flush border rounded">${lignes}</div>`
                : '<p class="text-secondary mb-0">Aucun réseau d\'eau enregistré dans cette zone.</p>'}
            <div class="d-flex flex-wrap gap-2 mt-3">
                ${options.lienZone ? `<a href="${lien(options.lienZone, zone.id)}" class="btn btn-sm btn-outline-primary">Gérer les réseaux</a>` : ''}
                ${options.lienAnalyse && compte ? `<button type="button" class="btn btn-sm btn-primary" id="bouton-analyse-ia"><i class="ti ti-sparkles me-1"></i> Analyser avec l'IA</button>` : ''}
            </div>
            <div id="resultat-analyse-ia" class="mt-3"></div>
        `;

        // Rapport IA de la zone (back office) : voir analyse-ia.js
        document.getElementById('bouton-analyse-ia')?.addEventListener('click', function () {
            window.analyserZone(lien(options.lienAnalyse, zone.id), document.getElementById('resultat-analyse-ia'), this);
        });
    }

    /*
     * Couleur des zones selon le mode choisi (back office) :
     *   etat   = état le plus grave de ses réseaux
     *   risque = niveau du score de risque (élément le plus exposé)
     *   groupe = groupe trouvé par K-means
     */
    const teintes = { green: '#2fb344', yellow: '#f59f00', orange: '#f76707', red: '#d63939', azure: '#4299e1' };
    const legendes = {
        etat:   [['#2fb344', 'Bon'], ['#f59f00', 'Dégradé'], ['#d63939', 'Hors service'], [sansReseau, 'Sans réseau']],
        risque: [['#2fb344', 'Faible'], ['#f59f00', 'Modéré'], ['#f76707', 'Élevé'], ['#d63939', 'Critique'], [sansReseau, 'Sans réseau']],
        groupe: [['#d63939', 'Prioritaire'], ['#f59f00', 'À surveiller'], ['#2fb344', 'Sain'], [sansReseau, 'Non classée']],
    };
    const couleurZone = (zone, mode) => {
        if (!zone.reseaux.length) return sansReseau;
        if (mode === 'risque') return teintes[zone.risque.couleur] || sansReseau;
        if (mode === 'groupe') return teintes[zone.groupe?.couleur] || sansReseau;
        return couleurs[zone.etat] || sansReseau;
    };

    const marqueurs = [];
    const limites = [];
    options.zones.forEach(zone => {
        const couleur = couleurZone(zone, 'etat');
        const marqueur = L.circleMarker([zone.lat, zone.lng], {
            radius: Math.min(8 + Math.sqrt(zone.population) / 20, 30),   // plus d'habitants = cercle plus grand
            color: couleur, fillColor: couleur, fillOpacity: 0.45, weight: 2,
        }).addTo(carte);

        marqueur.bindTooltip(`${echapper(zone.nom)} (${echapper(zone.commune)})`);
        marqueur.on('click', () => afficherZone(zone, marqueur));
        marqueurs.push({ zone, marqueur });
        limites.push([zone.lat, zone.lng]);
    });

    carte.fitBounds(limites, { padding: [40, 40], maxZoom: 12 });

    // Change la couleur des zones et la légende (appelé par les boutons État / Risque / Groupe)
    function changerMode(mode) {
        marqueurs.forEach(({ zone, marqueur }) => {
            const couleur = couleurZone(zone, mode);
            marqueur.setStyle({ color: couleur, fillColor: couleur });
        });
        const legende = options.legende && document.getElementById(options.legende);
        if (legende) {
            legende.innerHTML = legendes[mode].map(([couleur, texte]) =>
                `<span class="d-inline-flex align-items-center"><span class="d-inline-block rounded-circle me-1" style="width:.65rem;height:.65rem;background:${couleur}"></span>${texte}</span>`
            ).join('');
        }
    }

    changerMode('etat');
    return { changerMode };
};
