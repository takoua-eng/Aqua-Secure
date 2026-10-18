<?php

namespace App\Services\Renovation;

use App\Models\ProjetRenovation;
use App\Models\ReseauEau;
use App\Services\PlanRenovation;
use App\Services\Renovation\Apprentissage\JeuEntrainementSynthetique;
use App\Services\Renovation\Apprentissage\PlusProchesVoisins;
use App\Services\Renovation\Apprentissage\RegressionRidge;
use Illuminate\Support\Facades\Storage;

/*
 * Binôme 3 — modèle IA ENTRAÎNÉ sur les données AquaSecure : prédire le coût d'un projet qui n'a pas encore de devis.
 *
 * Exemples : les projets qui ont un coût déclaré (devis). Variables : type d'ouvrage, matériau, âge, état du réseau,
 * population de la zone. Cible : le coût, en logarithme (les coûts vont de quelques dizaines de milliers à
 * plusieurs millions de DT ; le logarithme rend les écarts comparables).
 *
 * Deux algorithmes codés en PHP (régression Ridge, k plus proches voisins) et plusieurs réglages sont comparés
 * par VALIDATION CROISÉE « un contre tous » : chaque devis est prédit par un modèle entraîné sur tous les autres.
 * Le meilleur est comparé au barème fixe actuel (PlanRenovation::cout) sur les mêmes projets.
 * Le modèle n'est utilisé dans l'application que s'il fait MIEUX que le barème et qu'il y a assez de devis.
 *
 * Aucune table : le modèle entraîné est un fichier JSON (storage/app/private/modeles/, non versionné).
 */
class ModeleCoutAppris
{
    public const FICHIER = 'modeles/renovation-cout.json';

    public const DEVIS_MIN = 12;

    public const LAMBDAS = [0.3, 1, 3, 10];

    public const K = [2, 3, 5];

    public const ALGORITHMES = ['ridge' => 'Régression linéaire régularisée (Ridge)', 'knn' => 'k plus proches voisins'];

    private const TYPES = ['canalisation', 'reservoir', 'station'];

    private const MATERIAUX = ['fonte', 'acier', 'pvc', 'pehd', 'beton', 'amiante_ciment'];

    private const ETATS = ['bon' => 0, 'degrade' => 1, 'hors_service' => 2];

    private static ?array $modele = null;

    private static bool $charge = false;

    // ---------- Entraînement ----------

