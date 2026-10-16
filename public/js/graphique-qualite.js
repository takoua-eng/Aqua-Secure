/*
 * Graphique ApexCharts de l'évolution d'un paramètre de qualité de l'eau, avec les limites de la norme.
 * Utilisé par la fiche d'un point (back office) et par la page Qualité de l'eau (citoyens).
 *
 * graphiqueQualite({
 *     element: 'graphique-parametre',      // id du conteneur
 *     choix: 'choix-parametre',            // id du <select> des paramètres
 *     evolution: { dates: [...], valeurs: { ph: [...], ... } },
 *     seuils: AnalyseQualite::SEUILS,
 *     parametres: AnalyseQualite::PARAMETRES,
 * });
 */
function graphiqueQualite({ element, choix, evolution, seuils, parametres }) {
    const zone = document.getElementById(element);
    const liste = document.getElementById(choix);
    if (!zone || !liste) return;

    const encre = { principale: '#0b0b0b', muette: '#898781', grille: '#e1e0d9', axe: '#c3c2b7' };
    const bleu = '#2a78d6', rouge = '#d03b3b';
    const nombre = (v, d) => Number(v).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });

    function options(cle) {
        const infos = parametres[cle], seuil = seuils[cle];
        const valeurs = evolution.valeurs[cle].map(Number);
        const unite = infos.unite ? ' ' + infos.unite : '';
        const horsNorme = v => (seuil.min !== null && v < seuil.min) || (seuil.max !== null && v > seuil.max);

        // Limites de la norme : lignes rouges en pointillés
        const limites = [];
        if (seuil.min !== null) limites.push({ y: seuil.min, libelle: 'Minimum ' + nombre(seuil.min, 1) + unite });
        if (seuil.max !== null) limites.push({ y: seuil.max, libelle: 'Maximum ' + nombre(seuil.max, seuil.max % 1 ? 1 : 0) + unite });

        const plafond = Math.max(...valeurs, ...limites.map(l => l.y), 1) * 1.15;

        return {
            chart: { type: 'line', height: 280, fontFamily: 'inherit', toolbar: { show: false }, animations: { enabled: false }, parentHeightOffset: 0 },
            series: [{ name: infos.libelle, data: valeurs }],
            colors: [bleu],
            stroke: { width: 2, curve: 'straight', lineCap: 'round' },
            markers: {
                size: 4, strokeColors: '#ffffff', strokeWidth: 2, hover: { size: 6 },
                // Les mesures hors norme sont en rouge
                discrete: valeurs.map((v, i) => horsNorme(v) ? { seriesIndex: 0, dataPointIndex: i, fillColor: rouge, strokeColor: '#ffffff', size: 6 } : null).filter(Boolean),
            },
            annotations: {
                yaxis: limites.map(l => ({
                    y: l.y, borderColor: rouge, strokeDashArray: 5,
                    label: { text: l.libelle, position: 'left', textAnchor: 'start', borderColor: 'transparent', style: { color: rouge, background: 'transparent' } },
                })),
            },
            grid: { borderColor: encre.grille },
            dataLabels: { enabled: false },
            legend: { show: false },
            tooltip: { theme: 'light', y: { formatter: v => nombre(v, infos.decimales) + unite } },
            xaxis: { categories: evolution.dates, labels: { style: { colors: encre.muette } }, axisBorder: { color: encre.axe } },
            yaxis: { min: 0, max: plafond, tickAmount: 5, labels: { style: { colors: encre.muette }, formatter: v => nombre(v, infos.decimales ? 1 : 0) } },
        };
    }

    let graphique = new ApexCharts(zone, options(liste.value));
    graphique.render();

    liste.addEventListener('change', () => {
        graphique.destroy();
        graphique = new ApexCharts(zone, options(liste.value));
        graphique.render();
    });
}
