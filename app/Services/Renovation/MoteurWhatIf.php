<?php

namespace App\Services\Renovation;

use InvalidArgumentException;
use LogicException;

/*
 * AquaSecure — What If? Engine : moteur de répartition d'un budget HYPOTHÉTIQUE entre projets de rénovation.
 *
 * - Indépendant de l'IA et de la base de données : il reçoit des tableaux, rend des tableaux (testable).
 * - Déterministe : mêmes entrées => mêmes résultats (tris stables, départage par identifiant).
 * - Calculs en centimes entiers : aucune erreur d'arrondi, le budget n'est jamais dépassé (vérifié à la fin).
 * - Il ne crée et ne modifie aucun financement réel.
 *
 * Candidat attendu : id, titre, reseau, zone, etat_reseau (bon|degrade|hors_service), statut, score (0-100),
 *                    cout (DT, null si inconnu), cout_source (declare|estime|inconnu), finance (DT enregistrés).
 * Besoin d'un projet = coût − financements enregistrés (jamais négatif).
 */
class MoteurWhatIf
{
    public const STRATEGIES = [
        'degrades' => [
            'lettre'      => 'A',
            'libelle'     => 'Réseaux dégradés d\'abord',
            'icone'       => 'alert-octagon',
            'description' => 'Finance en entier les projets des réseaux hors service, puis dégradés, puis en bon état ; à état égal, le score de priorité le plus haut passe en premier.',
            'compromis'   => 'Peut consommer le budget sur quelques gros projets critiques et laisser les autres sans aucun financement.',
        ],
        'equilibre' => [
            'lettre'      => 'B',
            'libelle'     => 'Répartition équilibrée',
            'icone'       => 'arrows-split-2',
            'description' => 'Partage le budget entre tous les projets, proportionnellement à leur score de priorité, sans jamais dépasser le reste à financer d\'un projet.',
            'compromis'   => 'Fait avancer tous les projets mais en achève peu : la plupart des financements sont partiels.',
        ],
        'max_projets' => [
            'lettre'      => 'C',
            'libelle'     => 'Maximum de projets',
            'icone'       => 'stack-2',
            'description' => 'Finance en entier les plus petits restes à financer d\'abord : c\'est la méthode qui achève le plus grand nombre de projets avec ce budget.',
            'compromis'   => 'Laisse de côté les projets coûteux, même lorsqu\'ils concernent un réseau critique.',
        ],
        'optimal' => [
            'lettre'      => 'D',
            'libelle'     => 'Priorité maximale (optimisation)',
            'icone'       => 'math-function',
            'description' => 'Problème du sac à dos (programmation dynamique) : la combinaison de projets financés en entier dont la somme des scores de priorité est la plus grande.',
            'compromis'   => 'Résultat optimal pour le score mais moins intuitif à expliquer ; il dépend de la fiabilité des scores et des coûts.',
        ],
        'equite' => [
            'lettre'      => 'E',
            'libelle'     => 'Équité territoriale',
            'icone'       => 'map-2',
            'description' => 'Finance en entier, à chaque tour, un projet du gouvernorat qui a reçu le moins de DT par habitant (financements enregistrés + allocations du scénario) ; à égalité, le score le plus haut.',
            'compromis'   => 'Réduit les écarts entre régions, au risque de passer après des projets plus prioritaires situés dans des gouvernorats déjà mieux dotés.',
        ],
    ];

    public const BUDGET_MAX = 100000000;      // DT

    public const SEUIL_PRIORITAIRE = 70;      // même seuil que ScorePriorite « Prioritaire »

    private const RANG_ETAT = ['hors_service' => 2, 'degrade' => 1, 'bon' => 0];

    public const ETATS = ['hors_service' => 'Hors service', 'degrade' => 'Dégradé', 'bon' => 'Bon'];

    // Sac à dos : nombre maximal de cases (le pas s'agrandit avec le budget, jamais en dessous de 1 000 DT)
    private const CAPACITE_MAX = 20000;

    private const PAS_MIN = 100000;   // centimes = 1 000 DT

