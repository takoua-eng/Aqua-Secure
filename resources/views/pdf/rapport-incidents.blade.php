<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>AquaSecure — Rapport Incidents & Interventions</title>
    <style>
        @page {
            margin: 24px 30px 30px 30px;
        }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5px;
            line-height: 1.45;
            color: #1e293b;
        }
        .header-top {
            border-bottom: 2px solid #206bc4;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }
        .header-top table {
            margin: 0;
            padding: 0;
        }
        .header-logo {
            font-size: 18px;
            font-weight: bold;
            color: #206bc4;
            letter-spacing: -0.5px;
        }
        .header-tagline {
            font-size: 8.5px;
            color: #64748b;
        }
        .header-meta {
            text-align: right;
            font-size: 8.5px;
            color: #475569;
        }
        .titre-doc {
            background: #f0f7ff;
            border-left: 4px solid #206bc4;
            padding: 12px 14px;
            margin-bottom: 16px;
            border-radius: 0 4px 4px 0;
        }
        h1 {
            font-size: 15px;
            color: #0f172a;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .sous-titre {
            font-size: 10px;
            color: #3b82f6;
            font-weight: bold;
            margin: 0;
        }
        h2 {
            font-size: 11.5px;
            color: #1e3a8a;
            margin: 14px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #cbd5e1;
            text-transform: uppercase;
        }
        h3 {
            font-size: 10px;
            color: #0f172a;
            margin: 10px 0 4px 0;
        }
        p {
            margin: 0 0 6px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8.5px;
        }
        th {
            background: #f1f5f9;
            color: #334155;
            font-weight: bold;
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }
        td {
            padding: 4px 6px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .page-break {
            page-break-before: always;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-orange { background: #ffedd5; color: #9a3412; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #f1f5f9; color: #475569; }

        .kpi-container {
            margin-bottom: 12px;
        }
        .kpi-table td {
            text-align: center;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 6px;
        }
        .kpi-valeur {
            font-size: 15px;
            font-weight: bold;
            color: #1e40af;
        }
        .kpi-libelle {
            font-size: 7.5px;
            color: #64748b;
            text-transform: uppercase;
        }
        .encadre {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        .encadre-alerte {
            background: #fef2f2;
            border-left: 3px solid #dc2626;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        .pied {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7.5px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
        .script-box {
            background: #fafafa;
            border: 1px dashed #94a3b8;
            padding: 6px 8px;
            margin-bottom: 8px;
            border-radius: 3px;
        }
        .discours {
            font-style: italic;
            color: #0369a1;
        }
    </style>
</head>
<body>

    <div class="pied">
        AquaSecure — Rapport d'Exploitation (Incidents & Interventions) · Page 1/3 · Généré le {{ $dateGeneration }}
    </div>

    <!-- ENTETE -->
    <div class="header-top">
        <table style="border:none; margin:0;">
            <tr>
                <td style="border:none; padding:0; width:60%;">
                    <div class="header-logo">AquaSecure 💧</div>
                    <div class="header-tagline">Système Intégré de Surveillance & Exploitation des Réseaux Hydrauliques</div>
                </td>
                <td style="border:none; padding:0; text-align:right;">
                    <div class="header-meta">
                        <strong>Module :</strong> Exploitation (Incidents & Interventions)<br>
                        <strong>Technologie :</strong> Laravel 12 · MySQL · Isolation Forest<br>
                        <strong>Date :</strong> {{ $dateGeneration }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="titre-doc">
        <h1>Rapport Technique & Guide de Soutenance</h1>
        <div class="sous-titre">Documentation Complète du Module Incidents & Interventions</div>
    </div>

    <!-- SYNTHESE DU TRAVAIL -->
    <h2>1. Synthèse du Travail Réalisé</h2>
    <p>
        Dans le cadre du projet <strong>AquaSecure</strong>, j'ai développé l'intégralité du module <strong>Exploitation</strong>,
        chargé de garantir la continuité de service et la réactivité face aux défaillances du réseau d'eau potable.
        Le module s'articule autour de 4 composantes majeures :
    </p>
    <ul>
        <li><strong>Back-office Métier :</strong> Gestion des signalements, qualification de gravité, cycle d'intervention et historique d'audit.</li>
        <li><strong>Moteur de Priorisation Explicable (Score / 100) :</strong> Classement objectif et dynamique des urgences selon 5 axes pondérés.</li>
        <li><strong>Intelligence Artificielle (Isolation Forest) :</strong> Détection non-supervisée des comportements anormaux sur les couples (Réseau × Mois).</li>
        <li><strong>Portail Public Citoyen :</strong> Carte interactive (Leaflet) et consignes pratiques pour les habitants des communes touchées.</li>
    </ul>

    <!-- STATISTIQUES EN DIRECT -->
    <div class="kpi-container">
        <table class="kpi-table">
            <tr>
                <td width="20%"><div class="kpi-valeur">{{ $stats['total_incidents'] }}</div><div class="kpi-libelle">Total Incidents</div></td>
                <td width="20%"><div class="kpi-valeur" style="color:#dc2626;">{{ $stats['critiques'] }}</div><div class="kpi-libelle">Alertes Critiques</div></td>
                <td width="20%"><div class="kpi-valeur" style="color:#d97706;">{{ $stats['en_traitement'] }}</div><div class="kpi-libelle">En Traitement</div></td>
                <td width="20%"><div class="kpi-valeur" style="color:#16a34a;">{{ $stats['resolus'] }}</div><div class="kpi-libelle">Incidents Résolus</div></td>
                <td width="20%"><div class="kpi-valeur">{{ $stats['interventions'] }}</div><div class="kpi-libelle">Interventions Clôturées</div></td>
            </tr>
        </table>
    </div>

    <!-- MODELISATION ET REGLES METIER -->
    <h2>2. Modélisation Relationnelle & Règles d'Intégrité</h2>
    <table>
        <tr>
            <th width="25%">Entité / Table</th>
            <th width="35%">Champs Clés & Typage</th>
            <th width="40%">Règles d'Intégrité & Comportements</th>
        </tr>
        <tr>
            <td><strong>incidents</strong><br><small>Constat d'une anomalie</small></td>
            <td>
                <code>reseau_id</code> (FK, restrictOnDelete)<br>
                <code>type</code> (fuite, coupure, contamination)<br>
                <code>gravite</code> (faible, moyenne, critique)<br>
                <code>date_signalement</code> (date)
            </td>
            <td>
                <strong>Statut calculé :</strong> Non stocké en base, déduit des interventions pour supprimer toute redondance.<br>
                <strong>Règle I3 :</strong> Réseau verrouillé dès qu'une intervention a démarré.<br>
                <strong>Règle I6 :</strong> Détection temps réel des doublons potentiels (± 7 jours).<br>
                <strong>Règle I7 :</strong> Alerte automatique si critique &gt; 24h sans intervention.
            </td>
        </tr>
        <tr>
            <td><strong>interventions</strong><br><small>Opération technique terrain</small></td>
            <td>
                <code>incident_id</code> (FK, restrictOnDelete)<br>
                <code>statut</code> (planifiee, en_cours, terminee)<br>
                <code>date_intervention</code> (date)
            </td>
            <td>
                <strong>Machine à états finis :</strong> Planifiée &rarr; En cours &rarr; Terminée.<br>
                <strong>Règle T7 :</strong> Statut "Terminée" irréversible pour garantir l'inviolabilité.<br>
                <strong>Règle T8 :</strong> Seule une intervention "Planifiée" peut être annulée.<br>
                <strong>Transactions & Concurrence :</strong> Utilisation de <code>lockForUpdate()</code>.
            </td>
        </tr>
        <tr>
            <td><strong>historique_interventions</strong><br><small>Audit de traçabilité</small></td>
            <td>
                <code>intervention_id</code>, <code>user_id</code><br>
                <code>ancien_statut</code>, <code>nouveau_statut</code><br>
                <code>created_at</code> (horodatage)
            </td>
            <td>
                Enregistre automatiquement chaque événement de changement d'état avec l'identité de l'agent connecté pour conformité et audit qualité.
            </td>
        </tr>
    </table>

    <!-- PRIORISATION EXPLICABLE -->
    <h2>3. Moteur de Priorisation Explicable (Score sur 100)</h2>
    <p>
        Pour assister le répartiteur sans effet "boîte noire", un algorithme déterministe attribue un score d'urgence calculé sur 100 points :
    </p>
    <table>
        <tr>
            <th>Critère Évalué</th>
            <th>Pondération</th>
            <th>Barème et Justification Métier</th>
        </tr>
        <tr>
            <td><strong>Gravité de l'incident</strong></td>
            <td><strong>40 pts max</strong></td>
            <td>Faible : 10 pts · Moyenne : 25 pts · Critique : 40 pts (impact direct sur les usagers).</td>
        </tr>
        <tr>
            <td><strong>Typologie du sinistre</strong></td>
            <td><strong>25 pts max</strong></td>
            <td>Fuite : 10 pts · Coupure : 15 pts · Contamination : 25 pts (urgence sanitaire prioritaire).</td>
        </tr>
        <tr>
            <td><strong>Ancienneté sans résolution</strong></td>
            <td><strong>15 pts max</strong></td>
            <td>+1 point tous les 2 jours d'attente (pénalise l'inertie opérationnelle).</td>
        </tr>
        <tr>
            <td><strong>Statut de prise en charge</strong></td>
            <td><strong>15 pts max</strong></td>
            <td>Aucune : 15 pts · Planifiée en retard : 12 pts · Planifiée : 8 pts · En cours : 3 pts.</td>
        </tr>
        <tr>
            <td><strong>État physique du réseau</strong></td>
            <td><strong>5 pts max</strong></td>
            <td>Hors service : 5 pts · Dégradé : 3 pts · Bon état : 0 pt.</td>
        </tr>
    </table>

    <div class="page-break"></div>

    <div class="pied">
        AquaSecure — Rapport d'Exploitation (Incidents & Interventions) · Page 2/3 · Généré le {{ $dateGeneration }}
    </div>

    <!-- IA ISOLATION FOREST -->
    <h2>4. Intelligence Artificielle : Détection d'Anomalies (Isolation Forest)</h2>
    <p>
        Un algorithme de <strong>Machine Learning non supervisé</strong> (Forêt d'Isolation développée en PHP natif)
        analyse les séries historiques pour isoler les comportements atypiques sur le réseau sans nécessiter d'étiquetage manuel préalable.
    </p>

    <div class="encadre">
        <strong>Principes & Architecture de l'IA :</strong>
        <ul>
            <li><strong>Unité d'analyse :</strong> Couple <code>(Réseau d'eau × Mois calendaire)</code>.</li>
            <li><strong>Variables analysées :</strong> Volume total d'incidents, nombre de cas critiques, taux de contamination, dynamique d'aggravation vs trimestre précédent (hausse), et délai médian de réaction.</li>
            <li><strong>Hyperparamètres :</strong> 100 arbres décisionnels aléatoires, sous-échantillonnage de 64 observations, graine pseudo-aléatoire reproductible.</li>
            <li><strong>Garde-fous scientifiques :</strong> Si les critères de suffisance statistique ne sont pas réunis (ex: &lt; 30 incidents ou &lt; 6 mois), l'algorithme refuse de prédire (aucun chiffre inventé).</li>
            <li><strong>Validation de consistance :</strong> Mesure de stabilité multi-graines par l'indice de Jaccard (recouvrement moyen de <strong>{{ $iaInfo['stabilite'] }}</strong>).</li>
        </ul>
    </div>

    <!-- PORTAIL CITOYEN -->
    <h2>5. Portail Public & Vulgarisation Citoyenne</h2>
    <p>
        L'application propose une interface dédiée aux usagers (<a href="http://127.0.0.1:8000/incidents">/incidents</a>) qui traduit l'information technique en langage accessible :
    </p>
    <table>
        <tr>
            <th width="20%">Type d'Incident</th>
            <th width="35%">Terminologie Publique (Statut)</th>
            <th width="45%">Consignes & Recommandations Citoyennes Automatiques</th>
        </tr>
        <tr>
            <td><strong style="color:#0284c7;">Fuite d'eau</strong></td>
            <td><span class="badge badge-yellow">Réparation en cours</span></td>
            <td>Baisse de pression possible au robinet. Signaler tout écoulement anormal sur voirie.</td>
        </tr>
        <tr>
            <td><strong style="color:#dc2626;">Contamination</strong></td>
            <td><span class="badge badge-red">Contrôle sanitaire en cours</span></td>
            <td>Vérification sanitaire. Boire de l'eau en bouteille pour les personnes fragiles et nourrissons.</td>
        </tr>
        <tr>
            <td><strong style="color:#d97706;">Coupure</strong></td>
            <td><span class="badge badge-blue">Intervention programmée</span></td>
            <td>Interruption temporaire du service. Prévoyez une réserve d'eau pour les besoins immédiats.</td>
        </tr>
    </table>

    <!-- QUALITE ET TESTS -->
    <h2>6. Tests Automatisés & Assurance Qualité</h2>
    <p>
        Le module est protégé par une suite complète de <strong>87 tests automatisés</strong> (PHPUnit) garantissant qu'aucune régression ne peut survenir :
    </p>
    <table>
        <tr>
            <th>Fichier de Test</th>
            <th>Type</th>
            <th>Périmètre couvert</th>
        </tr>
        <tr>
            <td><code>PrioriteIncidentTest.php</code></td>
            <td>Unitaire</td>
            <td>Exactitude du barème sur 100, plafonnement des points, mise à jour dynamique.</td>
        </tr>
        <tr>
            <td><code>RegleStatutTest.php</code></td>
            <td>Unitaire</td>
            <td>Transitions d'états, rejet des retours en arrière, calcul du statut d'incident.</td>
        </tr>
        <tr>
            <td><code>IncidentHttpTest.php</code></td>
            <td>Fonctionnel</td>
            <td>Sécurité des accès (rôles admin/gestionnaire), validation des requêtes, alertes doublons.</td>
        </tr>
        <tr>
            <td><code>InterventionHttpTest.php</code></td>
            <td>Fonctionnel</td>
            <td>Changement de statut, verrouillage après complétion, création des lignes d'audit.</td>
        </tr>
        <tr>
            <td><code>AnalyseIncidentsIATest.php</code></td>
            <td>Fonctionnel</td>
            <td>Diagnostic préalable, robustesse de l'Isolation Forest, non-altération des données.</td>
        </tr>
    </table>

    <div class="page-break"></div>

    <div class="pied">
        AquaSecure — Rapport d'Exploitation (Incidents & Interventions) · Page 3/3 · Généré le {{ $dateGeneration }}
    </div>

    <!-- SCENARIO DE SOUTENANCE -->
    <h2>7. Guide de Soutenance & Scénario Oral (8 à 10 Minutes)</h2>
    <p>Voici le fil conducteur à suivre pour valoriser votre travail devant la professeure :</p>

    <div class="script-box">
        <strong>Minute 0 à 1 : Accroche & Vision Métier</strong>
        <p class="discours">
            « Bonjour Madame. Dans AquaSecure, j'ai pris en charge le module Exploitation : Incidents et Interventions.
            Sur un réseau d'eau, une défaillance mal gérée conduit à du gaspillage massif ou à un danger sanitaire.
            J'ai donc conçu un écosystème en 3 volets : l'information publique transparente pour les usagers, un outil de pilotage terrain strict pour les équipes, et deux algorithmes d'aide à la décision : un scoring explicable et une IA prédictive. »
        </p>
    </div>

    <div class="script-box">
        <strong>Minute 1 à 3 : Démonstration du Portail Citoyen</strong>
        <p><strong>Action sur PC :</strong> Allez sur <code>http://127.0.0.1:8000/incidents</code>.</p>
        <p class="discours">
            « Côté citoyen, l'usager visualise immédiatement la carte de sa commune (Leaflet). Les données sont vulgarisées : pas de code réseau obscur, mais l'état réel des réparations et des consignes claires (ex: stocker de l'eau ou faire bouillir en cas de suspicion de contamination). Les détails internes de gestion restent confidentiels. »
        </p>
    </div>

    <div class="script-box">
        <strong>Minute 3 à 5 : Back-Office & Intégrité des Interventions</strong>
        <p><strong>Action sur PC :</strong> Connectez-vous en <code>admin@aquasecure.tn</code> / <code>password</code> &rarr; Allez sur <code>/admin/incidents</code>.</p>
        <p class="discours">
            « Côté exploitant, voici le tableau de bord opérationnel.
            Remarquez les indicateurs en temps réel : les alertes critiques identifient en rouge tout problème critique non pris en charge depuis plus de 24h (Règle I7).
            Lorsque j'ouvre un incident et planifie une intervention, l'incident bascule automatiquement au statut 'En traitement' : son statut n'est pas figé en base mais calculé dynamiquement pour éliminer toute incohérence. »
        </p>
    </div>

    <div class="script-box">
        <strong>Minute 5 à 7 : Sécurité des Transitions & Traçabilité</strong>
        <p><strong>Action sur PC :</strong> Ouvrez une intervention &rarr; Changez le statut &rarr; Consultez <code>/admin/interventions/historique</code>.</p>
        <p class="discours">
            « Le cycle de vie respecte une machine à états finis stricte : Planifiée &rarr; En cours &rarr; Terminée.
            Une intervention terminée devient strictement non modifiable pour éviter toute falsification de rapport.
            De plus, chaque mouvement est consigné avec horodatage et identité de l'agent dans l'historique d'audit. Au niveau technique, j'ai protégé ces écritures par des transactions SQL avec verrous pessimistes (lockForUpdate). »
        </p>
    </div>

    <div class="script-box">
        <strong>Minute 7 à 9 : Priorisation Explicable & Détection d'Anomalies IA</strong>
        <p><strong>Action sur PC :</strong> Cliquez sur « Priorités », puis sur « Analyse IA ».</p>
        <p class="discours">
            « Pour aider le chef de secteur, notre moteur de priorité classe les sinistres sur 100 points avec une totale explicabilité de chaque critère (gravité, risque sanitaire, ancienneté).
            Enfin, sur la page IA, nous appliquons l'algorithme non-supervisé Isolation Forest sur les couples Réseau × Mois. L'algorithme isole les combinaisons atypiques (ex: résurgence anormale de contaminations sur un même tronçon) et formule des préconisations d'inspection préventive. »
        </p>
    </div>

    <!-- REPONSES AUX QUESTIONS PIEGES -->
    <h2>8. Fiche Réflexe : Réponses aux Questions Pièges du Jury</h2>
    <table>
        <tr>
            <th width="30%">Question Possible</th>
            <th width="70%">Votre Réponse Argumentée</th>
        </tr>
        <tr>
            <td><strong>Pourquoi calculer le statut au lieu d'une colonne en base ?</strong></td>
            <td>
                « Pour respecter les formes normales et éviter toute incohérence d'état (dédoublement de vérité). L'état réel dépend exclusivement des interventions terrain. Le calculer garantit une vérité unique. »
            </td>
        </tr>
        <tr>
            <td><strong>Pourquoi Isolation Forest plutôt qu'un seuil statistique ?</strong></td>
            <td>
                « Un seuil fixe ne détecte que les pics sur une seule variable. Isolation Forest est multivarié : il détecte par exemple une stagnation du nombre d'incidents mais avec une dégradation anormale du délai d'intervention et une concentration de cas critiques. »
            </td>
        </tr>
        <tr>
            <td><strong>Comment gérez-vous les conflits d'accès concurrents ?</strong></td>
            <td>
                « Dans la méthode <code>passerA()</code> du modèle <code>Intervention</code>, j'utilise une transaction de base de données associée à <code>lockForUpdate()</code>. La lecture et la vérification de la transition sont atomiques. »
            </td>
        </tr>
    </table>

</body>
</html>