    /** Entraîne, évalue, enregistre et renvoie le modèle (même s'il est inactif, pour afficher pourquoi). */
    public static function entrainer(): array
    {
        $debut = microtime(true);
        $base = self::exemples();
        $synthetiques = config('renovation.ia.jeu_synthetique', true) ? self::exemplesSynthetiques() : [];
        $exemples = [...$base, ...$synthetiques];
        $n = count($exemples);

        $modele = [
            'version'     => 1,
            'entraine_le' => now()->toIso8601String(),
            'n'           => $n,
            'composition' => ['base' => count($base), 'synthetique' => count($synthetiques)],
            'minimum'     => self::DEVIS_MIN,
            'variables'   => self::libellesVariables(),
            'actif'       => false,
            'raison'      => null,
        ];

        if ($n < self::DEVIS_MIN) {
            $modele['raison'] = "Seulement {$n} exemple(s) de devis : il en faut au moins " . self::DEVIS_MIN . ' pour entraîner et valider le modèle.';

            return self::enregistrer($modele, $debut);
        }

        $bruts = array_column($exemples, 'brut');
        $reels = array_column($exemples, 'cout');
        $logs = array_map('log', $reels);

        // Validation croisée « un contre tous » de chaque algorithme et de chaque réglage
        $candidats = [];
        foreach (self::LAMBDAS as $lambda) {
            $candidats[] = self::validationCroisee($bruts, $logs, $reels, 'ridge', $lambda);
        }
        foreach (self::K as $k) {
            if ($k < $n) {
                $candidats[] = self::validationCroisee($bruts, $logs, $reels, 'knn', $k);
            }
        }
        // Critère : l'erreur RELATIVE moyenne (les coûts vont de ~100 000 à plus d'un million de DT :
        // l'erreur en DT serait dominée par les gros projets)
        usort($candidats, fn ($a, $b) => [$a['mape'], $a['mae'], $a['algorithme']] <=> [$b['mape'], $b['mae'], $b['algorithme']]);
        $retenu = $candidats[0];

        // Référence : le barème fixe, sur les mêmes projets
        $baremes = array_column($exemples, 'bareme');
        $bareme = self::erreurs($reels, $baremes);

        // Modèle final : entraîné sur TOUS les devis avec le réglage retenu
        $normalisation = self::normalisation(array_map(fn ($b) => self::encoder($b), $bruts));
        $x = array_map(fn ($b) => self::normaliser(self::encoder($b), $normalisation), $bruts);

        $modele += [
            'candidats'     => array_map(fn ($c) => array_diff_key($c, ['predictions' => 1]), $candidats),
            'retenu'        => array_diff_key($retenu, ['predictions' => 1]),
            'bareme'        => $bareme,
            'gain_pct'      => $bareme['mape'] > 0 ? round(100 * ($bareme['mape'] - $retenu['mape']) / $bareme['mape'], 1) : 0.0,
            'normalisation' => $normalisation,
            'parametres'    => $retenu['algorithme'] === 'ridge'
                ? RegressionRidge::entrainer($x, $logs, $retenu['parametre'])
                : ['k' => $retenu['parametre'], 'x' => $x, 'y' => $logs, 'titres' => array_column($exemples, 'titre')],
            'validation'    => array_map(fn ($e, $p) => ['titre' => $e['titre'], 'origine' => $e['origine'], 'reel' => $e['cout'],
                'predit' => $p, 'bareme' => $e['bareme']], $exemples, $retenu['predictions']),
            'sur_la_base'   => self::evaluerSurLaBase($base, $synthetiques, $retenu),
        ];
        $modele['influences'] = $retenu['algorithme'] === 'ridge' ? self::influences($modele['parametres']['poids']) : [];
        $modele['actif'] = $retenu['mape'] < $bareme['mape'];
        $modele['raison'] = $modele['actif'] ? null
            : 'Le meilleur modèle ne fait pas mieux que le barème actuel sur les devis enregistrés : le barème reste utilisé.';

        return self::enregistrer($modele, $debut);
    }

    /** Projets avec un devis : variables brutes, coût réel et coût du barème (référence). */
    public static function exemples(): array
    {
        return ProjetRenovation::with('reseau.zone')
            ->whereNotNull('cout_estime')->where('cout_estime', '>', 0)
            ->orderBy('id')->get()
            ->filter(fn ($p) => $p->reseau !== null)
            ->map(fn ($p) => [
                'titre'   => $p->titre,
                'origine' => 'base',
                'brut'    => self::brut($p->reseau),
                'cout'    => (float) $p->cout_estime,
                'bareme'  => (float) PlanRenovation::cout($p->reseau),
            ])->values()->all();
    }

    /** Historique synthétique documenté (JeuEntrainementSynthetique), au même format. */
    public static function exemplesSynthetiques(): array
    {
        return array_map(function ($e, $i) {
            $reseau = new ReseauEau(['type' => $e['brut']['type'], 'materiau' => $e['brut']['materiau']]);

            return ['titre' => 'Historique synthétique n° ' . ($i + 1), 'origine' => 'synthetique', 'brut' => $e['brut'],
                'cout' => $e['cout'], 'bareme' => (float) PlanRenovation::cout($reseau)];
        }, $s = JeuEntrainementSynthetique::generer(), array_keys($s));
    }

