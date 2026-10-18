# AquaSecure — Binôme 2 : Gestion des incidents et des interventions

Module Laravel 12 du back office AquaSecure : suivi des **incidents** techniques confirmés sur un réseau d'eau
(fuite, contamination, coupure) et des **interventions** de réparation, avec un **score de priorité explicable**
et des **statistiques** calculées à partir de la base.

---

## 1. Architecture

Le module suit les conventions du projet : contrôleurs `Admin/`, FormRequest avec messages en français,
logique métier dans `app/Services/`, constantes `valeur => libellé` dans les modèles, vues Blade + Tabler.

| Couche | Fichiers |
|---|---|
| Migrations | `database/migrations/2026_10_17_100001_create_incidents_table.php`, `…100002_create_interventions_table.php` (+ `historique_interventions`) |
| Modèles | `app/Models/Incident.php`, `Intervention.php`, `HistoriqueIntervention.php` |
| Règles métier | événements Eloquent des modèles + `app/Exceptions/RegleIncidentException.php` |
| Validation | `app/Http/Requests/IncidentRequest.php`, `InterventionRequest.php`, `StatutInterventionRequest.php` |
| Autorisations | `app/Policies/IncidentPolicy.php`, `InterventionPolicy.php` (découverte automatique par Laravel) |
| Contrôleurs | `app/Http/Controllers/Admin/IncidentController.php`, `InterventionController.php`, `IncidentAnalyseController.php` |
| Services | `app/Services/PrioriteIncident.php` (score), `StatistiquesIncidents.php` |
| IA (Machine Learning) | `app/IA/Incidents/IsolationForest.php`, `PreparationDonnees.php`, `AnalyseAnomalies.php`, `app/Console/Commands/AnalyserIncidents.php`, `app/Http/Controllers/Admin/IncidentIAController.php`, vue `admin/incidents/ia.blade.php` |
| API JSON | `app/Http/Resources/IncidentResource.php`, `InterventionResource.php` |
| Routes | `routes/incidents.php` (chargé depuis `routes/web.php`, voir § 9) |
| Vues | `resources/views/admin/incidents/*`, `resources/views/admin/interventions/*` |
| Données de démonstration | `database/factories/IncidentFactory.php`, `InterventionFactory.php`, `database/seeders/IncidentSeeder.php` |
| Tests | `tests/Unit/RegleStatutTest.php`, `PrioriteIncidentTest.php`, `tests/Feature/IncidentInterventionModeleTest.php`, `IncidentHttpTest.php`, `InterventionHttpTest.php`, `PrioriteStatistiquesTest.php` |

Aucun fichier d'un autre binôme n'est modifié. Le modèle `ReseauEau` est utilisé **en lecture seule**
(code, zone, état, date de mise en service).

---

## 2. Modèle de données

```
ZONE 1 ── N RESEAU_EAU 1 ── N INCIDENT 1 ── N INTERVENTION 1 ── N HISTORIQUE_INTERVENTION N ── 1 USER
                        (reseau_id)      (incident_id)        (intervention_id)               (user_id)
```

| Table | Colonnes | Clés étrangères |
|---|---|---|
| `incidents` | id, reseau_id, type, gravite, date_signalement, timestamps | `reseau_id` → `reseaux_eau.id`, **restrictOnDelete** |
| `interventions` | id, incident_id, statut, date_intervention, timestamps | `incident_id` → `incidents.id`, **restrictOnDelete** |
| `historique_interventions` | id, intervention_id, ancien_statut (null = création), nouveau_statut, user_id, timestamps | `intervention_id` cascade, `user_id` nullOnDelete |

Le **statut d'un incident n'est pas stocké** : il est calculé à partir de ses interventions.

| Interventions de l'incident | Statut |
|---|---|
| aucune | Non traité |
| au moins une planifiée ou en cours | En traitement |
| au moins une, toutes terminées | Résolu |

---

## 3. Règles métier

Les règles sont vérifiées **à deux niveaux** : dans les FormRequest (messages clairs dans les formulaires)
et dans les événements Eloquent des modèles (aucun chemin ne peut les contourner : formulaire, API, seeder, tinker).

### Incident

