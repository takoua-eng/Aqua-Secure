<?php

namespace App\Services\Renovation;

/*
 * Binôme 3 — robustesse d'un scénario What If? face à l'incertitude des coûts (simulation de Monte-Carlo).
 *
 * Hypothèse affichée et réglable : le coût réel d'un projet varie au hasard (loi uniforme) autour du coût connu :
 *   ± incertitude_estime pour un coût estimé (barème), ± incertitude_devis pour un coût déclaré (devis).
 * Pour chaque tirage, on se demande : pour achever les projets que le scénario finance EN ENTIER, combien faudra-t-il
 * vraiment ? Le scénario « tient » si ce total (plus les financements partiels inchangés) reste dans le budget.
 *
 * Le pourcentage obtenu N'EST PAS une probabilité réelle : c'est une conséquence mathématique des hypothèses saisies.
 * Graine fixe => mêmes paramètres, même résultat (reproductible). Aucune écriture en base.
 */
class RobustesseScenario
{
    public const TIRAGES = 1000;

    public const GRAINE = 20261018;

    /**
     * @param array $scenario un scénario renvoyé par MoteurWhatIf::comparer (clé « lignes »)
     * @return array{tient_pct: float, total_median: float, total_p95: float, surcout_p95: float, tirages: int, incertitude_estime: float, incertitude_devis: float}
     */
    public static function evaluer(array $scenario, float $budget, float $incertitudeEstime = 0.3, float $incertitudeDevis = 0.1, int $tirages = self::TIRAGES): array
    {
        $lignes = array_values(array_filter($scenario['lignes'], fn ($l) => $l['propose'] > 0));
        $alloue = array_sum(array_column($lignes, 'propose'));

        mt_srand(self::GRAINE);
        $totaux = [];
        $tient = 0;

        for ($t = 0; $t < $tirages; $t++) {
            $total = 0.0;
            foreach ($lignes as $l) {
                if ($l['etat'] !== 'complet') {
                    $total += $l['propose'];   // financement partiel : montant inchangé
                    continue;
                }
                $u = $l['cout_source'] === 'declare' ? $incertitudeDevis : $incertitudeEstime;
                $coutReel = $l['cout'] * (1 + $u * (2 * mt_rand() / mt_getrandmax() - 1));
                $total += max($l['propose'], $coutReel - $l['finance']);
            }
            $totaux[] = $total;
            $tient += $total <= $budget + 0.005 ? 1 : 0;
        }
        mt_srand();   // on rend un générateur imprévisible au reste de l'application

        sort($totaux);
        $quantile = fn (float $q) => $totaux ? $totaux[(int) min(count($totaux) - 1, floor($q * count($totaux)))] : 0.0;

        return [
            'tient_pct'          => $tirages > 0 ? round(100 * $tient / $tirages, 1) : 0.0,
            'total_median'       => round($quantile(0.5), 2),
            'total_p95'          => round($quantile(0.95), 2),
            'surcout_p95'        => round(max(0, $quantile(0.95) - $alloue), 2),
            'tirages'            => $tirages,
            'incertitude_estime' => $incertitudeEstime,
            'incertitude_devis'  => $incertitudeDevis,
        ];
    }

    /** Robustesse de toutes les stratégies d'une comparaison. */
    public static function pourComparaison(array $comparaison, float $incertitudeEstime, float $incertitudeDevis): array
    {
        return array_map(fn ($s) => self::evaluer($s, $comparaison['budget'], $incertitudeEstime, $incertitudeDevis), $comparaison['scenarios']);
    }
}