    /**
     * Calcule les 4 stratégies sur les mêmes données et les compare.
     *
     * @param array $options exclus (int[]), partiel (bool), inclure_estimes (bool)
     */
    public static function comparer(array $candidats, float $budget, array $options = []): array
    {
        if (! is_finite($budget) || $budget < 0 || $budget > self::BUDGET_MAX) {
            throw new InvalidArgumentException('Le budget doit être compris entre 0 et ' . self::montant(self::BUDGET_MAX * 100) . '.');
        }

        $options = [
            'exclus'          => array_values(array_unique(array_map('intval', $options['exclus'] ?? []))),
            'partiel'         => (bool) ($options['partiel'] ?? false),
            'inclure_estimes' => (bool) ($options['inclure_estimes'] ?? true),
        ];
        sort($options['exclus']);

        $budgetC = self::centimes($budget);
        [$retenus, $ecartes] = self::preparer($candidats, $options);

        $scenarios = [];
        foreach (array_keys(self::STRATEGIES) as $strategie) {
            $scenarios[$strategie] = self::scenario($strategie, $retenus, $budgetC, $options['partiel']);
        }

        [$recommande, $raison] = self::recommander($scenarios);

        return [
            'budget'      => $budgetC / 100,
            'options'     => $options,
            'scenarios'   => $scenarios,
            'meilleurs'   => self::meilleurs($scenarios),
            'recommande'  => $recommande,
            'raison'      => $raison,
            'candidats'   => count($retenus),
            'besoin_total' => array_sum(array_column($retenus, 'besoin')) / 100,
            'ecartes'     => $ecartes,
            'diagnostic'  => self::diagnostic($retenus, $ecartes, $budgetC),
            'hypotheses'  => self::hypotheses($budgetC, $retenus, $ecartes, $options),
        ];
    }

    // ---------- Préparation des données ----------

    private static function preparer(array $candidats, array $options): array
    {
        $retenus = [];
        $ecartes = [];
        $vus = [];

        foreach ($candidats as $c) {
            foreach (['id', 'titre', 'statut', 'score', 'cout_source', 'finance'] as $cle) {
                if (! array_key_exists($cle, $c)) {
                    throw new InvalidArgumentException("Candidat incomplet : la donnée « {$cle} » manque.");
                }
            }

            $id = (int) $c['id'];
            if (isset($vus[$id])) {
                continue; // un projet n'est compté qu'une fois
            }
            $vus[$id] = true;

            $ligne = [
                'id'          => $id,
                'titre'       => (string) $c['titre'],
                'reseau'      => (string) ($c['reseau'] ?? ''),
                'zone'        => (string) ($c['zone'] ?? ''),
                'etat_reseau' => array_key_exists($c['etat_reseau'] ?? null, self::RANG_ETAT) ? $c['etat_reseau'] : null,
                'statut'      => (string) $c['statut'],
                'score'       => max(0, min(100, (int) $c['score'])),
                'cout'        => $c['cout'] === null ? null : self::centimes(max(0, (float) $c['cout'])),
                'cout_source' => (string) $c['cout_source'],
                'finance'     => self::centimes(max(0, (float) $c['finance'])),
                // Facultatif (stratégie E) : gouvernorat, sa population et ce qu'il a déjà reçu
                'territoire'            => isset($c['territoire']) ? (string) $c['territoire'] : null,
                'territoire_population' => max(0, (int) ($c['territoire_population'] ?? 0)),
                'territoire_finance'    => self::centimes(max(0, (float) ($c['territoire_finance'] ?? 0))),
            ];
            $ligne['besoin'] = $ligne['cout'] === null ? null : max(0, $ligne['cout'] - $ligne['finance']);

            $motif = match (true) {
                in_array($id, $options['exclus'], true) => 'Exclu du scénario par le gestionnaire (hypothèse).',
                $ligne['statut'] === 'termine'          => 'Projet terminé : plus de besoin de financement.',
                $ligne['cout'] === null || $ligne['cout'] === 0 => 'Coût inconnu : le reste à financer ne peut pas être calculé.',
                $ligne['besoin'] === 0                  => 'Déjà entièrement financé par les financements enregistrés.',
                $ligne['cout_source'] !== 'declare' && ! $options['inclure_estimes'] => 'Coût seulement estimé : exclu par l\'option « devis uniquement ».',
                default => null,
            };

            if ($motif) {
                $ecartes[] = ['id' => $id, 'titre' => $ligne['titre'], 'motif' => $motif];
            } else {
                $retenus[] = $ligne;
            }
        }

        usort($retenus, fn ($a, $b) => $a['id'] <=> $b['id']);
        usort($ecartes, fn ($a, $b) => $a['id'] <=> $b['id']);

        return [$retenus, $ecartes];
    }

