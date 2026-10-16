# AquaSecure

Gestion des infrastructures d'eau potable en Tunisie : zones, réseaux d'eau, analyse des risques,
qualité de l'eau (points de prélèvement et analyses de laboratoire), incidents, interventions…

Projet Laravel 12 · MySQL · template Tabler · cartes Leaflet · graphiques ApexCharts · PDF avec dompdf.

---

## 1. Prérequis

| Outil | Version | Remarque |
|---|---|---|
| PHP | 8.2 ou plus | celui de XAMPP convient (`C:\xampp\php`) |
| Composer | 2.x | https://getcomposer.org |
| MySQL / MariaDB | — | celui de XAMPP |
| Git | — | |
| Connexion Internet | — | Tabler, Leaflet et ApexCharts sont chargés par CDN |

Extensions PHP nécessaires (activées par défaut dans XAMPP) : `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`.

Vérifier :

```bash
php -v
composer -V
```

> Pas besoin de Node.js / npm : les styles et scripts sont chargés par CDN.

---

## 2. Installation (une seule fois)

### 2.1 Récupérer le projet

```bash
git clone https://github.com/takoua-eng/Aqua-Secure.git
cd Aqua-Secure
```

### 2.2 Installer les dépendances PHP

```bash
composer install
```

### 2.3 Créer le fichier `.env`

```bash
copy .env.example .env        # Windows (cmd / PowerShell)
# cp .env.example .env        # Git Bash, Linux, macOS
php artisan key:generate
```

Vérifier la partie base de données du `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestion_eau
DB_USERNAME=root
DB_PASSWORD=
```

> ⚠️ Le fichier `.env` contient des mots de passe : il ne doit **jamais** être commité (il est déjà dans `.gitignore`).

### 2.4 Démarrer MySQL

Ouvrir le **panneau de contrôle XAMPP** et cliquer sur **Start** en face de **MySQL**.

### 2.5 Créer les bases de données

