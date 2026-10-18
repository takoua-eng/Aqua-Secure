<?php

namespace App\Services\Renovation;

use App\Models\ProjetRenovation;
use App\Services\ScoreRisque;

/*
 * Score de priorité d'un projet de rénovation (0 à 100), calculé par des règles explicables.
 *
 *   Risque du réseau        40 pts  score de risque du réseau (module Analyse des risques), ramené sur 40
 *   Avancement du projet    20 pts  en cours 20 (fonds déjà engagés), planifié 10, terminé 0
 *   Reste à financer        25 pts  part du coût qui n'est pas encore financée
 *   Alertes des capteurs     8 pts  relevés hors seuil sur 30 jours (module Surveillance) : 1 à 4 => 4 pts, 5 et plus => 8 pts
 *   Qualité de l'eau         7 pts  part d'analyses non conformes sur 90 jours (module Qualité de l'eau)
 *
 * Le score classe les projets à partir des données enregistrées. Il ne mesure ni une urgence,
 * ni une gravité, ni une probabilité de panne, et ne remplace pas l'avis d'un ingénieur.
 */
class ScorePriorite
{
    public const POINTS_STATUT = ['en_cours' => 20, 'planifie' => 10, 'termine' => 0];

    // Seuils : score minimum => [clé, libellé, couleur Tabler]
    public const NIVEAUX = [
        70 => ['prioritaire', 'Prioritaire', 'red'],
        45 => ['elevee', 'Élevée', 'orange'],
        25 => ['moyenne', 'Moyenne', 'yellow'],
        0  => ['basse', 'Basse', 'green'],
    ];

    /**
     * Calcul pur (sans base de données) : testable et reproductible.
     *
     * @param array{capteurs?: int, alertes?: int, analyses?: int, non_conformes?: int} $signaux
     * @return array{score: int, niveau: string, libelle: string, couleur: string, facteurs: list<array>, limites: list<string>}
     */
    public static function calculer(int $scoreRisque, string $statut, ?float $cout, float $finance, string $sourceCout, array $signaux = []): array
    {
        $scoreRisque = max(0, min(100, $scoreRisque));
        $facteurs = [];
        $limites = [];

        $facteurs[] = [
            'libelle' => "Score de risque du réseau : {$scoreRisque}/100",
            'points'  => (int) round(40 * $scoreRisque / 100),
            'max'     => 40,
        ];

        $facteurs[] = [
            'libelle' => match ($statut) {
                'en_cours' => 'Projet en cours : des fonds sont déjà engagés',
                'planifie' => 'Projet planifié, pas encore démarré',
                default    => 'Projet terminé : plus de besoin de financement',
            },
            'points'  => self::POINTS_STATUT[$statut] ?? 0,
            'max'     => 20,
        ];

        if ($cout === null || $cout <= 0) {
            $facteurs[] = ['libelle' => 'Reste à financer inconnu (coût non disponible)', 'points' => 0, 'max' => 25];
            $limites[] = 'Coût du projet inconnu : le critère « reste à financer » vaut 0.';
        } else {
            $part = max(0.0, min(1.0, ($cout - $finance) / $cout));
            $pct = (int) round($part * 100);
            $nature = $sourceCout === 'declare' ? 'déclaré' : 'estimé';
            $facteurs[] = [
                'libelle' => "Reste à financer : {$pct} % du coût {$nature}",
                'points'  => (int) round(25 * $part),
                'max'     => 25,
            ];
            if ($sourceCout !== 'declare') {
                $limites[] = 'Le reste à financer repose sur un coût estimé (barème), pas sur un devis.';
            }
        }

        // Signaux des autres modules (Surveillance, Qualité de l'eau)
        $capteurs = (int) ($signaux['capteurs'] ?? 0);
        $alertes = (int) ($signaux['alertes'] ?? 0);
        if ($capteurs === 0) {
            $facteurs[] = ['libelle' => 'Aucun capteur installé sur ce réseau', 'points' => 0, 'max' => 8];
            $limites[] = 'Pas de capteur sur le réseau : le critère « alertes des capteurs » ne peut pas jouer.';
        } else {
            $jours = SignauxTerrain::JOURS_CAPTEURS;
            $facteurs[] = [
                'libelle' => "Capteurs : {$alertes} relevé(s) hors seuil sur {$jours} jours ({$capteurs} capteur(s))",
                'points'  => $alertes >= 5 ? 8 : ($alertes >= 1 ? 4 : 0),
                'max'     => 8,
            ];
        }

        $analyses = (int) ($signaux['analyses'] ?? 0);
        $nonConformes = (int) ($signaux['non_conformes'] ?? 0);
        if ($analyses === 0) {
            $facteurs[] = ['libelle' => 'Aucune analyse d\'eau récente sur ce réseau', 'points' => 0, 'max' => 7];
        } else {
            $jours = SignauxTerrain::JOURS_ANALYSES;
            $facteurs[] = [
                'libelle' => "Qualité de l'eau : {$nonConformes} analyse(s) non conforme(s) sur {$analyses} en {$jours} jours",
                'points'  => (int) round(7 * $nonConformes / $analyses),
                'max'     => 7,
            ];
        }

        $limites[] = "Ce score classe les projets selon les données enregistrées : il ne mesure ni l'urgence, ni une probabilité de panne.";

        $score = (int) min(100, array_sum(array_column($facteurs, 'points')));

        return ['score' => $score] + self::niveau($score) + ['facteurs' => $facteurs, 'limites' => $limites];
    }

    // Le réseau doit être chargé avec sa zone (et etat_depuis si possible, pour éviter une requête)
    public static function pourProjet(ProjetRenovation $projet): array
    {
        $risque = $projet->reseau ? ScoreRisque::pourReseau($projet->reseau) : null;
        $cout = ModeleCout::pourProjet($projet);

        $resultat = self::calculer(
            (int) ($risque['score'] ?? 0),
            $projet->statut,
            $cout['montant'],
            $projet->totalFinance(),
            $cout['source'],
            SignauxTerrain::pourReseau($projet->reseau_id),
        );

        if (! $risque) {
            array_unshift($resultat['limites'], 'Réseau introuvable : le risque du réseau compte pour 0.');
        }

        return $resultat + ['risque' => $risque, 'cout' => $cout];
    }

    /** @return array{niveau: string, libelle: string, couleur: string} */
    public static function niveau(int $score): array
    {
        foreach (self::NIVEAUX as $minimum => [$cle, $libelle, $couleur]) {
            if ($score >= $minimum) {
                return ['niveau' => $cle, 'libelle' => $libelle, 'couleur' => $couleur];
            }
        }

        return ['niveau' => 'basse', 'libelle' => 'Basse', 'couleur' => 'green'];
    }
}