    // ---------- Stratégies ----------

    private static function scenario(string $strategie, array $candidats, int $budget, bool $partiel): array
    {
        [$alloc, $raisons, $ordre] = match ($strategie) {
            'degrades'    => self::strategieDegrades($candidats, $budget, $partiel),
            'equilibre'   => self::strategieEquilibre($candidats, $budget),
            'max_projets' => self::strategieMaxProjets($candidats, $budget, $partiel),
            'optimal'     => self::strategieOptimale($candidats, $budget, $partiel),
            'equite'      => self::strategieEquite($candidats, $budget, $partiel),
        };

        self::verifier($candidats, $alloc, $budget, $strategie);

        return self::construire($strategie, $candidats, $alloc, $raisons, $ordre, $budget);
    }

    // A — état du réseau d'abord, puis score de priorité
    private static function strategieDegrades(array $candidats, int $budget, bool $partiel): array
    {
        usort($candidats, fn ($a, $b) => [self::rangEtat($b), $b['score'], $a['id']] <=> [self::rangEtat($a), $a['score'], $b['id']]);

        return self::financerDansLOrdre($candidats, $budget, $partiel, function ($c) {
            $etat = self::ETATS[$c['etat_reseau']] ?? 'état inconnu';

            return "Réseau {$etat}, priorité {$c['score']}/100";
        });
    }

    // C — plus petits besoins d'abord : maximise le nombre de projets achevés (choix glouton optimal pour ce critère)
    private static function strategieMaxProjets(array $candidats, int $budget, bool $partiel): array
    {
        usort($candidats, fn ($a, $b) => [$a['besoin'], $b['score'], $a['id']] <=> [$b['besoin'], $a['score'], $b['id']]);

        return self::financerDansLOrdre($candidats, $budget, $partiel, fn ($c) => 'Reste à financer de ' . self::montant($c['besoin']));
    }

    private static function financerDansLOrdre(array $ordonnes, int $budget, bool $partiel, callable $motif): array
    {
        $reste = $budget;
        $alloc = [];
        $raisons = [];
        $ordre = [];
        $rang = 0;

        foreach ($ordonnes as $c) {
            $id = $c['id'];
            $alloc[$id] = 0;

            if ($c['besoin'] <= $reste) {
                $alloc[$id] = $c['besoin'];
                $reste -= $c['besoin'];
                $ordre[$id] = ++$rang;
                $raisons[$id] = $motif($c) . ' : financé en entier (rang ' . $rang . ').';
            } elseif ($partiel && $reste > 0) {
                $alloc[$id] = $reste;
                $ordre[$id] = ++$rang;
                $raisons[$id] = $motif($c) . ' : financement PARTIEL avec le reliquat du budget (ne permet pas d\'achever le projet).';
                $reste = 0;
            } else {
                $raisons[$id] = $motif($c) . ' : non financé, le budget restant (' . self::montant($reste) . ') ne couvre pas son besoin.';
            }
        }

        return [$alloc, $raisons, $ordre];
    }

