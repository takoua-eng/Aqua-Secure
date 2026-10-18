/*
 * Binôme 3 — AquaSecure What If? Engine (interface).
 * Le navigateur ne calcule rien : il envoie les paramètres (hypothèses) et affiche les résultats
 * calculés par le serveur (MoteurWhatIf). Tout texte affiché est échappé.
 */
(function () {
    const cfg = window.WhatIfConfig;
    const $ = id => document.getElementById(id);
    const e = RenovationIA.echapper;
    const dt = (v, dec = 0) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: dec, maximumFractionDigits: dec }).format(v) + ' DT';
    const dtAuto = v => dt(v, Number.isInteger(Math.round(v * 100) / 100) ? 0 : 2);
    const pct = v => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(v) + ' %';
    const badge = (type) => ({
        enregistre: '<span class="badge bg-blue-lt" title="Donnée réelle enregistrée"><i class="ti ti-database me-1"></i>Enregistré</span>',
        hypothese: '<span class="badge bg-purple-lt" title="Valeur hypothétique"><i class="ti ti-bulb me-1"></i>Hypothèse</span>',
        calcule: '<span class="badge bg-teal-lt" title="Résultat du moteur"><i class="ti ti-calculator me-1"></i>Calculé</span>',
    }[type]);
    const couleurEtat = { hors_service: 'red', degrade: 'yellow', bon: 'green' };
    const couleurNiveau = s => s >= 70 ? 'red' : s >= 45 ? 'orange' : s >= 25 ? 'yellow' : 'green';

    let derniere = null;      // dernière réponse du serveur
    let numero = 0;           // ignore les réponses arrivées dans le désordre
    let minuteur = null;
    const graphiques = {};

    // ---------- Paramètres (hypothèses) ----------
    function parametres() {
        return {
            budget: Number($('wi-budget-saisie').value) || 0,
            strategie: document.querySelector('input[name="wi-strategie"]:checked').value,
            partiel: $('wi-partiel').checked,
            inclure_estimes: $('wi-estimes').checked,
            exclus: [...document.querySelectorAll('.wi-inclus:not(:disabled)')].filter(c => !c.checked).map(c => Number(c.value)),
            incertitude_estime: Number($('wi-incertitude-estime').value),
            incertitude_devis: Number($('wi-incertitude-devis').value),
        };
    }

    function majBudget(source) {
        const curseur = $('wi-budget-curseur');
        const saisie = $('wi-budget-saisie');
        if (source === 'curseur') saisie.value = curseur.value;
        else curseur.value = Math.min(Number(saisie.value) || 0, Number(curseur.max));
        $('wi-budget-affiche').textContent = dt(Number(saisie.value) || 0);
    }

    function planifier(delai = 300) {
        clearTimeout(minuteur);
        minuteur = setTimeout(simuler, delai);
    }

    // ---------- Appel au moteur ----------
    async function simuler() {
        const ici = ++numero;
        $('wi-resultats').classList.add('wi-calcul');
        $('wi-chargement').classList.remove('d-none');
        $('wi-erreur').innerHTML = '';
        try {
            const [ok, d] = await RenovationIA.envoyer(cfg.urlSimuler, parametres());
            if (ici !== numero) return;
            if (!ok) {
                $('wi-erreur').innerHTML = `<div class="alert alert-danger"><i class="ti ti-alert-circle me-1"></i>${e(d)}</div>`;
                return;
            }
            derniere = d;
            afficher();
        } catch (err) {
            if (ici === numero) $('wi-erreur').innerHTML = '<div class="alert alert-danger">Connexion au serveur impossible.</div>';
        } finally {
            if (ici === numero) {
                $('wi-resultats').classList.remove('wi-calcul');
                $('wi-chargement').classList.add('d-none');
            }
        }
    }

    // ---------- Affichage ----------
    function afficher() {
        const c = derniere.comparaison;
        const cle = parametres().strategie;
        const s = c.scenarios[cle];

        afficherDiagnostic(c);
        afficherKpi(s);
        afficherComparaison(c, cle);
        afficherAvantApres(s);
        afficherRepartition(s);
        afficherRisques(s, c);
        $('wi-resume-enregistrement').innerHTML =
            `Budget ${e(dt(c.budget))} · stratégie ${e(s.lettre)} « ${e(s.libelle)} » · ${s.kpi.nb_candidats} projet(s) candidat(s). `
            + 'Le serveur recalculera le scénario sur les données réelles du moment. Aucune donnée réelle ne sera modifiée.';
    }

    function afficherDiagnostic(c) {
        const d = c.diagnostic;
        if (!d.messages.length) { $('wi-diagnostic').innerHTML = ''; return; }
        const classe = { insuffisant: 'danger', attention: 'warning', ok: 'info' }[d.niveau];
        $('wi-diagnostic').innerHTML = `<div class="alert alert-${classe}">
            <div class="fw-bold mb-1"><i class="ti ti-${d.niveau === 'insuffisant' ? 'database-off' : 'info-circle'} me-1"></i>
            ${d.niveau === 'insuffisant' ? 'Données insuffisantes' : 'À savoir sur ces données'}</div>
            <ul class="mb-0 ps-3">${d.messages.map(m => `<li>${e(m)}</li>`).join('')}</ul></div>`;
    }

    function carteKpi(titre, valeur, detail, icone, couleur) {
        return `<div class="col-6 col-lg-4 col-xxl"><div class="card card-sm h-100"><div class="card-body">
            <div class="d-flex align-items-center mb-1"><span class="avatar avatar-sm bg-${couleur}-lt me-2"><i class="ti ti-${icone}"></i></span>
            <span class="small text-secondary">${titre}</span></div>
            <div class="h2 mb-0">${valeur}</div><div class="small text-secondary">${detail}</div></div></div></div>`;
    }

    function afficherKpi(s) {
        const k = s.kpi;
        $('wi-kpi').innerHTML =
            carteKpi('Budget alloué', e(dt(k.alloue)), `${pct(k.utilisation_pct)} du budget · reste ${e(dt(k.reste))}`, 'coin', 'purple')
            + carteKpi('Projets achevés', `${k.nb_complets} / ${k.nb_candidats}`, 'financés en entier', 'circle-check', 'green')
            + carteKpi('Financements partiels', String(k.nb_partiels), 'ne permettent pas d\'achever', 'progress', 'orange')
            + carteKpi('Priorité couverte', pct(k.priorite_couverte_pct), `besoin couvert : ${pct(k.besoin_couvert_pct)}`, 'target-arrow', 'blue')
            + carteKpi('Réseaux dégradés traités', `${k.critiques_couverts} / ${k.critiques_total}`, 'hors service ou dégradés', 'alert-octagon', 'red');

        // Robustesse de la stratégie détaillée (hypothèse d'incertitude affichée)
        const r = derniere.robustesse && derniere.robustesse[s.strategie];
        if (r) {
            const couleur = r.tient_pct >= 80 ? 'green' : r.tient_pct >= 50 ? 'yellow' : 'red';
            $('wi-kpi').innerHTML += carteKpi('Robustesse', pct(r.tient_pct),
                `tient dans le budget sur ${r.tirages} tirages (coûts ±${Math.round(r.incertitude_estime * 100)} % / ±${Math.round(r.incertitude_devis * 100)} %)`
                + (r.surcout_p95 > 0 ? ` · surcoût possible jusqu'à ${e(dt(r.surcout_p95))} (95 %)` : ''), 'dice-5', couleur);
        }
    }

    function afficherComparaison(c, choisie) {
        const cles = Object.keys(c.scenarios);
        const nom = k => `${c.scenarios[k].lettre} · ${c.scenarios[k].libelle}`;
        const lignes = [
            ['Priorité couverte', 'priorite_couverte_pct', pct],
            ['Projets achevés', 'nb_complets', v => v],
            ['Réseaux dégradés traités', 'critiques_couverts', v => v],
            ['Gouvernorats aidés', 'territoires_finances', v => v ?? '—'],
            ['Besoin couvert', 'besoin_couvert_pct', pct],
            ['Financements partiels', 'nb_partiels', v => v],
            ['Budget non utilisé', 'reste', v => dt(v)],
            ['Concentration (plus gros projet)', 'concentration_pct', pct],
            ['Part sur coûts estimés', 'part_estimee_pct', pct],
        ];

        let html = '<thead><tr><th>Indicateur</th>' + cles.map(k =>
            `<th class="wi-strategie-col text-center ${k === choisie ? 'wi-active' : ''}" data-strategie="${k}" title="Détailler cette stratégie">
                ${e(c.scenarios[k].lettre)} · ${e(c.scenarios[k].libelle)}
                ${k === c.recommande ? '<div><span class="badge bg-green-lt mt-1"><i class="ti ti-star me-1"></i>Recommandée (règle)</span></div>' : ''}
            </th>`).join('') + '</tr></thead><tbody>';
        lignes.forEach(([libelle, kpi, f]) => {
            const gagnants = c.meilleurs[kpi] || [];
            html += `<tr><td class="text-secondary">${libelle}</td>` + cles.map(k =>
                `<td class="text-center wi-strategie-col ${k === choisie ? 'wi-active' : ''}" data-strategie="${k}">
                    ${gagnants.includes(k) && cles.length > 1 ? '<i class="ti ti-trophy text-yellow me-1" title="Meilleure valeur"></i>' : ''}${e(f(c.scenarios[k].kpi[kpi]))}</td>`
            ).join('') + '</tr>';
        });
        if (derniere.robustesse) {
            const meilleur = Math.max(...cles.map(k => derniere.robustesse[k].tient_pct));
            html += '<tr><td class="text-secondary" title="Part des tirages où le scénario reste dans le budget si les coûts varient selon l\'hypothèse choisie">Robustesse (coûts incertains)</td>'
                + cles.map(k => `<td class="text-center wi-strategie-col ${k === choisie ? 'wi-active' : ''}" data-strategie="${k}">
                    ${derniere.robustesse[k].tient_pct === meilleur && cles.length > 1 ? '<i class="ti ti-trophy text-yellow me-1"></i>' : ''}${e(pct(derniere.robustesse[k].tient_pct))}</td>`).join('') + '</tr>';
        }
        $('wi-tableau-comparaison').innerHTML = html + '</tbody>';
        $('wi-recommandation').innerHTML = `<i class="ti ti-calculator me-1"></i>${e(c.raison)}
            <span class="ms-1">La justification argumentée est proposée par l'analyse IA, plus bas.</span>`;

        const series = [
            { name: 'Priorité couverte (%)', data: cles.map(k => c.scenarios[k].kpi.priorite_couverte_pct) },
            { name: 'Besoin couvert (%)', data: cles.map(k => c.scenarios[k].kpi.besoin_couvert_pct) },
            { name: 'Projets achevés (% des candidats)', data: cles.map(k => c.scenarios[k].kpi.nb_candidats ? Math.round(1000 * c.scenarios[k].kpi.nb_complets / c.scenarios[k].kpi.nb_candidats) / 10 : 0) },
        ];
        dessiner('comparaison', 'wi-graphique-comparaison', {
            chart: { type: 'bar', height: 260, fontFamily: 'inherit', toolbar: { show: false }, animations: { speed: 300 } },
            series,
            xaxis: { categories: cles.map(nom) },
            yaxis: { max: 100, labels: { formatter: v => Math.round(v) + ' %' } },
            colors: ['#066fd1', '#0ca678', '#f59f00'],
            plotOptions: { bar: { columnWidth: '60%', borderRadius: 3 } },
            dataLabels: { enabled: false },
            legend: { position: 'top' },
            tooltip: { y: { formatter: v => pct(v) } },
        });
    }

    function afficherAvantApres(s) {
        const lignes = s.lignes.slice().sort((a, b) => b.score - a.score || a.id - b.id).slice(0, 15);
        $('wi-titre-avant-apres').textContent = `— stratégie ${s.lettre}, ${lignes.length} projet(s) les plus prioritaires`;
        dessiner('avant-apres', 'wi-graphique-avant-apres', {
            chart: { type: 'bar', stacked: true, height: Math.max(260, 34 * lignes.length + 80), fontFamily: 'inherit', toolbar: { show: false }, animations: { speed: 300 } },
            series: [
                { name: 'Déjà financé (enregistré)', data: lignes.map(l => l.finance) },
                { name: 'Proposé (simulation)', data: lignes.map(l => l.propose) },
                { name: 'Reste à financer après', data: lignes.map(l => Math.max(0, Math.round((l.cout - l.apres) * 100) / 100)) },
            ],
            xaxis: { categories: lignes.map(l => l.titre.length > 38 ? l.titre.slice(0, 36) + '…' : l.titre),
                labels: { formatter: v => new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(v) } },
            plotOptions: { bar: { horizontal: true, barHeight: '65%' } },
            colors: ['#066fd1', '#0ca678', '#dadfe5'],
            dataLabels: { enabled: false },
            legend: { position: 'top' },
            tooltip: { y: { formatter: v => dt(v) } },
        });
    }

    function afficherRepartition(s) {
        $('wi-titre-repartition').textContent = `— ${s.lettre} · ${s.libelle}`;
        const etat = {
            complet: '<span class="badge bg-green-lt"><i class="ti ti-circle-check me-1"></i>Financé en entier</span>',
            partiel: '<span class="badge bg-orange-lt" title="Ne permet pas d\'achever le projet"><i class="ti ti-progress me-1"></i>Partiel</span>',
            aucun: '<span class="badge bg-secondary-lt">Non financé</span>',
        };
        $('wi-repartition').innerHTML = s.lignes.map(l => `
            <tr>
                <td class="text-secondary">${l.rang ?? '—'}</td>
                <td>
                    <div class="fw-medium">${e(l.titre)}</div>
                    <div class="small"><span class="font-monospace">${e(l.reseau)}</span>
                        <span class="text-${couleurEtat[l.etat_reseau] || 'secondary'}">· ${e(cfg.etats[l.etat_reseau] || 'état inconnu')}</span></div>
                    <div class="wi-raison">${e(l.raison)}</div>
                </td>
                <td><span class="badge bg-${couleurNiveau(l.score)} text-white">${l.score}</span></td>
                <td class="text-end text-nowrap">${e(dtAuto(l.cout))}<div>${({
                    declare: '<span class="badge bg-green-lt">Devis</span>',
                    predit: '<span class="badge bg-indigo-lt" title="Prédit par le modèle entraîné (page Modèles IA)">Prédit IA</span>',
                })[l.cout_source] || '<span class="badge bg-yellow-lt">Estimé</span>'}</div></td>
                <td class="text-end text-nowrap">${e(dtAuto(l.finance))}</td>
                <td class="text-end text-nowrap fw-bold ${l.propose > 0 ? 'text-teal' : 'text-secondary'}">${e(dtAuto(l.propose))}</td>
                <td>
                    <div class="progress progress-sm" title="Avant : ${pct(l.avant_pct)} · après : ${pct(l.apres_pct)}">
                        <div class="progress-bar bg-blue" style="width:${Math.min(100, l.avant_pct)}%"></div>
                        <div class="progress-bar bg-teal" style="width:${Math.max(0, Math.min(100, l.apres_pct) - Math.min(100, l.avant_pct))}%"></div>
                    </div>
                    <div class="small text-secondary">${pct(l.avant_pct)} → <strong>${pct(l.apres_pct)}</strong></div>
                </td>
                <td>${etat[l.etat]}</td>
            </tr>`).join('') || '<tr><td colspan="8" class="text-center text-secondary py-4">Aucun projet candidat.</td></tr>';
    }

    function afficherRisques(s, c) {
        const icones = { danger: ['red', 'alert-octagon'], warning: ['orange', 'alert-triangle'], info: ['azure', 'info-circle'] };
        $('wi-risques').innerHTML =
            (s.risques.length ? '<ul class="list-unstyled mb-3">' + s.risques.map(r =>
                `<li class="d-flex gap-2 mb-2"><i class="ti ti-${icones[r.niveau][1]} text-${icones[r.niveau][0]} mt-1"></i><span>${e(r.texte)}</span></li>`
            ).join('') + '</ul>' : '<p class="text-secondary">Aucun risque particulier détecté par les règles.</p>')
            + `<div class="small"><strong>Critère :</strong> ${e(s.criteres)}</div>`
            + `<div class="small mt-1"><strong>Compromis :</strong> ${e(s.compromis)}</div>`;

        $('wi-hypotheses').innerHTML = '<ul class="list-unstyled mb-2">' + c.hypotheses.map(h =>
            `<li class="d-flex gap-2 mb-1"><i class="ti ti-bulb text-purple mt-1"></i><span>${e(h)}</span></li>`).join('') + '</ul>'
            + (c.ecartes.length ? '<div class="fw-bold small mt-2">Projets écartés</div><ul class="small list-unstyled mb-0">'
                + c.ecartes.map(x => `<li><i class="ti ti-point text-secondary"></i>${e(x.titre)} — <span class="text-secondary">${e(x.motif)}</span></li>`).join('') + '</ul>' : '');
    }

    function dessiner(nom, id, options) {
        if (graphiques[nom]) {
            graphiques[nom].updateOptions(options, true, true);
        } else {
            graphiques[nom] = new ApexCharts($(id), options);
            graphiques[nom].render();
        }
    }

    // ---------- Analyse IA ----------
    async function analyser() {
        const bouton = $('wi-ia');
        const cible = $('wi-analyse-ia');
        bouton.disabled = true;
        RenovationIA.attente(cible, "Analyse des scénarios en cours… (jusqu'à une minute)");
        cible.scrollIntoView({ behavior: 'smooth', block: 'center' });
        try {
            const [ok, d] = await RenovationIA.envoyer(cfg.urlAnalyser, parametres());
            if (!ok) { RenovationIA.erreur(cible, d); return; }
            const noms = Object.fromEntries(Object.entries(cfg.strategies).map(([k, s]) => [k, `${s.lettre} · ${s.libelle}`]));
            cible.innerHTML = RenovationIA.scenarios(d, noms);
        } catch (err) {
            RenovationIA.erreur(cible, 'Connexion au serveur impossible.');
        } finally {
            bouton.disabled = false;
        }
    }

    // ---------- Enregistrement : on envoie les paramètres, le serveur recalcule ----------
    $('wi-form-enregistrer').addEventListener('submit', function () {
        const p = parametres();
        const champs = [['budget', p.budget], ['strategie', p.strategie], ['partiel', p.partiel ? 1 : 0], ['inclure_estimes', p.inclure_estimes ? 1 : 0]];
        p.exclus.forEach(id => champs.push(['exclus[]', id]));
        $('wi-champs-caches').innerHTML = champs.map(([n, v]) => `<input type="hidden" name="${e(n)}" value="${e(v)}">`).join('');
    });

    // ---------- Événements ----------
    $('wi-budget-curseur').addEventListener('input', () => { majBudget('curseur'); planifier(); });
    $('wi-budget-saisie').addEventListener('input', () => { majBudget('saisie'); planifier(500); });
    document.querySelectorAll('.wi-raccourci').forEach(b => b.addEventListener('click', () => {
        $('wi-budget-saisie').value = Math.round(cfg.besoinTotal * Number(b.dataset.part) / 100 / 1000) * 1000;
        majBudget('saisie');
        planifier(0);
    }));
    document.querySelectorAll('input[name="wi-strategie"]').forEach(r => r.addEventListener('change', () => derniere && afficher()));
    ['wi-partiel', 'wi-estimes'].forEach(id => $(id).addEventListener('change', () => planifier(0)));
    ['wi-incertitude-estime', 'wi-incertitude-devis'].forEach(id => $(id).addEventListener('input', function () {
        $(id + '-valeur').textContent = this.value;
        planifier();
    }));
    document.querySelectorAll('.wi-inclus').forEach(c => c.addEventListener('change', () => planifier()));
    $('wi-tous').addEventListener('click', () => { document.querySelectorAll('.wi-inclus:not(:disabled)').forEach(c => c.checked = true); planifier(0); });
    $('wi-aucun').addEventListener('click', () => { document.querySelectorAll('.wi-inclus:not(:disabled)').forEach(c => c.checked = false); planifier(0); });
    $('wi-filtre').addEventListener('input', function () {
        const t = this.value.trim().toLowerCase();
        document.querySelectorAll('.wi-projet').forEach(p => p.classList.toggle('d-none', t !== '' && !p.dataset.texte.includes(t)));
    });
    $('wi-simuler').addEventListener('click', () => planifier(0));
    $('wi-ia').addEventListener('click', analyser);
    // Clic sur une colonne du comparatif : on détaille cette stratégie
    $('wi-tableau-comparaison').addEventListener('click', ev => {
        const col = ev.target.closest('[data-strategie]');
        if (!col) return;
        document.querySelector(`input[name="wi-strategie"][value="${col.dataset.strategie}"]`).checked = true;
        afficher();
    });

    majBudget('saisie');
    simuler();
})();
