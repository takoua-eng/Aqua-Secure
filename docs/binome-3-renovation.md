# Binôme 3 — Financement des rénovations

Projets de rénovation, financements, **Smart Budget Monitoring**, **scoring de priorité**, **détection des risques
financiers** et **AquaSecure — What If? Engine** (simulateur de décisions financières avec interprétation IA).

Règle d'or du module : on ne confond jamais

| Badge | Signification |
|---|---|
| **Enregistré** (bleu) | donnée réelle lue en base |
| **Hypothèse** (violet) | valeur saisie dans le simulateur (budget hypothétique, exclusions, options) |
| **Calculé** (vert d'eau) | résultat d'un calcul déterministe (moteur, score, alertes) |
| **Estimé** (jaune) / **Devis** (vert) | coût issu d'un barème / coût déclaré par le gestionnaire |
| **IA** (rose) / **Mode secours** (gris) | interprétation rédigée par l'IA / par des règles fixes si l'IA est indisponible |

---

## 1. Modèle de données

Trois tables **nouvelles** (migrations additives, aucune table existante modifiée) :

| Table | Colonnes du modèle officiel | Colonnes ajoutées et pourquoi |
|---|---|---|
| `projets_renovation` | `id`, `reseau_id` → `reseaux_eau.id`, `titre`, `statut` (`planifie`, `en_cours`, `termine`) | `cout_estime` (nullable) : coût **déclaré** (devis) — sans lui, aucun taux de couverture fiable n'est possible. `description`, `publie` : page de transparence citoyenne. |
| `financements` | `id`, `projet_id` → `projets_renovation.id`, `source` (`Etat`, `municipalite`, `bailleur`, `don`), `montant` decimal(10,2) | `simulation_id` (nullable) : traçabilité quand le financement vient d'un scénario approuvé et appliqué. |
| `simulations_financement` | — | scénarios What If? enregistrés : paramètres, résultats calculés, empreinte des données, analyse IA, cycle de validation. |

Intégrité : `restrictOnDelete` partout (un réseau qui a des projets, ou un projet qui a des financements, ne peut pas être supprimé).

**Coût d'un projet** (`app/Services/Renovation/ModeleCout.php`) : coût déclaré si saisi, sinon **estimation** avec le barème
existant du module Analyse des risques (`PlanRenovation::cout`, binôme 1, réutilisé sans modification), toujours signalée.

## 2. Fonctionnalités

| Fonctionnalité | Où | Fichiers principaux |
|---|---|---|
| CRUD projets (recherche, filtres statut/zone/financement, tri, pagination, score, couverture) | `/admin/projets` | `ProjetRenovationController`, `ProjetRenovationRequest`, `ProjetRenovationPolicy` |
| CRUD financements (filtres source/projet/origine/montants, totaux de la sélection) | `/admin/financements` | `FinancementController`, `FinancementRequest`, `FinancementPolicy` |
| **Smart Budget Monitoring** | `/admin/financement` | `SuiviBudget` |
| **Scoring de priorité** (0-100, expliqué) | fiches, listes, simulateur | `ScorePriorite` (réutilise `ScoreRisque` du binôme 1) |
| **Détection des risques financiers** (règles F1 à F9) | tableau financier, fiche projet | `DetectionRisquesFinanciers` |
| **What If? Engine** | `/admin/what-if` | `MoteurWhatIf`, `CandidatsWhatIf`, `WhatIfController`, `public/js/what-if.js` |
| Historique, comparaison, validation, application, rapport PDF | `/admin/simulations` | `SimulationController`, `SimulationFinancementPolicy` |
| Interprétation IA + mode secours | simulateur, simulations, alertes | `AnalyseFinancementIA`, `ValidationReponseIA` |
| **Observatoire citoyen** : compteurs, « Mon quartier » (DT par habitant vs moyenne), carte des chantiers, « Sur 100 DT investis », « Où va l'argent ? » (treemap), projets suivis (navigateur), dernières mises à jour | `/renovation` | `Front\RenovationPubliqueController`, `ObservatoireCitoyen` |
| **Fiche chantier citoyenne** : avancement animé, « Pourquoi ce chantier ? » (âge, durée de vie, état, population — données réelles), financement par habitant, mini-carte, partage, chantiers voisins | `/renovation/{id}` | idem |
| **Participation** : « Ce projet me concerne » (soutien, n'entre pas dans les décisions), questions publiques modérées (publiées avec la réponse du gestionnaire, carte « Voix des citoyens » côté gestion) | fiche chantier + fiche projet admin | `QuestionCitoyenneController`, migration `2026_10_18_300004` |

### Score de priorité

| Critère | Points | Source |
|---|---|---|
| Risque du réseau | 40 | score de risque du module Analyse des risques |
| Avancement | 20 | en cours 20, planifié 10, terminé 0 |
| Reste à financer | 25 | part du coût non financée (coût déclaré ou estimé, signalé) |
| Alertes des capteurs | 8 | relevés hors seuil sur 30 jours (module Surveillance, lecture seule) : 1 à 4 → 4, 5 et plus → 8 |
| Qualité de l'eau | 7 | part d'analyses non conformes sur 90 jours (module Qualité de l'eau, lecture seule) |

Le score **classe** les projets ; il ne mesure ni l'urgence, ni une probabilité de panne (affiché à l'écran).

### Règles de détection

F1 projet sans financement · F2 projet terminé sans financement · F3 financement > coût déclaré (ou > 150 % de
l'estimation) · F4 montant nul ou négatif · F5 chantier dépendant d'une seule source · F6 une source ≥ 60 % du total
(indice HHI) · F7 coûts non déclarés · F8 financements orphelins · F9 projet terminé sous-financé ·
F10 montant atypique (au-delà de Q3 + 3 × écart interquartile, méthode de Tukey, 8 financements minimum).

### What If? Engine

- Budget hypothétique (curseur + saisie + raccourcis 25/50/75/100 % du besoin), projets inclus/exclus,
  option « financement partiel », option « devis uniquement ».
- **4 stratégies calculées et comparées à chaque fois** :
  - **A — Réseaux dégradés d'abord** : hors service, puis dégradé, puis bon ; à état égal, score le plus haut.
  - **B — Répartition équilibrée** : proportionnelle au score, plafonnée au besoin, surplus redistribué.
  - **C — Maximum de projets** : plus petits besoins d'abord (optimal pour le nombre de projets achevés).
  - **D — Priorité maximale** : sac à dos 0/1 en programmation dynamique (somme des scores maximale).
- Calcul en **centimes entiers**, tris stables, vérification finale : budget jamais dépassé, aucune allocation
  supérieure au besoin (sinon exception). Résultats reproductibles.
- Indicateurs : alloué, reste, projets achevés, partiels, priorité couverte, besoin couvert, réseaux dégradés traités,
  concentration, part reposant sur des coûts estimés. Risques et compromis par règles. Hypothèses listées.
  Données insuffisantes détectées (aucun candidat, budget nul, budget < plus petit besoin, coûts estimés…).
- **Recommandation calculée** (règle fixe) distincte de la **recommandation IA** (avis argumenté).

### Validation humaine

`brouillon` (privé) → `soumise` → `approuvee` / `rejetee` → `appliquee`

- Le gestionnaire simule, enregistre, soumet. L'administrateur approuve (en choisissant la source réelle des fonds) ou
  rejette (motif obligatoire), puis applique.
- **Appliquer** = seule opération qui crée des financements réels : administrateur, case de confirmation, transaction
  avec verrou, **revérification** des données (financements, coûts, statuts identiques au calcul ; sinon refus),
  budget revérifié, **ajout uniquement** (rien n'est modifié ni supprimé), financements rattachés à la décision.

## 3. Intégration IA

- Fournisseur (config/renovation.php) : **Groq** (offre gratuite, `GROQ_API_KEY`, `GROQ_MODEL`, par défaut `openai/gpt-oss-120b`) en priorité,
  sinon **Claude** (`ANTHROPIC_API_KEY`, configuration commune), sinon mode secours. Clés uniquement dans `.env`, jamais dans Git.
- Offre gratuite Groq : 8 000 tokens par minute (question + réponse maximale) ; au-delà, le mode secours s'affiche
  avec le message « Quota gratuit atteint » — réessayer une minute plus tard.
- Seules des **données calculées** sont envoyées (aucune donnée personnelle) ; titres saisis nettoyés et présentés à
  l'IA comme des données, jamais des instructions.
- Réponse au **format JSON imposé** (structured outputs), puis contrôlée par `ValidationReponseIA` :
  structure, longueurs, stratégie existante, projets cités existants, et **aucun chiffre absent des résultats calculés**.
- Délai 60 s, 1 nouvelle tentative, cache 6 h, routes limitées à 10 appels/minute.
- Clé absente, API indisponible ou réponse rejetée → **mode secours** (même structure, rédigé par des règles), avec le
  motif affiché. L'IA n'écrit jamais en base.

## 4. Droits

| Rôle | Accès |
|---|---|
| Citoyen | `/renovation` uniquement : projets **publiés**, statut, montants agrégés par source. Aucun accès au back office (403). |
| Gestionnaire | tout le module ; ne peut pas modifier un projet terminé ni un financement issu d'une décision ; ne voit pas les brouillons des autres ; ne décide pas et n'applique pas. |
| Administrateur | tout, plus décision et application des scénarios. |

## 5. Tests

```bash
php vendor/bin/phpunit tests/Unit/Renovation            # moteur, score, alertes, garde-fous IA (sans base)
php artisan test --filter=Renovation                     # tout le module (base gestion_eau_test, voir phpunit.xml)
```

Les tests Feature chargent `routes/renovation.php` eux-mêmes si `routes/web.php` ne le fait pas encore.

## 6. Intégration dans le projet commun

Modifications de fichiers **partagés** proposées (non appliquées) : `docs/binome-3-modifications-partagees.diff`.

| Fichier | Nécessaire ? | Effet |
|---|---|---|
| `routes/web.php` | **Oui — appliqué le 18/10/2026 (accord du binôme)** | supprime la page provisoire « projets » et charge `routes/renovation.php` |
| `admin/partials/sidebar.blade.php` | Conseillé | liens Financements, Tableau financier, What If? (sinon : onglets internes du module) |
| `front/partials/header.blade.php` | **Appliqué le 18/10/2026** | lien « Rénovation » vers la page citoyenne |
| `ReseauEauController::destroy` (binôme 1) | Conseillé | message clair au lieu d'une erreur SQL si le réseau a des projets |
| `DatabaseSeeder` | Facultatif | données de démonstration du module |
| `DashboardController` | Facultatif (décision de groupe) | remplace les projets inventés du tableau de bord par les vrais |

Ensuite, sur une base de développement :

```bash
php artisan migrate                                   # crée seulement les 3 nouvelles tables
php artisan db:seed --class=RenovationSeeder          # données de démonstration (facultatif)
php artisan db:seed --class=RenovationTunisieSeeder  # 24 projets publiés, un par gouvernorat (démonstration)
```

## Innovations (aucune table ajoutée ni modifiée)

| Innovation | Où | Fichiers |
|---|---|---|
| Score relié aux modules Surveillance et Qualité de l'eau (lecture seule de leurs tables) | fiches et listes de projets | `SignauxTerrain`, `ScorePriorite` |
| Équité territoriale : DT/habitant par gouvernorat, courbe de Lorenz, indice de Gini, carte des 24 gouvernorats | `/admin/financement/territoires` | `EquiteTerritoriale`, `TerritoiresController` |
| Stratégie **E « Équité territoriale »** dans le What If? | simulateur | `MoteurWhatIf::strategieEquite` |
| Test de robustesse (Monte-Carlo, 1 000 tirages, graine fixe) : part des cas où le scénario tient si les coûts varient de ±X % | simulateur | `RobustesseScenario` |
| Plan pluriannuel (1 à 5 ans, report du reliquat) | `/admin/what-if/pluriannuel` | `PlanPluriannuel` |
| Alerte F10 « montant atypique » | tableau financier, rapport | `DetectionRisquesFinanciers::montantsAtypiques` |
| Export Excel (CSV `;` + BOM, formules neutralisées) et import tout-ou-rien avec rapport d'erreurs | `/admin/financements/export`, `/admin/financements/import` | `EchangeFinancementsController` |
| Rapport mensuel PDF : chiffres calculés + synthèse IA validée (ou mode secours) | bouton du tableau financier | `RapportMensuel`, `AnalyseFinancementIA::syntheseMensuelle` |
| Panneau de chantier A4 avec QR code vers la page publique | fiche projet › « Panneau de chantier » | `projets/panneau.blade.php` |
| Observatoire citoyen bilingue français / arabe (de droite à gauche, choix mémorisé) | `/renovation?langue=ar` | `lang/fr/observatoire.php`, `lang/ar/observatoire.php` |

## Modèles IA entraînés (page `/admin/financement/modeles-ia`, gestionnaire et administrateur)

Deux modèles d'**apprentissage automatique** codés en PHP sans bibliothèque (`app/Services/Renovation/Apprentissage/`),
enregistrés dans `storage/app/private/modeles/` (fichiers non versionnés, aucune table) :

| Modèle | Algorithme | Variables | Utilisé pour |
|---|---|---|---|
| Coût d'un projet sans devis | Régression Ridge ou k plus proches voisins (le meilleur est retenu) | type, matériau, âge, état du réseau, population | 3ᵉ niveau de coût : devis › **prédiction ± marge** › barème (fiche projet, What If?, score) |
| Source de financement | Classifieur bayésien naïf (lissage de Laplace) | type, état, taille du projet, région | Carte « Source à solliciter » (fiche projet) et suggestion à l'approbation d'un scénario |

- **Évaluation** : validation croisée « un contre tous », comparée à la méthode simple (barème ; « toujours la source la plus fréquente »).
  Un modèle qui ne fait pas mieux est **désactivé automatiquement**. Contrôle d'honnêteté séparé sur les seuls devis de la base.
- **Données** : la base + un **historique synthétique documenté** de 150 projets (`JeuEntrainementSynthetique`, graine fixe,
  règles affichées sur la page), car les données de démonstration saisies à la main n'ont aucune régularité.
  Avec de vrais devis : `RENOVATION_JEU_SYNTHETIQUE=false` dans le `.env`, puis réentraîner.
- **Entraînement** : bouton « Réentraîner » de la page ou `php artisan renovation:entrainer` (à lancer une fois après installation).

## 7. Démonstration pour le jury (5 minutes)

1. **Tableau financier** : montants réels par source, couverture calculée seulement sur les devis, alertes F1-F9,
   « Expliquer ces alertes » (IA ou mode secours).
2. **Fiche projet** : score de priorité décomposé, réseau et son score de risque, financements par source.
3. **What If?** : faire glisser le budget → les 4 stratégies se recalculent en direct ; cliquer une colonne du
   comparatif ; montrer avant/après, raisons par projet, risques, hypothèses ; « Analyse IA ».
4. **Enregistrer et soumettre** → se connecter en admin → approuver (source : État) → ajouter un financement
   sur un projet concerné → l'application est **refusée** (données changées) → relancer une simulation → appliquer.
5. **Rapport PDF** de la décision, puis **page citoyenne** `/renovation`.
