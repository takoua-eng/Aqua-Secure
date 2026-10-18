<?php

namespace App\Services\Renovation;

use App\Models\Financement;
use App\Models\ProjetRenovation;
use App\Models\ReseauEau;
use App\Services\PlanRenovation;
use App\Services\Renovation\Apprentissage\BayesNaif;
use App\Services\Renovation\Apprentissage\JeuEntrainementSynthetique;
use Illuminate\Support\Facades\Storage;

/*
 * Binôme 3 — modèle IA ENTRAÎNÉ : quelle source de financement solliciter en priorité pour un projet ?
 *
 * Exemples : chaque financement enregistré (sa source = la classe à prédire). Variables du projet financé :
 * type d'ouvrage, état du réseau, taille du projet (coût déclaré, sinon barème) et grande région (Nord, Centre, Sud).
 * Algorithme : classifieur bayésien naïf (fréquences + lissage de Laplace), explicable.
 * Évaluation : validation croisée « un contre tous », comparée à la référence « toujours la source la plus fréquente ».
 * Le modèle n'est recommandé dans l'application que s'il bat cette référence.
 *
 * Aucune table : fichier JSON dans storage/app/private/modeles/ (non versionné).
 */
class ModeleSourceAppris
{
    public const FICHIER = 'modeles/renovation-source.json';

    public const FINANCEMENTS_MIN = 15;

    public const VARIABLES = ['type' => 'Type d\'ouvrage', 'etat' => 'État du réseau', 'taille' => 'Taille du projet', 'region' => 'Région'];

    public const TAILLES = ['petit' => 'moins de 200 000 DT', 'moyen' => '200 000 à 600 000 DT', 'grand' => 'plus de 600 000 DT'];

    // Grandes régions de la Tunisie (regroupement des 24 gouvernorats de config/communes.php)
    public const REGIONS = [
        'Nord'   => ['Tunis', 'Ariana', 'Ben Arous', 'Manouba', 'Nabeul', 'Zaghouan', 'Bizerte', 'Béja', 'Jendouba', 'Le Kef', 'Siliana'],
        'Centre' => ['Sousse', 'Monastir', 'Mahdia', 'Kairouan', 'Kasserine', 'Sidi Bouzid', 'Sfax'],
        'Sud'    => ['Gafsa', 'Tozeur', 'Kébili', 'Gabès', 'Médenine', 'Tataouine'],
    ];

    private static ?array $modele = null;

    private static bool $charge = false;

    // ---------- Entraînement ----------

    public static function entrainer(): array
    {
        $debut = microtime(true);
        $base = self::exemples();
        $synthetiques = config('renovation.ia.jeu_synthetique', true)
            ? array_map(fn ($e) => ['x' => self::variablesDepuis($e['brut']['type'], $e['brut']['etat'], $e['cout'], $e['commune']), 'source' => $e['source']],
                JeuEntrainementSynthetique::generer())
            : [];
        $exemples = [...$base, ...$synthetiques];
        $x = array_column($exemples, 'x');
        $classes = array_column($exemples, 'source');
        $n = count($exemples);
        $distinctes = count(array_unique($classes));

        $modele = ['version' => 1, 'entraine_le' => now()->toIso8601String(), 'n' => $n,
            'composition' => ['base' => count($base), 'synthetique' => count($synthetiques)],
            'minimum' => self::FINANCEMENTS_MIN, 'actif' => false, 'raison' => null];

        if ($n < self::FINANCEMENTS_MIN || $distinctes < 2) {
            $modele['raison'] = $n < self::FINANCEMENTS_MIN
                ? "Seulement {$n} financement(s) : il en faut au moins " . self::FINANCEMENTS_MIN . '.'
                : 'Tous les financements viennent de la même source : rien à apprendre.';

            return self::enregistrer($modele, $debut);
        }

        // Validation croisée « un contre tous »
        $justes = $justesTop2 = 0;
        $matrice = [];
        foreach (array_keys($x) as $i) {
            $m = BayesNaif::entrainer(array_values(array_diff_key($x, [$i => 1])), array_values(array_diff_key($classes, [$i => 1])));
            $probas = array_keys(BayesNaif::probabilites($m, $x[$i]));
            $justes += $probas[0] === $classes[$i] ? 1 : 0;
            $justesTop2 += in_array($classes[$i], array_slice($probas, 0, 2), true) ? 1 : 0;
            $matrice[$classes[$i]][$probas[0]] = ($matrice[$classes[$i]][$probas[0]] ?? 0) + 1;
        }

        $frequences = array_count_values($classes);
        arsort($frequences);
        $majoritaire = array_key_first($frequences);

        $modele['parametres'] = BayesNaif::entrainer($x, $classes);
        $modele['exactitude'] = round(100 * $justes / $n, 1);
        $modele['exactitude_top2'] = round(100 * $justesTop2 / $n, 1);
        $modele['reference'] = ['source' => $majoritaire, 'exactitude' => round(100 * $frequences[$majoritaire] / $n, 1)];
        $modele['repartition'] = array_map(fn ($c) => round(100 * $c / $n, 1), $frequences);
        $modele['matrice'] = $matrice;
        $modele['actif'] = $modele['exactitude'] > $modele['reference']['exactitude'];
        $modele['raison'] = $modele['actif'] ? null
            : 'Le modèle ne fait pas mieux que « toujours proposer ' . Financement::libelleSourceDe($majoritaire)
                . ' » : il faut des financements plus variés pour qu\'il apprenne quelque chose d\'utile.';

        return self::enregistrer($modele, $debut);
    }

