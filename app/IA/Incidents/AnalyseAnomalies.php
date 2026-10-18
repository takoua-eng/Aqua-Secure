<?php

namespace App\IA\Incidents;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/*
 * Analyse IA des incidents (binôme 2) : détection d'observations inhabituelles (réseau × mois) par Isolation Forest.
 *
 * - LECTURE SEULE de la base ; le résultat est enregistré dans storage/app/private/incidents-ia/analyse.json.
 * - Si les données sont insuffisantes (PreparationDonnees::CRITERES), le modèle n'est PAS lancé : le résultat
 *   le dit et liste les critères non remplis. Aucun résultat n'est inventé.
 * - Une observation signalée est une anomalie STATISTIQUE, pas un incident confirmé ; rien n'est modifié automatiquement.
 * - Évaluation : sans anomalies étiquetées, précision / rappel / F1 ne peuvent pas être mesurés. On mesure la
 *   STABILITÉ des détections (recouvrement de Jaccard entre plusieurs graines aléatoires).
 */
class AnalyseAnomalies
{
    public const FICHIER = 'incidents-ia/analyse.json';

    public const NB_ARBRES = 100;
    public const ECHANTILLON = 64;
    public const GRAINE = 2026;
    public const GRAINES_STABILITE = [7, 19, 31, 43, 61];

    // Score à partir duquel une observation est signalée, et à partir duquel l'anomalie est "forte"
    public const SEUIL = 0.6;
    public const SEUIL_FORT = 0.7;

    public const ANOMALIES_MAX = 50;

    public static function executer(): array
    {
        $diagnostic = PreparationDonnees::diagnostic();
        $preparation = PreparationDonnees::observations();
        $lignes = $preparation['lignes'];
        $criteres = PreparationDonnees::suffisance($diagnostic, count($lignes));
        $variables = array_keys(PreparationDonnees::VARIABLES);

        $resultat = [
            'date'         => now()->toIso8601String(),
            'statut'       => 'donnees_insuffisantes',
            'modele'       => 'Isolation Forest (non supervisé)',
            'parametres'   => [
                'arbres'      => self::NB_ARBRES,
                'echantillon' => min(self::ECHANTILLON, max(2, count($lignes))),
                'graine'      => self::GRAINE,
                'seuil'       => self::SEUIL,
                'seuil_fort'  => self::SEUIL_FORT,
            ],
            'periode'      => ['debut' => $preparation['debut'], 'fin' => $preparation['fin']],
            'variables'    => PreparationDonnees::VARIABLES,
            'diagnostic'   => $diagnostic,
            'criteres'     => $criteres,
            'observations' => count($lignes),
            'delai_median' => $preparation['delai_median'],
            'anomalies'    => [],
            'signalees'    => 0,
            'distribution' => [],
            'stabilite'    => null,
            'reference'    => [],
            'series'       => [],
        ];

        if (collect($criteres)->contains('ok', false)) {
            self::enregistrer($resultat);

            return $resultat;
        }

        $X = array_map(fn ($ligne) => array_values($ligne['valeurs']), $lignes);
        $echantillon = min(self::ECHANTILLON, count($X));

        $foret = (new IsolationForest(self::NB_ARBRES, $echantillon, self::GRAINE))->entrainer($X);
        $scores = array_map(fn ($x) => $foret->score($x), $X);
        $signales = array_keys(array_filter($scores, fn ($s) => $s >= self::SEUIL));
        $reference = self::reference($X, $variables);

        $anomalies = collect($signales)
            ->map(fn ($i) => [
                'reseau_id'    => $lignes[$i]['reseau_id'],
                'reseau'       => $lignes[$i]['reseau'],
                'zone'         => $lignes[$i]['zone'],
                'mois'         => $lignes[$i]['mois'],
                'mois_libelle' => ucfirst(Carbon::parse($lignes[$i]['mois'] . '-01')->locale('fr')->isoFormat('MMMM YYYY')),
                'score'        => round($scores[$i], 3),
                'niveau'       => $scores[$i] >= self::SEUIL_FORT ? 'forte' : 'moderee',
                'valeurs'      => $lignes[$i]['valeurs'],
                'delai_impute' => $lignes[$i]['delai_impute'],
                'explications' => self::expliquer($lignes[$i]['valeurs'], $reference),
            ])
            ->sortByDesc('score')
            ->take(self::ANOMALIES_MAX)
            ->map(fn ($anomalie) => $anomalie + ['recommandations' => self::recommander($anomalie)])
            ->values()
            ->all();

        $resultat['statut'] = 'termine';
        $resultat['anomalies'] = $anomalies;
        $resultat['signalees'] = count($signales);
        $resultat['reference'] = $reference;
        $resultat['distribution'] = self::distribution($scores);
        $resultat['stabilite'] = self::stabilite($X, $echantillon, $signales);
        $resultat['series'] = self::series($lignes, $scores, collect($anomalies)->pluck('reseau')->unique()->all(), $preparation['mois']);

        self::enregistrer($resultat);

        return $resultat;
    }