    /**
     * Contrôle d'honnêteté : modèle entraîné SANS les devis de la base, testé UNIQUEMENT sur eux.
     * Montre ce que le modèle vaut sur les vraies données de l'application, comparé au barème.
     */
    private static function evaluerSurLaBase(array $base, array $synthetiques, array $retenu): ?array
    {
        if (! $base || ! $synthetiques) {
            return null;
        }

        $bruts = array_column($synthetiques, 'brut');
        $norm = self::normalisation(array_map(fn ($b) => self::encoder($b), $bruts));
        $x = array_map(fn ($b) => self::normaliser(self::encoder($b), $norm), $bruts);
        $logs = array_map(fn ($e) => log($e['cout']), $synthetiques);
        $ridge = $retenu['algorithme'] === 'ridge' ? RegressionRidge::entrainer($x, $logs, $retenu['parametre']) : null;

        $predits = array_map(function ($e) use ($ridge, $norm, $x, $logs, $retenu) {
            $v = self::normaliser(self::encoder($e['brut']), $norm);

            return exp($ridge ? RegressionRidge::predire($ridge, $v) : PlusProchesVoisins::predire($x, $logs, $v, (int) $retenu['parametre'])['valeur']);
        }, $base);

        return [
            'n'      => count($base),
            'modele' => self::erreurs(array_column($base, 'cout'), $predits),
            'bareme' => self::erreurs(array_column($base, 'cout'), array_column($base, 'bareme')),
        ];
    }

    /** Variables brutes d'un élément de réseau (aussi utilisées par le formulaire d'essai de la page « Modèles IA »). */
    public static function brut(ReseauEau $r): array
    {
        return [
            'type'       => $r->type,
            'materiau'   => $r->materiau,
            'age'        => $r->age(),
            'etat'       => $r->etat,
            'population' => (int) ($r->zone?->population ?? 0),
        ];
    }

    private static function validationCroisee(array $bruts, array $logs, array $reels, string $algorithme, float $parametre): array
    {
        $predictions = [];
        foreach (array_keys($bruts) as $i) {
            $autres = array_values(array_diff_key($bruts, [$i => 1]));
            $logsAutres = array_values(array_diff_key($logs, [$i => 1]));
            $norm = self::normalisation(array_map(fn ($b) => self::encoder($b), $autres));
            $x = array_map(fn ($b) => self::normaliser(self::encoder($b), $norm), $autres);
            $nouveau = self::normaliser(self::encoder($bruts[$i]), $norm);

            $log = $algorithme === 'ridge'
                ? RegressionRidge::predire(RegressionRidge::entrainer($x, $logsAutres, $parametre), $nouveau)
                : PlusProchesVoisins::predire($x, $logsAutres, $nouveau, (int) $parametre)['valeur'];
            $predictions[] = round(exp($log), 2);
        }

        return ['algorithme' => $algorithme, 'parametre' => $parametre] + self::erreurs($reels, $predictions) + ['predictions' => $predictions];
    }

    /** Erreur moyenne absolue (DT) et erreur relative moyenne (%). */
    private static function erreurs(array $reels, array $predits): array
    {
        $absolues = array_map(fn ($r, $p) => abs($r - $p), $reels, $predits);
        $relatives = array_map(fn ($r, $p) => abs($r - $p) / $r, $reels, $predits);

        return [
            'mae'  => round(array_sum($absolues) / count($absolues), 2),
            'mape' => round(100 * array_sum($relatives) / count($relatives), 1),
        ];
    }

    // ---------- Prédiction ----------

    /** Coût prédit pour un élément de réseau, ou null si le modèle n'est pas actif. */
    public static function predirePourReseau(ReseauEau $reseau): ?array
    {
        return self::predire(self::brut($reseau));
    }

    /** @return array{montant: float, marge: float, algorithme: string, voisins: list<string>}|null */
    public static function predire(array $brut): ?array
    {
        $m = self::charger();
        if (! $m || ! $m['actif']) {
            return null;
        }

        $x = self::normaliser(self::encoder($brut), $m['normalisation']);
        $voisins = [];
        if ($m['retenu']['algorithme'] === 'ridge') {
            $log = RegressionRidge::predire($m['parametres'], $x);
        } else {
            $p = PlusProchesVoisins::predire($m['parametres']['x'], $m['parametres']['y'], $x, (int) $m['parametres']['k']);
            $log = $p['valeur'];
            $voisins = array_map(fn ($i) => $m['parametres']['titres'][$i], $p['voisins']);
        }

        $montant = round(exp($log) / 1000) * 1000;

        return [
            'montant'    => $montant,
            // Marge = erreur RELATIVE moyenne du modèle appliquée à ce montant (l'erreur en DT dépend de la taille du projet)
            'marge'      => round($montant * $m['retenu']['mape'] / 100 / 1000) * 1000,
            'algorithme' => self::ALGORITHMES[$m['retenu']['algorithme']],
            'n'          => $m['n'],
            'voisins'    => $voisins,
        ];
    }