    public static function exemples(): array
    {
        return Financement::with('projet.reseau.zone')->orderBy('id')->get()
            ->filter(fn ($f) => $f->projet?->reseau !== null)
            ->map(fn ($f) => ['x' => self::variables($f->projet), 'source' => $f->source])
            ->values()->all();
    }

    /** Variables d'un projet (catégorielles). */
    public static function variables(ProjetRenovation $p): array
    {
        $cout = $p->cout_estime !== null && (float) $p->cout_estime > 0 ? (float) $p->cout_estime : (float) PlanRenovation::cout($p->reseau);

        return self::variablesDepuis($p->reseau->type, $p->reseau->etat, $cout, $p->reseau->zone?->commune);
    }

    public static function variablesDepuis(string $type, string $etat, float $cout, ?string $commune): array
    {
        return [
            'type'   => $type,
            'etat'   => $etat,
            'taille' => $cout < 200000 ? 'petit' : ($cout < 600000 ? 'moyen' : 'grand'),
            'region' => self::region(EquiteTerritoriale::gouvernoratDe($commune)),
        ];
    }

    public static function region(?string $gouvernorat): string
    {
        foreach (self::REGIONS as $region => $gouvernorats) {
            if (in_array($gouvernorat, $gouvernorats, true)) {
                return $region;
            }
        }

        return 'Inconnue';
    }

    // ---------- Recommandation ----------

    /** @return array{probabilites: array<string, float>, source: string, explication: string, n: int}|null */
    public static function recommander(ProjetRenovation $projet): ?array
    {
        return $projet->reseau ? self::recommanderDepuis(self::variables($projet)) : null;
    }

    public static function recommanderDepuis(array $x): ?array
    {
        $m = self::charger();
        if (! $m || ! $m['actif']) {
            return null;
        }

        $probas = BayesNaif::probabilites($m['parametres'], $x);
        $source = array_key_first($probas);

        // Explication : la variable du projet où cette source est la plus fréquente dans l'historique
        $meilleure = null;
        foreach ($x as $variable => $valeur) {
            $part = BayesNaif::part($m['parametres'], $variable, $valeur, $source);
            if ($part !== null && ($meilleure === null || $part > $meilleure[2])) {
                $meilleure = [$variable, $valeur, $part];
            }
        }

        return [
            'probabilites' => array_map(fn ($p) => round(100 * $p, 1), $probas),
            'source'       => $source,
            'explication'  => $meilleure
                ? 'Dans l\'historique, ' . round(100 * $meilleure[2]) . ' % des financements de projets « ' . self::libelleValeur($meilleure[0], $meilleure[1])
                    . ' » viennent de la source « ' . Financement::libelleSourceDe($source) . ' ».'
                : '',
            'n'            => $m['n'],
        ];
    }

    public static function libelleValeur(string $variable, string $valeur): string
    {
        return match ($variable) {
            'type'   => mb_strtolower(ReseauEau::TYPES[$valeur] ?? $valeur),
            'etat'   => 'réseau ' . mb_strtolower(ReseauEau::ETATS[$valeur] ?? $valeur),
            'taille' => self::TAILLES[$valeur] ?? $valeur,
            'region' => 'région ' . $valeur,
            default  => $valeur,
        };
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
}