Dans phpMyAdmin (http://localhost/phpmyadmin, onglet SQL) ou en ligne de commande :

```sql
CREATE DATABASE gestion_eau      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE gestion_eau_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

- `gestion_eau` : base de l'application ;
- `gestion_eau_test` : base utilisée par les tests (elle est vidée à chaque test).

En ligne de commande avec XAMPP :

```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE gestion_eau CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE gestion_eau_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 2.6 Créer les tables et les données de démonstration

```bash
php artisan migrate:fresh --seed
```

> `migrate:fresh` **supprime toutes les tables** puis les recrée : à utiliser seulement sur une base de développement.

Les données de démonstration créées :

- 3 comptes de test + 10 citoyens fictifs ;
- 10 zones et leurs réseaux d'eau (`InfrastructureSeeder`) ;
- 10 points de prélèvement et environ 80 analyses sur 8 mois, dont certaines non conformes (`QualiteEauSeeder`).

---

## 3. Lancer le projet

```bash
php artisan serve
```

Puis ouvrir **http://127.0.0.1:8000**

- Site public : http://127.0.0.1:8000
- Connexion : http://127.0.0.1:8000/connexion
- Back office : http://127.0.0.1:8000/admin

À chaque nouvelle séance de travail : démarrer MySQL dans XAMPP, puis `php artisan serve`.

### Comptes de test

| Rôle | Email | Mot de passe | Accès |
|---|---|---|---|
| Administrateur | `admin@aquasecure.tn` | `password` | tout le back office + gestion des utilisateurs |
| Gestionnaire | `gestionnaire@aquasecure.tn` | `password` | back office |
| Citoyen | `citoyen@aquasecure.tn` | `password` | site public, signalements |

---

## 4. Modules

| Section | Pages | Adresse |
|---|---|---|
| Infrastructure | Zones, Réseaux (import CSV), Analyse des risques | `/admin/zones`, `/admin/reseaux`, `/admin/risques` |
| Qualité de l'eau | Points de prélèvement, Analyses | `/admin/prelevements`, `/admin/analyses` |
| Administration | Utilisateurs (admin uniquement) | `/admin/users` |

### Qualité de l'eau (binôme 5)

- **Points de prélèvement** : liste avec recherche et filtres, formulaire avec carte (un clic place le point),
  fiche avec taux de conformité, graphique d'évolution d'un paramètre et historique des analyses.
- **Analyses** : liste avec filtres et indicateurs, valeurs hors norme en rouge, indicateur de conformité
  en direct pendant la saisie, bulletin d'analyse et export PDF.
- **Conformité calculée automatiquement** (seuils OMS / NT 09.14) :

  | Paramètre | Norme |
  |---|---|
  | pH | entre 6,5 et 8,5 |
  | Chlore résiduel | entre 0,1 et 1 mg/L |
  | Turbidité | ≤ 5 NTU |
  | Nitrates | ≤ 50 mg/L |
  | Bactéries coliformes | 0 UFC/100 mL |

- Un point qui a des analyses ne peut pas être supprimé (on le met hors service) ;
  un point hors service ne reçoit plus de nouvelles analyses.

#### Modèle d'intelligence artificielle (sans API, codé en PHP : `app/IA/ArbreDecision.php`)

**Détection précoce de contamination bactérienne** : un arbre de décision (algorithme CART, indice de Gini),
entraîné sur l'historique des analyses, estime le risque de présence de coliformes à partir des mesures
faites sur place (pH, chlore, turbidité, nitrates), du type de point et de la saison, sans attendre
les 24 à 48 h de culture en laboratoire.

Où le voir : formulaire d'analyse (prédiction en direct), bulletin d'analyse (prédiction comparée au laboratoire),
page **Qualité de l'eau → Détection de contamination** (performances, matrice de confusion, règles apprises).

Le modèle de détection est entraîné automatiquement par `php artisan migrate:fresh --seed`. Pour le réentraîner :

```bash
php artisan qualite:entrainer
```

ou bouton « Réentraîner le modèle » sur la page **Qualité de l'eau → Détection de contamination**.
Le modèle est enregistré dans `storage/app/private/modeles/` (non versionné : chacun le génère chez soi).

---

## 5. Tests

Les tests utilisent la base **`gestion_eau_test`** (réglée dans `phpunit.xml`) : la base `gestion_eau` n'est jamais touchée.

```bash
php artisan test                          # tous les tests
php artisan test --filter=QualiteEauTest  # seulement le module Qualité de l'eau
```

---

## 6. Rapport IA (facultatif)

La page « Analyse des risques » peut rédiger un rapport de zone avec Claude.
Sans clé, un rapport de démonstration est généré automatiquement. Pour utiliser l'IA, ajouter dans `.env` :

```env
ANTHROPIC_API_KEY=votre_cle
ANTHROPIC_MODEL=claude-opus-5-5
```

---

## 7. Problèmes fréquents

| Message | Solution |
|---|---|
| `SQLSTATE[HY000] [2002] … refusée` | MySQL n'est pas démarré : cliquer sur **Start** dans XAMPP. |
| `SQLSTATE[HY000] [1049] Unknown database 'gestion_eau'` | Créer la base (étape 2.5). |
| `Base table or view not found` | Lancer `php artisan migrate:fresh --seed`. |
| `No application encryption key has been specified` | Lancer `php artisan key:generate`. |
| `vendor/autoload.php` introuvable | Lancer `composer install`. |
| Erreur 403 « Accès non autorisé pour votre rôle » | Le compte citoyen n'a pas accès au back office : se connecter en gestionnaire ou admin. |
| Le `.env` a été modifié mais rien ne change | Lancer `php artisan config:clear`. |
| Carte ou graphiques vides | Vérifier la connexion Internet (bibliothèques chargées par CDN). |

---

## 8. Travail en groupe (Git)

Chaque binôme travaille sur sa propre branche, jamais directement sur `main` :

```bash
git checkout main
git pull
git checkout -b ma-branche
# … travail …
git add <fichiers>
git commit -m "Module … : description"
git push -u origin ma-branche
```

Puis ouvrir une Pull Request vers `main` sur GitHub.

Ne jamais commiter : `.env`, `vendor/`, `node_modules/`.
