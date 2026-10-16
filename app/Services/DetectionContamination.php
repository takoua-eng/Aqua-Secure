<?php

namespace App\Services;

use App\IA\ArbreDecision;
use App\Models\AnalyseQualite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/*
 * Modèle IA : détection précoce d'une contamination bactérienne.
 * Une culture de coliformes en laboratoire prend 24 à 48 h, alors que le pH, le chlore, la turbidité
 * et les nitrates se mesurent sur place en quelques minutes. Un arbre de décision, entraîné sur
 * l'historique des analyses, estime la probabilité que des coliformes soient présents à partir
 * de ces mesures rapides, du type de point et de la saison.
 */
class DetectionContamination
{
    public const FICHIER = 'modeles/detection-contamination.json';

    // Réglages de l'arbre
    public const PROFONDEUR_MAX = 4;
    public const MIN_PAR_FEUILLE = 5;

    // Part des analyses mise de côté pour mesurer les performances
    public const PART_TEST = 0.2;

    // Données minimales pour entraîner
    public const ANALYSES_MIN = 30;
    public const CAS_MIN = 5;

    // Variables d'entrée, dans l'ordre du vecteur donné à l'arbre
    public const VARIABLES = [
        ['cle' => 'ph',              'libelle' => 'pH',                         'type' => 'nombre',  'unite' => ''],
        ['cle' => 'chlore_residuel', 'libelle' => 'Chlore résiduel',            'type' => 'nombre',  'unite' => 'mg/L'],
        ['cle' => 'turbidite',       'libelle' => 'Turbidité',                  'type' => 'nombre',  'unite' => 'NTU'],
        ['cle' => 'nitrates',        'libelle' => 'Nitrates',                   'type' => 'nombre',  'unite' => 'mg/L'],
        ['cle' => 'robinet_public',  'libelle' => 'Point = robinet public',     'type' => 'oui_non', 'unite' => ''],
        ['cle' => 'reservoir',       'libelle' => 'Point = réservoir',          'type' => 'oui_non', 'unite' => ''],
        ['cle' => 'sortie_station',  'libelle' => 'Point = sortie de station',  'type' => 'oui_non', 'unite' => ''],
        ['cle' => 'saison_chaude',   'libelle' => 'Saison chaude (juin–sept.)', 'type' => 'oui_non', 'unite' => ''],
    ];

    // Probabilité à partir de laquelle on alerte. Volontairement en dessous de 50 % :
    // en santé publique, rater une contamination est plus grave qu'une fausse alerte.
    public const SEUIL_ALERTE = 0.3;

    // Niveau de risque selon la probabilité : [probabilité maximale, libellé, couleur]
    public const NIVEAUX = [
        [0.15, 'Risque faible',  'green'],
        [0.3,  'Risque modéré',  'orange'],
        [1.0,  'Risque élevé',   'red'],
    ];

    private static ?array $modele = null;

    // Vecteur d'entrée de l'arbre à partir des mesures de terrain, du type de point et de la date
    public static function vecteur(array $mesures, string $typePoint, $date): array
    {
        $mois = Carbon::parse($date)->month;

        return [
            (float) $mesures['ph'],
            (float) $mesures['chlore_residuel'],
            (float) $mesures['turbidite'],
            (float) $mesures['nitrates'],
            $typePoint === 'robinet_public' ? 1.0 : 0.0,
            $typePoint === 'reservoir' ? 1.0 : 0.0,
            $typePoint === 'sortie_station' ? 1.0 : 0.0,
            $mois >= 6 && $mois <= 9 ? 1.0 : 0.0,
        ];
    }