    // B — partage proportionnel au score, plafonné au besoin (les surplus sont redistribués)
    private static function strategieEquilibre(array $candidats, int $budget): array
    {
        $alloc = array_fill_keys(array_column($candidats, 'id'), 0);
        $actifs = [];
        foreach ($candidats as $c) {
            $actifs[$c['id']] = $c;
        }
        $reste = $budget;

        while ($reste > 0 && $actifs) {
            $poidsTotal = array_sum(array_map(fn ($c) => max(1, $c['score']), $actifs));
            $resteTour = $reste;
            $plafonne = false;

            foreach ($actifs as $id => $c) {
                $part = intdiv($resteTour * max(1, $c['score']), $poidsTotal);
                $manque = $c['besoin'] - $alloc[$id];
                if ($part >= $manque) {
                    $alloc[$id] += $manque;
                    $reste -= $manque;
                    unset($actifs[$id]);
                    $plafonne = true;
                }
            }

            if ($plafonne) {
                continue; // des projets sont couverts : on repartage ce qui reste entre les autres
            }

            foreach ($actifs as $id => $c) {
                $part = intdiv($resteTour * max(1, $c['score']), $poidsTotal);
                $alloc[$id] += $part;
                $reste -= $part;
            }

            // Centimes restants des divisions : un par projet, du score le plus haut au plus bas
            $ordreCentimes = array_values($actifs);
            usort($ordreCentimes, fn ($a, $b) => [$b['score'], $a['id']] <=> [$a['score'], $b['id']]);
            foreach ($ordreCentimes as $c) {
                if ($reste <= 0) {
                    break;
                }
                if ($alloc[$c['id']] < $c['besoin']) {
                    $alloc[$c['id']]++;
                    $reste--;
                }
            }
            break;
        }

        $raisons = [];
        $poids = array_sum(array_map(fn ($c) => max(1, $c['score']), $candidats)) ?: 1;
        foreach ($candidats as $c) {
            $raisons[$c['id']] = $alloc[$c['id']] >= $c['besoin']
                ? "Priorité {$c['score']}/100 : sa part couvre tout son reste à financer."
                : "Priorité {$c['score']}/100 : reçoit environ " . round(100 * max(1, $c['score']) / $poids, 1) . ' % du budget à partager (financement partiel).';
        }

        return [$alloc, $raisons, []];
    }

    // D — sac à dos 0/1 : maximise la somme des scores des projets financés en entier
    private static function strategieOptimale(array $candidats, int $budget, bool $partiel): array
    {
        $pas = max(self::PAS_MIN, (int) ceil($budget / self::CAPACITE_MAX));
        $capacite = intdiv($budget, $pas);
        $meilleur = array_fill(0, $capacite + 1, 0);
        $garde = [];

        // Poids arrondi au pas SUPÉRIEUR : la combinaison choisie ne peut jamais dépasser le budget réel
        // Valeur = score × 1000 + 1 : à somme de scores égale, on préfère financer plus de projets
        foreach ($candidats as $i => $c) {
            $poids = (int) ceil($c['besoin'] / $pas);
            $valeur = $c['score'] * 1000 + 1;
            $garde[$i] = str_repeat("\0", $capacite + 1);
            for ($b = $capacite; $b >= $poids; $b--) {
                if ($meilleur[$b - $poids] + $valeur > $meilleur[$b]) {
                    $meilleur[$b] = $meilleur[$b - $poids] + $valeur;
                    $garde[$i][$b] = "\1";
                }
            }
        }

        $choisis = [];
        $b = $capacite;
        for ($i = count($candidats) - 1; $i >= 0; $i--) {
            if ($garde[$i][$b] === "\1") {
                $choisis[$candidats[$i]['id']] = true;
                $b -= (int) ceil($candidats[$i]['besoin'] / $pas);
            }
        }

        $alloc = [];
        $raisons = [];
        $ordre = [];
        $reste = $budget;
        $rang = 0;
        $parScore = $candidats;
        usort($parScore, fn ($a, $b) => [$b['score'], $a['id']] <=> [$a['score'], $b['id']]);

        foreach ($parScore as $c) {
            $id = $c['id'];
            $alloc[$id] = 0;
            if (isset($choisis[$id])) {
                $alloc[$id] = $c['besoin'];
                $reste -= $c['besoin'];
                $ordre[$id] = ++$rang;
                $raisons[$id] = "Priorité {$c['score']}/100 : fait partie de la combinaison qui maximise la priorité totale.";
            }
        }

        foreach ($parScore as $c) {
            $id = $c['id'];
            if (isset($choisis[$id])) {
                continue;
            }
            if ($partiel && $reste > 0) {
                $alloc[$id] = min($reste, $c['besoin']);
                $reste -= $alloc[$id];
                $ordre[$id] = ++$rang;
                $raisons[$id] = "Priorité {$c['score']}/100 : financement PARTIEL avec le reliquat (ne permet pas d'achever le projet).";
            } else {
                $raisons[$id] = "Priorité {$c['score']}/100 : écarté, d'autres combinaisons apportent plus de priorité avec le même budget.";
            }
        }

        return [$alloc, $raisons, $ordre];
    }

