<?php

namespace App\Services\Renovation;

use InvalidArgumentException;

/*
 * Binôme 3 — plan pluriannuel : le What If? Engine rejoué année après année avec un budget annuel HYPOTHÉTIQUE.
 *
 * Chaque année : la stratégie choisie répartit le budget (+ le reliquat de l'année précédente si demandé) ;
 * les projets financés en entier sortent de la liste, les financements partiels s'ajoutent à ce qui est déjà financé ;
 * les autres projets sont reportés à l'année suivante.
 * Hypothèses affichées : budget constant, coûts constants (pas d'inflation), aucun nouveau projet ni financement extérieur.
 * Calcul pur (aucune écriture en base), déterministe.
 */
class PlanPluriannuel
{
    public const ANNEES_MAX = 5;

    /**
     * @return array{annees: list<array>, restants: list<array>, acheves: int, candidats: int, alloue: float, hypotheses: list<string>}
     */
    public static function calculer(array $candidats, float $budgetAnnuel, int $annees, string $strategie, bool $reporterReliquat, int $anneeDepart): array
    {
        if (! isset(MoteurWhatIf::STRATEGIES[$strategie])) {
            throw new InvalidArgumentException('Stratégie inconnue.');
        }
        if ($annees < 1 || $annees > self::ANNEES_MAX) {
            throw new InvalidArgumentException('Le plan couvre de 1 à ' . self::ANNEES_MAX . ' ans.');
        }

        $restants = array_values(array_filter($candidats, fn ($c) => $c['statut'] !== 'termine'));
        $nbDepart = MoteurWhatIf::comparer($restants, 0)['candidats'];
        $reliquat = 0.0;
        $plan = [];
        $acheves = 0;
        $alloueTotal = 0.0;

        for ($i = 0; $i < $annees; $i++) {
            $budget = round($budgetAnnuel + ($reporterReliquat ? $reliquat : 0), 2);
            $budget = min($budget, MoteurWhatIf::BUDGET_MAX);
            $scenario = MoteurWhatIf::comparer($restants, $budget, ['partiel' => false])['scenarios'][$strategie];

            $finances = array_values(array_filter($scenario['lignes'], fn ($l) => $l['propose'] > 0));
            $parId = array_column($finances, null, 'id');

            // L'argent alloué cette année compte dans la dotation du gouvernorat (stratégie « Équité ») les années suivantes
            $parTerritoire = [];
            foreach ($finances as $l) {
                if (($l['territoire'] ?? null) !== null) {
                    $parTerritoire[$l['territoire']] = ($parTerritoire[$l['territoire']] ?? 0) + $l['propose'];
                }
            }
            foreach ($restants as &$c) {
                if (isset($c['territoire'], $parTerritoire[$c['territoire']])) {
                    $c['territoire_finance'] = round((float) ($c['territoire_finance'] ?? 0) + $parTerritoire[$c['territoire']], 2);
                }
            }
            unset($c);

            // Mise à jour de la liste pour l'année suivante
            $suivants = [];
            foreach ($restants as $c) {
                $l = $parId[$c['id']] ?? null;
                if ($l && $l['etat'] === 'complet') {
                    continue;   // projet achevé : il sort du plan
                }
                if ($l) {
                    $c['finance'] = round((float) $c['finance'] + $l['propose'], 2);   // financement partiel
                }
                $suivants[] = $c;
            }
            $restants = $suivants;

            $nbAcheves = count(array_filter($finances, fn ($l) => $l['etat'] === 'complet'));
            $acheves += $nbAcheves;
            $alloueTotal += $scenario['kpi']['alloue'];
            $reliquat = $scenario['kpi']['reste'];

            $plan[] = [
                'annee'    => $anneeDepart + $i,
                'budget'   => $budget,
                'alloue'   => $scenario['kpi']['alloue'],
                'reliquat' => $reliquat,
                'acheves'  => $nbAcheves,
                'projets'  => array_map(fn ($l) => ['id' => $l['id'], 'titre' => $l['titre'], 'reseau' => $l['reseau'],
                    'propose' => $l['propose'], 'etat' => $l['etat'], 'territoire' => $l['territoire'] ?? null], $finances),
            ];
        }

        $encore = MoteurWhatIf::comparer($restants, 0);

        return [
            'annees'    => $plan,
            'restants'  => array_map(fn ($l) => ['id' => $l['id'], 'titre' => $l['titre'], 'reseau' => $l['reseau'], 'besoin' => $l['besoin']],
                $encore['scenarios'][$strategie]['lignes']),
            'reste_a_financer' => $encore['besoin_total'],
            'acheves'   => $acheves,
            'candidats' => $nbDepart,
            'alloue'    => round($alloueTotal, 2),
            'hypotheses' => [
                'Budget annuel hypothétique de ' . MoteurWhatIf::montant((int) round($budgetAnnuel * 100)) . ', identique chaque année.',
                $reporterReliquat ? 'Le budget non utilisé une année est reporté sur l\'année suivante.' : 'Le budget non utilisé une année est perdu (pas de report).',
                'Coûts constants (pas d\'inflation) ; aucun nouveau projet ni financement extérieur pendant la période.',
                'Stratégie appliquée chaque année : ' . MoteurWhatIf::STRATEGIES[$strategie]['lettre'] . ' — ' . MoteurWhatIf::STRATEGIES[$strategie]['libelle'] . '.',
            ],
        ];
    }
}