| # | Règle |
|---|---|
| I1 | Réseau existant ; type ∈ {fuite, contamination, coupure} ; gravité ∈ {faible, moyenne, critique} ; date de signalement obligatoire, jamais dans le futur. |
| I2 | La date de signalement ne précède pas la mise en service du réseau. |
| I3 | Dès qu'une intervention a **commencé** (en cours ou terminée), le réseau de l'incident ne peut plus changer. |
| I4 | Type et gravité restent modifiables (requalification après diagnostic). |
| I5 | Un incident qui a des interventions ne peut pas être supprimé (contrôleur + modèle + clé étrangère). |
| I6 | Avertissement (non bloquant) si un incident non résolu du même type existe sur le même réseau à ± 7 jours. |
| I7 | Alerte : incident critique sans aucune intervention, signalé depuis au moins 1 jour. |

### Intervention

| # | Règle |
|---|---|
| T1 | Créée depuis un incident existant ; son incident ne change jamais. |
| T2 | Sa date ne précède jamais le signalement de l'incident. |
| T3 | Planifiée : date aujourd'hui ou plus tard (une planifiée dont la date passe est signalée « en retard »). |
| T4 | En cours / terminée : date jamais dans le futur. |
| T5 | Le statut change par une action dédiée, selon les transitions ci-dessous ; chaque changement est historisé. |
| T6 | En passant à « en cours » ou « terminée », une date prévue dans le futur devient la date du jour. |
| T7 | Une intervention terminée ne peut plus être modifiée. |
| T8 | Seule une intervention planifiée peut être supprimée (annulation). |
| T9 | Plusieurs interventions par incident ; une nouvelle intervention **rouvre** un incident résolu. |

```
planifiee ──► en_cours ──► terminee    (terminee est définitif, pas de retour en arrière)
    └──────────────────────►┘
```

---

## 4. Rôles et autorisations

Authentification **Fortify existante**, rôles existants, aucun système concurrent.

| Action | Citoyen | Gestionnaire | Admin |
|---|:-:|:-:|:-:|
| Consulter, créer, modifier, changer un statut | ✗ (403) | ✓ | ✓ |
| Supprimer un incident / une intervention | ✗ | ✗ (403) | ✓ (si les règles I5 / T8 le permettent) |

- Accès au module : middleware du groupe `admin` (`auth`, `verified`, `role:gestionnaire,admin`).
- Contrôles fins : Policies (`Gate::authorize`) et `authorize()` des FormRequest.
- Formulaires protégés par le jeton CSRF ; réponses JSON des pages de consultation protégées par la session.

---

## 5. Routes

Toutes préfixées par `/admin` et nommées `admin.*` (héritées du groupe `admin` de `routes/web.php`).

| Méthode | URL | Nom | Rôle |
|---|---|---|---|
| GET | `/admin/incidents` | `admin.incidents.index` | Liste : recherche, filtres, tri, pagination (15) |
| GET | `/admin/incidents/create` | `admin.incidents.create` | Formulaire de création |
| POST | `/admin/incidents` | `admin.incidents.store` | Création |
| GET | `/admin/incidents/{incident}` | `admin.incidents.show` | Fiche : statut, score détaillé, interventions, doublons |
| GET | `/admin/incidents/{incident}/edit` | `admin.incidents.edit` | Formulaire de modification |
| PUT | `/admin/incidents/{incident}` | `admin.incidents.update` | Modification |
| DELETE | `/admin/incidents/{incident}` | `admin.incidents.destroy` | Suppression (admin) |
| GET | `/admin/incidents/priorites` | `admin.incidents.priorites` | Classement par priorité |
| GET | `/admin/incidents/statistiques` | `admin.incidents.statistiques` | Tableau de bord statistique |
| GET | `/admin/incidents/statistiques/donnees` | `admin.incidents.statistiques.donnees` | Statistiques en JSON |
| GET | `/admin/incidents/analyse-ia` | `admin.incidents.ia` | Page « IA — Analyse intelligente des incidents » (JSON avec `Accept: application/json`) |
| POST | `/admin/incidents/analyse-ia` | `admin.incidents.ia.lancer` | Lance une analyse (5 par minute au plus) |
| GET | `/admin/incidents/{incident}/interventions/create` | `admin.incidents.interventions.create` | Nouvelle intervention |
| POST | `/admin/incidents/{incident}/interventions` | `admin.incidents.interventions.store` | Création |
| GET | `/admin/interventions` | `admin.interventions.index` | Liste : filtres statut, type, incident, période, retard |
| GET | `/admin/interventions/historique` | `admin.interventions.historique` | Historique de tous les changements de statut |
| GET | `/admin/interventions/{intervention}` | `admin.interventions.show` | Suivi, changement de statut, historique |
| GET | `/admin/interventions/{intervention}/edit` | `admin.interventions.edit` | Modification de la date |
| PUT | `/admin/interventions/{intervention}` | `admin.interventions.update` | Modification |
| PATCH | `/admin/interventions/{intervention}/statut` | `admin.interventions.statut` | Changement de statut |
| DELETE | `/admin/interventions/{intervention}` | `admin.interventions.destroy` | Suppression (admin, planifiée) |