    // E — à chaque tour, le gouvernorat le moins doté par habitant ; un projet sans territoire connu passe en dernier
    private static function strategieEquite(array $candidats, int $budget, bool $partiel): array
    {
        $investi = [];
        $population = [];
        foreach ($candidats as $c) {
            if ($c['territoire'] !== null) {
                $investi[$c['territoire']] ??= $c['territoire_finance'];
                $population[$c['territoire']] = max($population[$c['territoire']] ?? 0, $c['territoire_population']);
            }
        }
        $parHabitant = fn ($c) => $c['territoire'] !== null && ($population[$c['territoire']] ?? 0) > 0
            ? $investi[$c['territoire']] / $population[$c['territoire']]
            : INF;

        $restants = $candidats;
        $alloc = array_fill_keys(array_column($candidats, 'id'), 0);
        $raisons = [];
        $ordre = [];
        $reste = $budget;
        $rang = 0;

        while ($restants) {
            usort($restants, fn ($a, $b) => [$parHabitant($a), $b['score'], $a['id']] <=> [$parHabitant($b), $a['score'], $b['id']]);
            $choisi = null;
            foreach ($restants as $i => $c) {
                if ($c['besoin'] <= $reste) {
                    $choisi = $i;
                    break;
                }
            }
            if ($choisi === null) {
                break;
            }

            $c = $restants[$choisi];
            $avant = $parHabitant($c);
            $alloc[$c['id']] = $c['besoin'];
            $reste -= $c['besoin'];
            $ordre[$c['id']] = ++$rang;
            $raisons[$c['id']] = $c['territoire'] === null
                ? "Gouvernorat inconnu : financé après les autres (rang {$rang})."
                : "Gouvernorat de {$c['territoire']} : " . ($avant === INF ? 'population non renseignée' : number_format($avant / 100, 2, ',', ' ') . ' DT par habitant')
                  . " avant cette allocation, le plus faible parmi les projets qui tiennent dans le budget (rang {$rang}).";
            if ($c['territoire'] !== null) {
                $investi[$c['territoire']] += $c['besoin'];
            }
            unset($restants[$choisi]);
        }

        foreach ($restants as $c) {
            if ($partiel && $reste > 0) {
                $alloc[$c['id']] = min($reste, $c['besoin']);
                $reste -= $alloc[$c['id']];
                $ordre[$c['id']] = ++$rang;
                $raisons[$c['id']] = 'Financement PARTIEL avec le reliquat (ne permet pas d\'achever le projet).';
            } else {
                $raisons[$c['id']] = 'Non financé : son besoin dépasse le budget restant (' . self::montant($reste) . ').';
            }
        }

        return [$alloc, $raisons, $ordre];
    }

    // ---------- Résultats ----------

