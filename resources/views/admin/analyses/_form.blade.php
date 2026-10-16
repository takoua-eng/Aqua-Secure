@if ($errors->any())
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle me-1"></i> Veuillez corriger les champs en rouge.
    </div>
@endif

@if (empty($points))
    {{-- Une analyse doit porter sur un point en service : on prévient s'il n'y en a aucun --}}
    <div class="alert alert-warning">
        <i class="ti ti-info-circle me-1"></i> Aucun point de prélèvement en service.
        <a href="{{ route('admin.prelevements.create') }}" class="alert-link">Créez d'abord un point</a>.
    </div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="row">
            <div class="col-md-6">
                <x-select name="point_id" label="Point de prélèvement" icon="droplet" :options="$points"
                          :value="$analyse->point_id" required
                          placeholder="— Choisir un point —"
                          data-msg-required="Choisissez le point de prélèvement."
                          valid-message="Point sélectionné." />
            </div>

            <div class="col-md-6">
                <x-input name="date_prelevement" type="date" label="Date de prélèvement" icon="calendar"
                         :value="$analyse->date_prelevement?->format('Y-m-d')" required
                         min="2000-01-01" :max="now()->format('Y-m-d')"
                         data-msg-required="La date de prélèvement est obligatoire."
                         data-msg-min="La date ne peut pas être avant 2000."
                         data-msg-max="La date de prélèvement ne peut pas être dans le futur."
                         valid-message="Date valide." />
            </div>

            <div class="col-12">
                <x-input name="laboratoire" label="Laboratoire" icon="building-hospital" :value="$analyse->laboratoire" required
                         placeholder="Ex : Laboratoire central SONEDE — Tunis"
                         minlength="2" maxlength="150"
                         data-msg-required="Le laboratoire est obligatoire."
                         data-msg-minlength="Le nom du laboratoire doit contenir au moins 2 caractères."
                         data-msg-maxlength="Le nom du laboratoire ne peut pas dépasser 150 caractères."
                         valid-message="Laboratoire renseigné." />
            </div>
        </div>

        <h4 class="mt-2 mb-3">Résultats du laboratoire</h4>
        <div class="row">
            {{-- Champs texte pour accepter la virgule décimale (7,5) ; contrôle des bornes par le script plus bas --}}
            @foreach ([
                ['ph',                   'pH',                                 'ph',           'Ex : 7,2',  '\d+([.,]\d{1,2})?', 'Le pH est obligatoire.',                         'Le pH est un nombre avec au plus 2 décimales (ex : 7,2).'],
                ['chlore_residuel',      'Chlore résiduel (mg/L)',             'bottle',       'Ex : 0,35', '\d+([.,]\d{1,2})?', 'Le chlore résiduel est obligatoire.',            'Nombre positif avec au plus 2 décimales (ex : 0,35).'],
                ['turbidite',            'Turbidité (NTU)',                    'blur',         'Ex : 1,8',  '\d+([.,]\d{1,2})?', 'La turbidité est obligatoire.',                  'Nombre positif avec au plus 2 décimales (ex : 1,8).'],
                ['nitrates',             'Nitrates (mg/L)',                    'atom',         'Ex : 12,5', '\d+([.,]\d{1,2})?', 'Le taux de nitrates est obligatoire.',           'Nombre positif avec au plus 2 décimales (ex : 12,5).'],
                ['bacteries_coliformes', 'Bactéries coliformes (UFC/100 mL)', 'virus',        'Ex : 0',    '\d+',               'Le nombre de bactéries coliformes est obligatoire.', 'Nombre entier positif (ex : 0).'],
            ] as [$champ, $libelle, $icone, $exemple, $motif, $msgObligatoire, $msgFormat])
                <div class="col-md-6">
                    <x-input :name="$champ" :label="$libelle" :icon="$icone" :value="$analyse->{$champ}" required
                             inputmode="{{ $champ === 'bacteries_coliformes' ? 'numeric' : 'decimal' }}"
                             :placeholder="$exemple" :pattern="$motif" autocomplete="off"
                             :hint="'Norme : ' . \App\Models\AnalyseQualite::norme($champ)"
                             :data-msg-required="$msgObligatoire"
                             :data-msg-pattern="$msgFormat"
                             valid-message="Valeur saisie." />
                </div>
            @endforeach
        </div>
    </div>

    {{-- Indicateur en direct : conformité calculée pendant la saisie --}}
    <div class="col-lg-4">
        <div class="card bg-light" style="position: sticky; top: 1rem">
            <div class="card-body">
                <h4 class="card-title mb-2"><i class="ti ti-shield-check me-1"></i> Conformité (aperçu)</h4>
                <div id="verdict" class="alert alert-secondary mb-3">
                    <i class="ti ti-pencil me-1"></i> Saisissez les résultats du laboratoire.
                </div>
                <ul class="list-unstyled mb-2" id="liste-parametres">
                    @foreach (\App\Models\AnalyseQualite::PARAMETRES as $cle => $infos)
                        <li class="d-flex align-items-start mb-2" data-parametre="{{ $cle }}">
                            <i class="ti ti-circle-dashed text-secondary me-2 mt-1"></i>
                            <div>
                                <div class="fw-medium">{{ $infos['libelle'] }}</div>
                                <div class="small text-secondary">Norme : {{ \App\Models\AnalyseQualite::norme($cle) }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <small class="form-hint">
                    Seuils OMS / norme tunisienne NT 09.14. La conformité est recalculée par le serveur à l'enregistrement.
                </small>

                {{-- Détection de contamination (modèle IA) : risque de coliformes estimé avec les seules mesures de terrain --}}
                <div class="border-top mt-3 pt-3" id="prediction-ia">
                    <h4 class="card-title mb-1"><i class="ti ti-virus-search me-1"></i> Détection de contamination (IA)</h4>
                    @if ($modeleIA)
                        <div class="small text-secondary mb-2">
                            Risque de contamination bactérienne estimé à partir du pH, du chlore, de la turbidité et des nitrates,
                            avant le résultat de la culture en laboratoire (24 à 48 h).
                        </div>
                        <div id="prediction-ia-resultat" class="text-secondary small">
                            Saisissez le point et les 4 mesures physico-chimiques.
                        </div>
                    @else
                        <div class="small text-secondary">
                            Modèle non entraîné.
                            <a href="{{ route('admin.detection.index') }}">Entraîner le modèle</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            const seuils = @json(\App\Models\AnalyseQualite::SEUILS);
            const parametres = @json(\App\Models\AnalyseQualite::PARAMETRES);
            const verdict = document.getElementById('verdict');
            const nombre = (v, d) => v.toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });

            // "7,5" => 7.5 ; null si vide ou mal écrit
            function lire(champ) {
                const texte = champ.value.trim().replace(',', '.');
                return texte === '' || isNaN(texte) ? null : Number(texte);
            }

            // Bornes de saisie (le motif ne vérifie que le format)
            const bornes = { ph: [0, 14, 'Le pH doit être compris entre 0 et 14.'] };

            function mettreAJour() {
                let remplis = 0, horsNorme = 0;

                Object.keys(seuils).forEach(cle => {
                    const champ = document.querySelector(`[name="${cle}"]`);
                    const ligne = document.querySelector(`[data-parametre="${cle}"]`);
                    const icone = ligne.querySelector('i');
                    const detail = ligne.querySelector('.small');
                    const { min, max } = seuils[cle];
                    const infos = parametres[cle];
                    const valeur = champ.validity.patternMismatch ? null : lire(champ);

                    if (bornes[cle]) {
                        const [bas, haut, msg] = bornes[cle];
                        champ.setCustomValidity(valeur !== null && (valeur < bas || valeur > haut) ? msg : '');
                    }

                    const norme = detail.dataset.norme ??= detail.textContent;
                    if (valeur === null || champ.validity.customError) {
                        icone.className = 'ti ti-circle-dashed text-secondary me-2 mt-1';
                        detail.className = 'small text-secondary';
                        detail.textContent = norme;
                        return;
                    }

                    remplis++;
                    const unite = infos.unite ? ' ' + infos.unite : '';
                    let probleme = null;
                    if (min !== null && valeur < min) probleme = infos.trop_bas;
                    else if (max !== null && valeur > max) probleme = infos.trop_haut;

                    if (probleme) {
                        horsNorme++;
                        icone.className = 'ti ti-circle-x text-red me-2 mt-1';
                        detail.className = 'small text-red';
                        detail.textContent = `${nombre(valeur, infos.decimales)}${unite} : ${probleme} (${norme.toLowerCase()})`;
                    } else {
                        icone.className = 'ti ti-circle-check text-green me-2 mt-1';
                        detail.className = 'small text-green';
                        detail.textContent = `${nombre(valeur, infos.decimales)}${unite} : dans la norme`;
                    }
                });

                const total = Object.keys(seuils).length;
                if (horsNorme > 0) {
                    verdict.className = 'alert alert-danger mb-3';
                    verdict.innerHTML = `<i class="ti ti-alert-octagon me-1"></i> <strong>Eau non conforme</strong> : ${horsNorme} paramètre${horsNorme > 1 ? 's' : ''} hors norme.`;
                } else if (remplis === total) {
                    verdict.className = 'alert alert-success mb-3';
                    verdict.innerHTML = '<i class="ti ti-circle-check me-1"></i> <strong>Eau conforme</strong> : les 5 paramètres respectent la norme.';
                } else {
                    verdict.className = 'alert alert-secondary mb-3';
                    verdict.innerHTML = `<i class="ti ti-pencil me-1"></i> Saisie en cours : ${remplis} / ${total} paramètres renseignés, aucun hors norme pour l'instant.`;
                }
            }

            Object.keys(seuils).forEach(cle => document.querySelector(`[name="${cle}"]`).addEventListener('input', mettreAJour));
            mettreAJour();   // valeurs déjà présentes (modification ou retour après erreur)
        })();
    </script>

    @if ($modeleIA)
        <script>
            // Prédiction du modèle IA (arbre de décision entraîné côté serveur, parcouru ici à chaque saisie)
            (function () {
                const modele = @json($modeleIA);
                const typesPoints = @json($typesPoints);
                const zone = document.getElementById('prediction-ia-resultat');
                const champ = nom => document.querySelector(`[name="${nom}"]`);
                const lire = nom => {
                    const texte = champ(nom).value.trim().replace(',', '.');
                    return texte === '' || isNaN(texte) ? null : Number(texte);
                };
                const nombre = v => v.toLocaleString('fr-FR', { maximumFractionDigits: 2 });

                // Même vecteur d'entrée que DetectionContamination::vecteur() côté serveur
                function vecteur() {
                    const mesures = ['ph', 'chlore_residuel', 'turbidite', 'nitrates'].map(lire);
                    const type = typesPoints[champ('point_id').value];
                    if (mesures.includes(null) || !type) return null;
                    const date = champ('date_prelevement').value;
                    const mois = date ? Number(date.slice(5, 7)) : new Date().getMonth() + 1;
                    return [...mesures,
                        type === 'robinet_public' ? 1 : 0, type === 'reservoir' ? 1 : 0, type === 'sortie_station' ? 1 : 0,
                        mois >= 6 && mois <= 9 ? 1 : 0];
                }

                // Question de l'arbre en français (comme DetectionContamination::condition())
                function condition(variable, seuil, inferieur) {
                    const v = modele.variables[variable];
                    if (v.type === 'oui_non') return `${v.libelle} : ${inferieur ? 'non' : 'oui'}`;
                    return `${v.libelle} ${inferieur ? '≤' : '>'} ${nombre(seuil)}${v.unite ? ' ' + v.unite : ''}`;
                }

                function predire() {
                    const x = vecteur();
                    if (!x) {
                        zone.className = 'text-secondary small';
                        zone.textContent = 'Saisissez le point et les 4 mesures physico-chimiques.';
                        return;
                    }

                    // Descente dans l'arbre jusqu'à une feuille
                    let noeud = modele.arbre;
                    const raisons = [];
                    while (!noeud.feuille) {
                        const inferieur = x[noeud.variable] <= noeud.seuil;
                        raisons.push(condition(noeud.variable, noeud.seuil, inferieur));
                        noeud = inferieur ? noeud.gauche : noeud.droite;
                    }

                    const proba = noeud.proba;
                    const [, libelle, couleur] = modele.niveaux.find(([max]) => proba < max) || modele.niveaux[modele.niveaux.length - 1];
                    const alerte = proba >= modele.seuilAlerte;

                    zone.className = '';
                    zone.innerHTML = `
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="h2 mb-0 text-${couleur}">${Math.round(proba * 100)} %</span>
                            <span class="badge bg-${couleur}-lt">${libelle}</span>
                        </div>
                        <div class="progress progress-sm mb-2"><div class="progress-bar bg-${couleur}" style="width: ${proba * 100}%"></div></div>
                        ${alerte ? '<div class="small text-red mb-1"><i class="ti ti-alert-triangle me-1"></i>Alerte : prévoir un contrôle sans attendre le laboratoire (rechloration, purge).</div>' : ''}
                        <div class="small text-secondary">Raisonnement du modèle :</div>
                        <ul class="small mb-0 ps-3">${raisons.map(r => `<li>${r}</li>`).join('')}</ul>
                        <div class="small text-secondary mt-1">Basé sur ${noeud.n} analyses passées similaires, dont ${noeud.positifs} contaminées.</div>`;
                }

                ['ph', 'chlore_residuel', 'turbidite', 'nitrates', 'point_id', 'date_prelevement']
                    .forEach(nom => ['input', 'change'].forEach(evt => champ(nom).addEventListener(evt, predire)));
                predire();
            })();
        </script>
    @endif
@endpush