    // ---------- Fichier du modèle ----------

    public static function charger(): ?array
    {
        if (! self::$charge) {
            self::$charge = true;
            self::$modele = Storage::disk('local')->exists(self::FICHIER)
                ? json_decode(Storage::disk('local')->get(self::FICHIER), true)
                : null;
        }

        return self::$modele;
    }

    public static function oublier(): void
    {
        self::$modele = null;
        self::$charge = false;
    }

    private static function enregistrer(array $modele, float $debut): array
    {
        $modele['duree_ms'] = (int) round(1000 * (microtime(true) - $debut));
        Storage::disk('local')->put(self::FICHIER, json_encode($modele, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        self::$modele = $modele;
        self::$charge = true;

        return $modele;
    }

    // ---------- Variables ----------

    // Type et matériau en colonnes 0/1 ; âge, état (0, 1, 2) et population (en milliers) en nombres
    private static function encoder(array $b): array
    {
        $v = [];
        foreach (self::TYPES as $t) {
            $v[] = $b['type'] === $t ? 1.0 : 0.0;
        }
        foreach (self::MATERIAUX as $m) {
            $v[] = $b['materiau'] === $m ? 1.0 : 0.0;
        }
        $v[] = (float) $b['age'];
        $v[] = (float) (self::ETATS[$b['etat']] ?? 0);
        $v[] = $b['population'] / 1000;

        return $v;
    }

    public static function libellesVariables(): array
    {
        return [
            ...array_map(fn ($t) => 'Type : ' . (ReseauEau::TYPES[$t] ?? $t), self::TYPES),
            ...array_map(fn ($m) => 'Matériau : ' . (ReseauEau::MATERIAUX[$m] ?? $m), self::MATERIAUX),
            'Âge du réseau (années)', 'État du réseau (bon → hors service)', 'Population de la zone (milliers)',
        ];
    }

    // Centrage-réduction : chaque variable a une moyenne 0 et un écart-type 1 (sur les exemples d'entraînement)
    private static function normalisation(array $vecteurs): array
    {
        $n = count($vecteurs);
        $d = count($vecteurs[0]);
        $moyennes = $ecarts = [];
        for ($j = 0; $j < $d; $j++) {
            $col = array_column($vecteurs, $j);
            $moyennes[$j] = array_sum($col) / $n;
            $variance = array_sum(array_map(fn ($v) => ($v - $moyennes[$j]) ** 2, $col)) / $n;
            $ecarts[$j] = $variance > 1e-12 ? sqrt($variance) : 1.0;
        }

        return ['moyennes' => $moyennes, 'ecarts' => $ecarts];
    }

    private static function normaliser(array $v, array $norm): array
    {
        return array_map(fn ($x, $m, $e) => ($x - $m) / $e, $v, $norm['moyennes'], $norm['ecarts']);
    }

    // Influence de chaque variable (Ridge) : effet sur le coût d'une hausse d'un écart-type, en %
    private static function influences(array $poids): array
    {
        $libelles = self::libellesVariables();
        $liste = array_map(fn ($p, $l) => ['variable' => $l, 'effet_pct' => round(100 * (exp($p) - 1), 1)], $poids, $libelles);
        usort($liste, fn ($a, $b) => abs($b['effet_pct']) <=> abs($a['effet_pct']));

        return array_values(array_filter($liste, fn ($l) => abs($l['effet_pct']) >= 0.5));
    }
}