**Paramètres de la liste des incidents** : `q` (n° d'incident, code réseau, zone, commune), `type`, `gravite`,
`statut` (`non_traite`, `en_traitement`, `resolu`), `reseau`, `zone`, `du`, `au` (AAAA-MM-JJ), `critiques=1`,
`tri` (`date`, `gravite`, `priorite`), `ordre` (`asc`, `desc`), `page`. Les valeurs invalides sont ignorées.

### Exemples JSON

Les pages de consultation renvoient du JSON avec l'en-tête `Accept: application/json` (session connectée requise).

```http
GET /admin/incidents?gravite=critique&statut=non_traite
Accept: application/json
```

```json
{
  "data": [{
    "id": 12,
    "reseau": { "id": 3, "code": "CAN-TUN-003", "etat": "hors_service", "zone": "Cité El Khadra" },
    "type": { "valeur": "contamination", "libelle": "Contamination" },
    "gravite": { "valeur": "critique", "libelle": "Critique" },
    "date_signalement": "2026-10-13",
    "statut": { "valeur": "non_traite", "libelle": "Non traité" },
    "interventions": [],
    "priorite": {
      "score": 87, "niveau": { "cle": "urgente", "libelle": "Urgente", "couleur": "red", "seuil": 70 },
      "facteurs": [
        { "cle": "gravite", "libelle": "Gravité", "points": 40, "max": 40, "explication": "Gravité critique" }
      ],
      "action": "Planifier une intervention immédiatement : incident critique non pris en charge. …",
      "resolu": false
    }
  }],
  "links": { "…": "…" },
  "meta": { "current_page": 1, "per_page": 15, "total": 1 }
}
```

*(Exemple illustratif : les valeurs dépendent des données présentes en base.)*

```http
GET /admin/incidents/statistiques/donnees
```

Renvoie `total`, `parType`, `parGravite`, `parStatut`, `parZone`, `evolution` (12 mois), `tauxResolution`,
`delaiPriseEnCharge`, `dureeResolution`, `interventions`, `interventionsEnRetard`, `critiquesSansIntervention`.

---

## 6. Priorisation explicable

`app/Services/PrioriteIncident.php` : **moteur de règles métier**, pas un modèle d'apprentissage automatique.
Les poids sont fixés par expertise ; chaque point est justifié et affiché.

| Règle | Barème | Max |
|---|---|---|
| Gravité | faible 10 · moyenne 25 · critique 40 | 40 |
| Type | fuite 10 · coupure 15 · contamination 25 (risque sanitaire) | 25 |
| Ancienneté | 1 point par tranche de 2 jours depuis le signalement | 15 |
| Prise en charge | aucune intervention 15 · planifiée en retard 12 · planifiée 8 · en cours 3 | 15 |
| État du réseau | hors service 5 · dégradé 3 · bon 0 (colonne réelle `reseaux_eau.etat`) | 5 |

Niveaux : **Urgente** ≥ 70 · **Haute** 45–69 · **Normale** 25–44 · **Basse** < 25. Un incident résolu sort du classement.

La page **Priorités** affiche : le classement, le détail des points, la raison de chaque priorité, l'action
recommandée, les critiques non pris en charge et les **réseaux à incidents récurrents** (≥ 3 incidents sur 12 mois).

**Pourquoi pas un modèle prédictif ?** Il n'existe aucun historique réel d'incidents. Entraîner un modèle sur
les données de démonstration reviendrait à lui faire apprendre nos propres règles : aucun résultat ne serait
défendable. Un modèle de prédiction du risque deviendra pertinent quand plusieurs années d'incidents réels seront
disponibles (données, entraînement, métriques et validation à prévoir à ce moment-là).

---

## 7. Statistiques

`app/Services/StatistiquesIncidents.php`, toutes calculées par des requêtes sur la base :

- total, répartition par type, par gravité, par statut calculé, par zone (10 premières) ;
- évolution mensuelle sur 12 mois (dont critiques) ;
- taux de résolution ;
- délai moyen de prise en charge : du signalement à la première intervention **commencée** ;
- durée moyenne de résolution : du signalement à la dernière intervention des incidents résolus ;
- interventions planifiées en retard ; critiques sans intervention.

Quand une donnée n'existe pas encore, la page affiche « — » ou un état vide : jamais de chiffre inventé.

**Filtres** (page et JSON) : `du`, `au` (date de signalement, AAAA-MM-JJ) et `type`. Ils s'appliquent à tous les
indicateurs ; les interventions comptées sont celles des incidents filtrés. Sans période, l'évolution couvre les
12 derniers mois ; avec une période, les mois de la période (24 au plus, les plus récents). Une date de fin antérieure
à la date de début est ignorée avec un message (page) ou refusée avec une erreur 422 (JSON).

---

## 7 bis. Analyse IA des incidents (Machine Learning)

**Distincte du score de priorité** : le score (§ 6) applique des règles fixes ; l'analyse IA est un modèle
d'apprentissage **non supervisé** qui repère des comportements statistiquement inhabituels.

### Choix du modèle

| Option | Besoin en données | Verdict |
|---|---|---|
| Random Forest (supervisé) | Une variable cible réelle et fiable (ex. « incident grave dans les 30 jours »), des centaines d'exemples étiquetés | Écarté : aucune étiquette réelle ; le modèle apprendrait le hasard des données de démonstration |
| Prévision temporelle | Plusieurs années d'historique mensuel régulier | Écarté pour l'instant |
| **Isolation Forest** (non supervisé) | Des observations numériques, sans étiquette | **Retenu**, protégé par des critères de suffisance |

Implémentation en PHP (`app/IA/Incidents/IsolationForest.php`), comme l'arbre de décision du binôme 5 : aucune
dépendance Python à installer le jour de la démonstration. Tirage aléatoire reproductible (graine fixe).

### Données (lecture seule)

Une observation = **un réseau pendant un mois** (au plus 24 mois). Variables : incidents du mois, incidents critiques,
part de contaminations, écart avec la moyenne des 3 mois précédents, délai moyen de prise en charge (médiane si
aucune intervention commencée), relevés de capteurs hors seuil (tables `capteurs` / `releves` du binôme 4, lues sans
modification). Les caractéristiques fixes du réseau (âge, matériau, état) ne sont pas utilisées.

### Critères de suffisance

Le modèle **n'est pas lancé** si l'un de ces critères n'est pas rempli : 30 incidents, 6 mois couverts, 3 réseaux
ayant des incidents, 36 observations. La page affiche alors les valeurs réelles et ce qui manque.

### Résultats et explication

- Score d'anomalie entre 0 et 1 ; signalé à partir de 0,6, « fort » à partir de 0,7.
- Pour chaque observation signalée : les 3 variables les plus éloignées de leur valeur habituelle (médiane, échelle
  robuste), en phrases, et des recommandations (inspection, analyses de contrôle, planification, capteurs).
- **Aucune action automatique** : une anomalie n'est pas un incident confirmé.

### Évaluation

Sans anomalies étiquetées, **précision, rappel et F1 ne peuvent pas être mesurés** (et ne sont jamais affichés).
L'analyse mesure la **stabilité** : 5 analyses avec d'autres graines, recouvrement des observations signalées
(indice de Jaccard). Les tests automatiques vérifient l'algorithme sur des données synthétiques construites pour
cela (un point isolé, un mois injecté) : ils valident le code, pas la performance sur données réelles.

### Lancement

Bouton « Lancer l'analyse » sur la page, ou `php artisan incidents:analyser`. Résultat enregistré dans
`storage/app/private/incidents-ia/analyse.json` (non versionné). Aucune écriture en base.

**Limites** : faux positifs (mois rares mais normaux), faux négatifs (dérives lentes), modèle descriptif (il ne prédit
pas l'avenir), résultats sans valeur métier tant que la base ne contient que des données de démonstration.

---

## 7 ter. Site citoyen : « Incidents et travaux »

Même démarche que les binômes 4 (Surveillance) et 5 (Qualité de l'eau) : contrôleur `Front/IncidentPublicController`,
vues `front/incidents/*` (Bootstrap 5, Bootstrap Icons, Leaflet, layout `layouts.front`), consultation réservée aux
utilisateurs connectés (`auth` + `verified`), citoyens compris.

| Page | URL | Nom de route |
|---|---|---|
| Liste, carte, filtres (commune, type, situation), bons réflexes | `/incidents` | `incidents.index` |
| Fiche : état, étapes du suivi, ce que cela change, emplacement | `/incidents/{incident}` | `incidents.show` |

**Logique d'affichage pour l'habitant**

- Visibles : incidents non résolus, et résolus depuis moins de 30 jours (`Incident::visiblesPublic()`) ; au-delà : 404.
- États traduits : *Signalé — intervention à programmer*, *Intervention programmée* (avec la date si elle est à venir),
  *Réparation en cours*, *Résolu* (avec la date de réparation). Gravité traduite en *impact faible / modéré / important*.
- **Jamais affiché** : score de priorité, analyse IA, statistiques internes, historique, auteurs, retards
  (une date prévue dépassée devient « date d'intervention en cours de confirmation »).
- Consignes par type (coupure, contamination, fuite) ; lien « Signaler » réservé aux citoyens (module Participation citoyenne).
- Position : celle du réseau, sinon le centre de la commune (indiqué comme approximatif).

---

## 8. Installation et lancement (poste Windows + XAMPP)

Prérequis du projet (README § 1) : PHP 8.2, Composer 2, MySQL de XAMPP démarré.

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Créer les deux bases si elles n'existent pas (README § 2.5) : `gestion_eau` et `gestion_eau_test`.

```bash
# Base vide (première installation) : crée toutes les tables et les données de démonstration du groupe
php artisan migrate --seed
# Base existante : ajoute seulement les 3 tables du module, sans rien effacer
php artisan migrate

# Données de démonstration du module (fictives ; ne fait rien si des incidents existent déjà)
php artisan db:seed --class=IncidentSeeder

php artisan serve
```

Ouvrir http://127.0.0.1:8000/admin/incidents — compte `gestionnaire@aquasecure.tn` / `password`
(ou `admin@aquasecure.tn` pour tester les suppressions).

⚠️ Ne pas utiliser `migrate:fresh` sur une base contenant des données à conserver : la commande efface toutes les tables.

---

## 9. Intégration dans `routes/web.php` (fichier partagé)

Laravel ne charge pas automatiquement `routes/incidents.php`. Modification à faire **une seule fois**, dans le groupe `admin` :

```diff
     Route::resource('releves', ReleveController::class)
         ->except('show')
         ->parameters(['releves' => 'releve']);

+    // Exploitation (binôme 2) : incidents et interventions
+    require __DIR__.'/incidents.php';
+
     // Pages provisoires : chaque ligne sera remplacée par un contrôleur CRUD
     $modules = [
         // url             nom de route      titre                     section             icône           description
-        ['incidents',      'incidents',      'Incidents',              'Exploitation',     'alert-triangle', 'Fuites, contaminations et coupures signalées.'],
-        ['interventions',  'interventions',  'Interventions',          'Exploitation',     'tool',           'Équipes envoyées sur le terrain et suivi des réparations.'],
         ['projets',        'projets',        'Projets de rénovation',  'Rénovation',       'building',       'Projets de rénovation et de décontamination, budget et financement.'],
     ];
```

Le menu latéral n'a pas besoin d'être modifié : il pointe déjà vers `admin.incidents.index` et `admin.interventions.index`.

---

## 10. Tests

Les tests utilisent **uniquement** la base `gestion_eau_test` (réglée dans `phpunit.xml`), vidée à chaque test
par `RefreshDatabase`. La base `gestion_eau` n'est jamais touchée.

```bash
php artisan test --filter=RegleStatutTest                 # transitions et statut calculé (sans base)
php artisan test --filter=PrioriteIncidentTest            # score de priorité (sans base)
php artisan test --filter=IncidentInterventionModeleTest  # modèles, relations, règles, clés étrangères
php artisan test --filter=IncidentHttpTest                # pages, rôles, validation, filtres, tri, pagination, JSON
php artisan test --filter=InterventionHttpTest            # création, dates, transitions, historique, suppressions
php artisan test --filter=PrioriteStatistiquesTest        # classement, récurrence, statistiques (et leurs filtres)
php artisan test --filter=IsolationForestTest             # algorithme Isolation Forest (sans base)
php artisan test --filter=AnalyseIncidentsIATest          # analyse IA : diagnostic, suffisance, détection, page
php artisan test --filter=IncidentPublicTest              # site citoyen : visibilité, états traduits, aucune info interne
php artisan test                                          # tout le projet (vérifie qu'aucun autre module n'est cassé)
```

Tant que la ligne `require __DIR__.'/incidents.php'` n'est pas dans `routes/web.php`, les tests HTTP sont
signalés **ignorés** (skipped) au lieu d'échouer.

---

## 11. Scénario de démonstration (≈ 10 minutes)

Préparation : `php artisan db:seed --class=IncidentSeeder` (données fictives), connexion en **gestionnaire**.

1. **Liste des incidents** (`/admin/incidents`) — indicateurs cliquables ; recherche « Tunis » ; filtre
   Gravité = Critique ; tri par **Priorité** ; montrer la pagination et le badge de score sur chaque ligne.
2. **Création d'un incident** — bouton « Nouvel incident » ; montrer la validation (date future refusée) ;
   choisir un réseau qui a déjà une fuite récente : **avertissement de doublon** après l'enregistrement.
3. **Fiche et statut calculé** — statut « Non traité » expliqué ; score sur 100 décomposé en 5 règles ;
   action recommandée ; pour un critique ancien, l'alerte rouge « non pris en charge ».
4. **Création d'une intervention** — depuis la fiche, statut « Planifiée », date de demain ; le statut de
   l'incident devient « En traitement » et son score baisse (prise en charge).
5. **Changement de statut et historique** — « Démarrer l'intervention » puis « Marquer comme terminée » ;
   montrer l'historique (qui, quand) ; l'incident passe « Résolu » ; tenter un retour en arrière : refusé.
   Se connecter en **admin** pour montrer qu'une intervention terminée ne peut pas être supprimée.
6. **Page Priorités** — classement, barème affiché, critiques non pris en charge, réseaux à incidents
   récurrents ; insister : *moteur de règles explicable, pas d'apprentissage automatique*.
7. **Statistiques** — indicateurs, filtres période / type, graphiques (« Voir les données »), lien « Données JSON ».
8. **Analyse IA** — montrer d'abord le diagnostic des données et les critères de suffisance ; lancer l'analyse ;
   pour une observation signalée : score, explication, recommandations, courbe du réseau ; insister sur la
   différence avec le score de priorité, sur l'absence d'action automatique et sur l'évaluation par stabilité.

---

## 12. Limites connues

- Le score repose sur des poids fixés par expertise, non validés sur des données réelles.
- Le tri par priorité calcule le score en PHP pour tous les incidents filtrés avant de paginer :
  adapté à quelques milliers d'incidents, à revoir (score stocké ou calculé en SQL) au-delà.
- `date_signalement` est une date sans heure : l'alerte « critique depuis 24 h » est calculée en jours.
- Supprimer une intervention planifiée (annulation) supprime aussi son historique (cascade).
- Les données de `IncidentSeeder` sont **fictives** ; leurs lignes d'historique portent la date d'exécution du seeder.
- **Module Infrastructure** : `ReseauEauController::destroy()` ne vérifie pas les incidents. Supprimer un réseau
  qui a des incidents est refusé par la base (`restrictOnDelete`) mais affiche une erreur SQL au lieu d'un message.
  Correctif proposé au binôme Infrastructure (leur fichier, non modifié ici) :

  ```php
  if (\App\Models\Incident::where('reseau_id', $reseau->id)->exists()) {
      return back()->withErrors([
          'reseau' => "Le réseau {$reseau->code} a des incidents enregistrés : il ne peut pas être supprimé.",
      ]);
  }
  ```
- Le tableau de bord commun affiche encore des chiffres d'incidents fictifs (`DashboardController`) :
  à brancher sur `StatistiquesIncidents` avec l'accord du groupe.