    private static function construire(string $strategie, array $candidats, array $alloc, array $raisons, array $ordre, int $budget): array
    {
        $alloue = array_sum($alloc);
        $lignes = [];
        $sommeScores = 0;
        $scoreCouvert = 0.0;
        $complets = $partiels = $aucun = 0;
        $critiques = $critiquesCouverts = 0;
        $surEstimes = 0;
        $hhi = 0.0;
        $plusGrand = 0;
        $prioritairesNonCouverts = [];
        $critiquesNonCouverts = [];
        $besoinTotal = 0;

        foreach ($candidats as $c) {
            $id = $c['id'];
            $propose = $alloc[$id] ?? 0;
            $etat = $propose === 0 ? 'aucun' : ($propose >= $c['besoin'] ? 'complet' : 'partiel');
            $besoinTotal += $c['besoin'];

            match ($etat) {
                'complet' => $complets++,
                'partiel' => $partiels++,
                default   => $aucun++,
            };
            $sommeScores += $c['score'];
            $scoreCouvert += $c['score'] * ($c['besoin'] > 0 ? $propose / $c['besoin'] : 0);

            if (in_array($c['etat_reseau'], ['degrade', 'hors_service'], true)) {
                $critiques++;
                if ($etat === 'complet') {
                    $critiquesCouverts++;
                } else {
                    $critiquesNonCouverts[] = $c['titre'];
                }
            }
            if ($c['score'] >= self::SEUIL_PRIORITAIRE && $etat !== 'complet') {
                $prioritairesNonCouverts[] = $c['titre'];
            }
            if ($c['cout_source'] !== 'declare') {
                $surEstimes += $propose;
            }
            if ($alloue > 0) {
                $hhi += ($propose / $alloue) ** 2;
            }
            $plusGrand = max($plusGrand, $propose);

            $apres = $c['finance'] + $propose;
            $lignes[] = [
                'id'          => $id,
                'titre'       => $c['titre'],
                'reseau'      => $c['reseau'],
                'zone'        => $c['zone'],
                'etat_reseau' => $c['etat_reseau'],
                'territoire'  => $c['territoire'],
                'statut'      => $c['statut'],
                'score'       => $c['score'],
                'cout'        => $c['cout'] / 100,
                'cout_source' => $c['cout_source'],
                'finance'     => $c['finance'] / 100,
                'besoin'      => $c['besoin'] / 100,
                'propose'     => $propose / 100,
                'apres'       => $apres / 100,
                'avant_pct'   => round(100 * $c['finance'] / $c['cout'], 1),
                'apres_pct'   => round(100 * $apres / $c['cout'], 1),
                'etat'        => $etat,
                'rang'        => $ordre[$id] ?? null,
                'raison'      => $raisons[$id] ?? '',
            ];
        }

        usort($lignes, fn ($a, $b) => [$b['propose'], $b['score'], $a['id']] <=> [$a['propose'], $a['score'], $b['id']]);

        $kpi = [
            'budget'               => $budget / 100,
            'alloue'               => $alloue / 100,
            'reste'                => ($budget - $alloue) / 100,
            'utilisation_pct'      => $budget > 0 ? round(100 * $alloue / $budget, 1) : 0.0,
            'nb_candidats'         => count($candidats),
            'nb_complets'          => $complets,
            'nb_partiels'          => $partiels,
            'nb_non_finances'      => $aucun,
            'besoin_total'         => $besoinTotal / 100,
            'besoin_couvert_pct'   => $besoinTotal > 0 ? round(100 * $alloue / $besoinTotal, 1) : 0.0,
            'priorite_couverte_pct' => $sommeScores > 0 ? round(100 * $scoreCouvert / $sommeScores, 1) : 0.0,
            'critiques_total'      => $critiques,
            'critiques_couverts'   => $critiquesCouverts,
            'concentration_pct'    => $alloue > 0 ? round(100 * $plusGrand / $alloue, 1) : 0.0,
            'hhi'                  => (int) round($hhi * 10000),
            'part_estimee_pct'     => $alloue > 0 ? round(100 * $surEstimes / $alloue, 1) : 0.0,
            // Nombre de gouvernorats qui reçoivent au moins une allocation
            'territoires_finances' => count(array_unique(array_filter(array_map(
                fn ($l) => $l['propose'] > 0 ? $l['territoire'] : null, $lignes)))),
        ];

        return [
            'strategie' => $strategie,
            'lettre'    => self::STRATEGIES[$strategie]['lettre'],
            'libelle'   => self::STRATEGIES[$strategie]['libelle'],
            'criteres'  => self::STRATEGIES[$strategie]['description'],
            'compromis' => self::STRATEGIES[$strategie]['compromis'],
            'kpi'       => $kpi,
            'lignes'    => $lignes,
            'risques'   => self::risques($kpi, $budget - $alloue, $prioritairesNonCouverts, $critiquesNonCouverts),
        ];
    }