    // Entraîne le modèle sur toutes les analyses, mesure ses performances et l'enregistre
    public static function entrainer(): array
    {
        $analyses = AnalyseQualite::with('point:id,type_point')->orderBy('id')->get();

        $exemples = $analyses->map(fn (AnalyseQualite $a) => self::vecteur($a->only(['ph', 'chlore_residuel', 'turbidite', 'nitrates']), $a->point->type_point, $a->date_prelevement))->all();
        $cibles = $analyses->map(fn (AnalyseQualite $a) => $a->bacteries_coliformes > 0 ? 1 : 0)->all();
        $positifs = array_sum($cibles);

        if (count($cibles) < self::ANALYSES_MIN || $positifs < self::CAS_MIN || count($cibles) - $positifs < self::CAS_MIN) {
            throw new RuntimeException(sprintf(
                "Pas assez de données pour entraîner le modèle : il faut au moins %d analyses, dont %d avec et %d sans coliformes (actuellement %d analyses, %d contaminées).",
                self::ANALYSES_MIN, self::CAS_MIN, self::CAS_MIN, count($cibles), $positifs
            ));
        }

        // Séparation entraînement / test, en gardant la même part de cas contaminés des deux côtés
        [$entrainement, $test] = self::separer($cibles);

        $arbre = (new ArbreDecision(self::PROFONDEUR_MAX, self::MIN_PAR_FEUILLE))->entrainer(
            array_map(fn ($i) => $exemples[$i], $entrainement),
            array_map(fn ($i) => $cibles[$i], $entrainement),
        );

        $modele = [
            'entraineLe' => now()->toIso8601String(),
            'variables'  => self::VARIABLES,
            'donnees'    => [
                'total'        => count($cibles),
                'positifs'     => $positifs,
                'entrainement' => count($entrainement),
                'test'         => count($test),
            ],
            'metriques'  => self::evaluer($arbre, $exemples, $cibles, $test),
            'arbre'      => $arbre->versTableau(),
        ];

        Storage::disk('local')->put(self::FICHIER, json_encode($modele, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        self::$modele = $modele;

        return $modele;
    }

    // Modèle enregistré, ou null s'il n'a jamais été entraîné
    public static function charger(): ?array
    {
        if (self::$modele === null && Storage::disk('local')->exists(self::FICHIER)) {
            self::$modele = json_decode(Storage::disk('local')->get(self::FICHIER), true);
        }

        return self::$modele;
    }

    public static function oublier(): void
    {
        self::$modele = null;
    }

    // Prédiction pour des mesures : probabilité, niveau de risque et raisonnement de l'arbre
    public static function predire(array $mesures, string $typePoint, $date): ?array
    {
        $modele = self::charger();
        if (! $modele) {
            return null;
        }

        $arbre = ArbreDecision::depuisTableau($modele['arbre']);
        $x = self::vecteur($mesures, $typePoint, $date);
        $probabilite = $arbre->probabilite($x);

        return [
            'probabilite' => $probabilite,
            'niveau'      => self::niveau($probabilite),
            'raisons'     => array_map(fn ($c) => self::condition($c), $arbre->chemin($x)),
        ];
    }

    // Règles apprises, en français, de la plus risquée à la moins risquée
    public static function regles(array $modele): array
    {
        return collect(ArbreDecision::depuisTableau($modele['arbre'])->regles())
            ->map(fn ($r) => [
                'conditions' => array_map(fn ($c) => self::condition($c), $r['conditions']),
                'proba'      => $r['proba'],
                'n'          => $r['n'],
                'positifs'   => $r['positifs'],
                'niveau'     => self::niveau($r['proba']),
            ])
            ->sortByDesc('proba')
            ->values()
            ->all();
    }

    // [libellé, couleur] du niveau de risque
    public static function niveau(float $probabilite): array
    {
        foreach (self::NIVEAUX as [$max, $libelle, $couleur]) {
            if ($probabilite < $max) {
                return ['libelle' => $libelle, 'couleur' => $couleur];
            }
        }

        return ['libelle' => 'Risque élevé', 'couleur' => 'red'];
    }

    // Une question de l'arbre en français : "Chlore résiduel ≤ 0,15 mg/L", "Point = robinet public : oui"
    public static function condition(array $c): string
    {
        $variable = self::VARIABLES[$c['variable']];

        if ($variable['type'] === 'oui_non') {
            return $variable['libelle'] . ' : ' . ($c['inferieur'] ? 'non' : 'oui');
        }

        $seuil = str_replace('.', ',', rtrim(rtrim(number_format($c['seuil'], 2, '.', ''), '0'), '.'));

        return $variable['libelle'] . ($c['inferieur'] ? ' ≤ ' : ' > ') . $seuil . ($variable['unite'] ? ' ' . $variable['unite'] : '');
    }

    // ---------- Outils ----------

    // Indices des exemples d'entraînement et de test (tirage reproductible, stratifié)
    private static function separer(array $cibles): array
    {
        mt_srand(42);   // même découpage à chaque entraînement : résultats comparables
        $entrainement = $test = [];

        foreach ([0, 1] as $classe) {
            $indices = array_keys(array_filter($cibles, fn ($c) => $c === $classe));
            // Mélange de Fisher-Yates
            for ($i = count($indices) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                [$indices[$i], $indices[$j]] = [$indices[$j], $indices[$i]];
            }
            $nbTest = max(1, (int) round(count($indices) * self::PART_TEST));
            $test = [...$test, ...array_slice($indices, 0, $nbTest)];
            $entrainement = [...$entrainement, ...array_slice($indices, $nbTest)];
        }

        mt_srand();

        return [$entrainement, $test];
    }

    // Performances sur les analyses de test (jamais vues pendant l'apprentissage)
    private static function evaluer(ArbreDecision $arbre, array $exemples, array $cibles, array $test): array
    {
        $vp = $fp = $fn = $vn = 0;
        foreach ($test as $i) {
            $prediction = $arbre->probabilite($exemples[$i]) >= self::SEUIL_ALERTE ? 1 : 0;
            match (true) {
                $prediction === 1 && $cibles[$i] === 1 => $vp++,
                $prediction === 1 && $cibles[$i] === 0 => $fp++,
                $prediction === 0 && $cibles[$i] === 1 => $fn++,
                default                                => $vn++,
            };
        }

        $n = count($test);
        $precision = $vp + $fp ? (float) $vp / ($vp + $fp) : null;
        $rappel = $vp + $fn ? (float) $vp / ($vp + $fn) : null;

        return [
            'matrice'    => compact('vp', 'fp', 'fn', 'vn'),
            'exactitude' => $n ? (float) ($vp + $vn) / $n : null,
            'precision'  => $precision,
            'rappel'     => $rappel,
            'f1'         => $precision && $rappel ? 2 * $precision * $rappel / ($precision + $rappel) : null,
            // Référence : un "modèle" qui répondrait toujours "pas de contamination"
            'reference'  => $n ? (float) ($fp + $vn) / $n : null,
        ];
    }
}