    // Dernière analyse enregistrée (null s'il n'y en a pas)
    public static function charger(): ?array
    {
        if (! Storage::disk('local')->exists(self::FICHIER)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get(self::FICHIER), true);
    }

    private static function enregistrer(array $resultat): void
    {
        Storage::disk('local')->put(self::FICHIER, json_encode($resultat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
            }

    // ---------- Explication ----------

    // Valeur habituelle de chaque variable : médiane et échelle robuste (1,4826 × écart absolu médian, sinon écart-type)
    private static function reference(array $X, array $variables): array
    {
        $reference = [];
        foreach ($variables as $j => $cle) {
            $colonne = array_column($X, $j);
            $mediane = self::mediane($colonne);
            $echelle = 1.4826 * self::mediane(array_map(fn ($v) => abs($v - $mediane), $colonne));

            if ($echelle <= 0) {
                $moyenne = array_sum($colonne) / count($colonne);
                $echelle = sqrt(array_sum(array_map(fn ($v) => ($v - $moyenne) ** 2, $colonne)) / count($colonne));
            }

            $reference[$cle] = ['mediane' => round($mediane, 2), 'echelle' => round($echelle, 4)];
        }

        return $reference;
    }

    // Les 3 variables qui s'écartent le plus de leur valeur habituelle (écart d'au moins 1 échelle)
    private static function expliquer(array $valeurs, array $reference): array
    {
        return collect($valeurs)
            ->map(function ($valeur, $cle) use ($reference) {
                $ref = $reference[$cle];
                if ($ref['echelle'] <= 0) {
                    return null;   // variable constante : elle n'explique rien
                }

                $ecart = ($valeur - $ref['mediane']) / $ref['echelle'];
                if (abs($ecart) < 1) {
                    return null;
                }

                $unite = PreparationDonnees::VARIABLES[$cle]['unite'];

                return [
                    'variable' => $cle,
                    'libelle'  => PreparationDonnees::VARIABLES[$cle]['libelle'],
                    'valeur'   => $valeur,
                    'habituel' => $ref['mediane'],
                    'ecart'    => round($ecart, 1),
                    'texte'    => PreparationDonnees::VARIABLES[$cle]['libelle'] . ' : ' . self::nombre($valeur) . $unite
                        . ' (habituellement ' . self::nombre($ref['mediane']) . $unite . ', '
                        . ($ecart > 0 ? 'nettement plus élevé' : 'nettement plus bas') . ')',
                ];
            })
            ->filter()
            ->sortByDesc(fn ($e) => abs($e['ecart']))
            ->take(3)
            ->values()
            ->all();
    }

    // Recommandations d'après les variables en hausse (jamais d'action automatique)
    private static function recommander(array $anomalie): array
    {
        $enHausse = collect($anomalie['explications'])->where('ecart', '>', 0)->pluck('variable');
        $reseau = $anomalie['reseau'];
        $mois = mb_strtolower($anomalie['mois_libelle']);

        $recommandations = $enHausse->map(fn ($variable) => match ($variable) {
            'incidents', 'hausse'   => "Inspecter le réseau {$reseau} : nombre d'incidents inhabituel en {$mois}. Si la tendance se confirme, le signaler pour une étude de rénovation.",
            'critiques'             => "Vérifier que les incidents critiques du réseau {$reseau} en {$mois} ont tous été pris en charge.",
            'part_contamination'    => "Demander des analyses de contrôle sur les points de prélèvement du réseau {$reseau} (module Qualité de l'eau).",
            'delai_prise_en_charge' => "Délai de prise en charge inhabituel sur {$reseau} : revoir la planification des équipes d'intervention.",
            'releves_hors_seuil'    => "Vérifier les capteurs et les mesures du réseau {$reseau} (module Surveillance) : nombreux relevés hors seuil.",
            default                 => null,
        })->filter()->unique()->values();

        if ($recommandations->isEmpty()) {
            $recommandations->push("Combinaison de valeurs inhabituelle sur {$reseau} en {$mois} : vérifier les données saisies et l'activité du réseau.");
        }

        return $recommandations->all();
    }

    // ---------- Évaluation et graphiques ----------

    // Recommencer avec d'autres graines : les mêmes observations sont-elles signalées ? (Jaccard, 1 = identique)
    private static function stabilite(array $X, int $echantillon, array $signales): array
    {
        $jaccard = [];
        foreach (self::GRAINES_STABILITE as $graine) {
            $autre = (new IsolationForest(self::NB_ARBRES, $echantillon, $graine))->entrainer($X);
            $ensemble = array_keys(array_filter(array_map(fn ($x) => $autre->score($x), $X), fn ($s) => $s >= self::SEUIL));
            $jaccard[] = round(self::jaccard($signales, $ensemble), 3);
        }

        return [
            'graines' => self::GRAINES_STABILITE,
            'jaccard' => $jaccard,
            'moyenne' => round(array_sum($jaccard) / count($jaccard), 3),
        ];
    }

    private static function jaccard(array $a, array $b): float
    {
        $union = count(array_unique(array_merge($a, $b)));

        return $union === 0 ? 1.0 : count(array_intersect($a, $b)) / $union;
    }

    // Répartition des scores par tranche de 0,05 (de « < 0,35 » à « ≥ 0,75 »)
    private static function distribution(array $scores): array
    {
        $classes = [];
        for ($k = 0; $k < 10; $k++) {
            $de = 0.30 + $k * 0.05;
            $classes[] = [
                'libelle'  => match ($k) {
                    0       => '< 0,35',
                    9       => '≥ 0,75',
                    default => number_format($de, 2, ',', '') . '–' . number_format($de + 0.05, 2, ',', ''),
                },
                'signalee' => $de >= self::SEUIL - 1e-9,
                'nombre'   => 0,
            ];
        }

        foreach ($scores as $score) {
            $k = max(0, min(9, (int) floor(($score - 0.30) / 0.05 + 1e-9)));
            $classes[$k]['nombre']++;
        }

        return $classes;
    }

    // Incidents et score mois par mois, pour les réseaux qui ont au moins une anomalie
    private static function series(array $lignes, array $scores, array $reseaux, array $mois): array
    {
        $series = [];
        foreach ($lignes as $i => $ligne) {
            if (! in_array($ligne['reseau'], $reseaux, true)) {
                continue;
            }
            $series[$ligne['reseau']]['incidents'][] = $ligne['valeurs']['incidents'];
            $series[$ligne['reseau']]['scores'][] = round($scores[$i], 3);
        }

        return [
            'mois'    => array_map(fn ($m) => ucfirst(Carbon::parse($m . '-01')->locale('fr')->isoFormat('MMM YY')), $mois),
            'reseaux' => $series,
        ];
    }

    // ---------- Outils ----------

    private static function mediane(array $valeurs): float
    {
        sort($valeurs);
        $n = count($valeurs);
        if ($n === 0) {
            return 0.0;
        }

        return $n % 2 ? (float) $valeurs[intdiv($n, 2)] : ($valeurs[$n / 2 - 1] + $valeurs[$n / 2]) / 2;
    }

    private static function nombre(float|int $valeur): string
    {
        return rtrim(rtrim(number_format((float) $valeur, 1, ',', ' '), '0'), ',');
    }
}