    // Risques et compromis détectés par des règles fixes (indépendantes de l'IA)
    private static function risques(array $kpi, int $resteC, array $prioritaires, array $critiques): array
    {
        $risques = [];

        if ($prioritaires) {
            $risques[] = ['niveau' => 'danger', 'texte' => count($prioritaires) . ' projet(s) de priorité « Prioritaire » non financé(s) en entier : ' . self::liste($prioritaires) . '.'];
        }
        if ($critiques) {
            $risques[] = ['niveau' => 'warning', 'texte' => count($critiques) . ' projet(s) sur un réseau dégradé ou hors service restent sans financement complet : ' . self::liste($critiques) . '.'];
        }
        if ($kpi['nb_partiels'] > 0) {
            $risques[] = ['niveau' => 'warning', 'texte' => $kpi['nb_partiels'] . ' financement(s) partiel(s) : ils ne suffisent pas à achever les projets concernés.'];
        }
        if ($kpi['nb_complets'] + $kpi['nb_partiels'] >= 2 && $kpi['concentration_pct'] >= 60) {
            $risques[] = ['niveau' => 'warning', 'texte' => 'Un seul projet reçoit ' . self::pct($kpi['concentration_pct']) . ' du montant alloué : forte dépendance à ce chantier.'];
        }
        if ($kpi['part_estimee_pct'] >= 50) {
            $risques[] = ['niveau' => 'warning', 'texte' => self::pct($kpi['part_estimee_pct']) . ' du montant alloué repose sur des coûts estimés : à confirmer par des devis.'];
        }
        if ($resteC > 0 && $kpi['nb_non_finances'] + $kpi['nb_partiels'] > 0) {
            $risques[] = ['niveau' => 'info', 'texte' => 'Budget non utilisé : ' . self::montant($resteC) . ', car aucun projet restant ne tient entièrement dans ce reliquat.'];
        }
        if ($kpi['nb_candidats'] > 0 && $kpi['nb_non_finances'] === 0 && $kpi['nb_partiels'] === 0) {
            $risques[] = ['niveau' => 'info', 'texte' => 'Tous les projets candidats sont financés en entier.'];
        }

        return $risques;
    }

    // Meilleure stratégie pour chaque indicateur (plusieurs en cas d'égalité)
    private static function meilleurs(array $scenarios): array
    {
        $criteres = [
            'priorite_couverte_pct' => 'max', 'nb_complets' => 'max', 'critiques_couverts' => 'max',
            'besoin_couvert_pct' => 'max', 'nb_partiels' => 'min', 'reste' => 'min', 'territoires_finances' => 'max',
        ];
        $resultat = [];

        foreach ($criteres as $kpi => $sens) {
            $valeurs = array_map(fn ($s) => $s['kpi'][$kpi], $scenarios);
            $cible = $sens === 'max' ? max($valeurs) : min($valeurs);
            $resultat[$kpi] = array_keys(array_filter($valeurs, fn ($v) => abs($v - $cible) < 0.001));
        }

        return $resultat;
    }

    // Recommandation CALCULÉE (règle fixe, pas l'IA) : priorité couverte, puis projets achevés, puis moins de partiels
    private static function recommander(array $scenarios): array
    {
        $cles = array_keys($scenarios);
        usort($cles, function ($a, $b) use ($scenarios) {
            $ka = $scenarios[$a]['kpi'];
            $kb = $scenarios[$b]['kpi'];

            return [$kb['priorite_couverte_pct'], $kb['nb_complets'], $ka['nb_partiels'], array_search($a, array_keys(self::STRATEGIES))]
                <=> [$ka['priorite_couverte_pct'], $ka['nb_complets'], $kb['nb_partiels'], array_search($b, array_keys(self::STRATEGIES))];
        });

        $meilleur = $cles[0];
        $k = $scenarios[$meilleur]['kpi'];

        return [$meilleur, 'Règle de décision calculée : la stratégie qui couvre la plus grande part de la priorité totale ('
            . self::pct($k['priorite_couverte_pct']) . '), puis le plus de projets achevés (' . $k['nb_complets']
            . '), puis le moins de financements partiels (' . $k['nb_partiels'] . ').'];
    }

    // Données insuffisantes ou cas limites
    private static function diagnostic(array $retenus, array $ecartes, int $budget): array
    {
        $messages = [];
        $niveau = 'ok';

        if (! $retenus) {
            return ['niveau' => 'insuffisant', 'messages' => ['Aucun projet finançable : tous les projets sont exclus, terminés, sans coût ou déjà financés.']];
        }
        if ($budget === 0) {
            return ['niveau' => 'insuffisant', 'messages' => ['Le budget hypothétique est nul : rien ne peut être réparti.']];
        }

        $besoins = array_column($retenus, 'besoin');
        if (min($besoins) > $budget) {
            $niveau = 'attention';
            $messages[] = 'Le budget est inférieur au plus petit reste à financer (' . self::montant(min($besoins)) . ') : aucun projet ne peut être achevé ; seuls la répartition équilibrée et le mode partiel allouent des fonds.';
        }
        if (array_sum($besoins) <= $budget) {
            $messages[] = 'Le budget couvre tous les besoins (' . self::montant(array_sum($besoins)) . ') : les stratégies donnent le même résultat.';
        }

        $estimes = count(array_filter($retenus, fn ($c) => $c['cout_source'] !== 'declare'));
        if ($estimes > 0) {
            $niveau = 'attention';
            $messages[] = "{$estimes} projet(s) sur " . count($retenus) . " n'ont qu'un coût estimé (barème) : leurs montants sont indicatifs.";
        }

        $sansEtat = count(array_filter($retenus, fn ($c) => $c['etat_reseau'] === null));
        if ($sansEtat > 0) {
            $niveau = 'attention';
            $messages[] = "{$sansEtat} projet(s) ont un état de réseau inconnu : la stratégie A les place en dernier.";
        }

        return ['niveau' => $niveau, 'messages' => $messages];
    }

    private static function hypotheses(int $budget, array $retenus, array $ecartes, array $options): array
    {
        return array_values(array_filter([
            'Budget hypothétique de ' . self::montant($budget) . ' saisi par le gestionnaire : il ne correspond à aucun financement enregistré.',
            'Reste à financer = coût du projet − financements enregistrés au moment du calcul.',
            $options['inclure_estimes']
                ? 'Sans devis, le coût est estimé avec le barème de remplacement du module Analyse des risques.'
                : 'Seuls les projets dont le coût a été déclaré (devis) sont pris en compte.',
            $options['partiel']
                ? 'Financement partiel autorisé : le reliquat peut aller à un projet sans l\'achever.'
                : 'Financement partiel non autorisé pour les stratégies A, C et D (la stratégie B est partielle par nature).',
            $options['exclus'] ? count($options['exclus']) . ' projet(s) exclu(s) du scénario par le gestionnaire.' : null,
            'Les projets terminés ou déjà entièrement financés ne sont pas candidats.',
            'Les scores de priorité sont ceux calculés par l\'application au moment de la simulation.',
        ]));
    }

    // Contrôle final : le moteur refuse de rendre un résultat impossible
    private static function verifier(array $candidats, array $alloc, int $budget, string $strategie): void
    {
        $besoins = array_column($candidats, 'besoin', 'id');

        foreach ($alloc as $id => $montant) {
            if (! isset($besoins[$id]) || $montant < 0 || $montant > $besoins[$id]) {
                throw new LogicException("Stratégie {$strategie} : allocation impossible pour le projet {$id}.");
            }
        }
        if (array_sum($alloc) > $budget) {
            throw new LogicException("Stratégie {$strategie} : le budget est dépassé.");
        }
    }

    // ---------- Outils ----------

    private static function rangEtat(array $c): int
    {
        return self::RANG_ETAT[$c['etat_reseau']] ?? -1;
    }

    private static function centimes(float $montant): int
    {
        return (int) round($montant * 100);
    }

    public static function montant(int|float $centimes): string
    {
        return number_format($centimes / 100, $centimes % 100 === 0 ? 0 : 2, ',', ' ') . ' DT';
    }

    private static function pct(float $valeur): string
    {
        return str_replace('.', ',', (string) $valeur) . ' %';
    }

    private static function liste(array $titres): string
    {
        $extrait = array_slice($titres, 0, 3);

        return '« ' . implode(' », « ', $extrait) . ' »' . (count($titres) > 3 ? ' et ' . (count($titres) - 3) . ' autre(s)' : '');
    }
}
