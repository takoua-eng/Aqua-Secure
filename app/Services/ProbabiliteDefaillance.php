<?php

namespace App\Services;

use App\Models\ReseauEau;

/*
 * Probabilité de panne d'un élément du réseau, avec une loi de Weibull
 * (le modèle classique du vieillissement des infrastructures).
 *
 *   Risque cumulé à l'âge t :  H(t) = (t / η)^β
 *     η (eta)  = durée de vie caractéristique du matériau (63 % des éléments ont lâché à cet âge)
 *     β (beta) = vitesse d'usure : plus β est grand, plus les pannes se concentrent en fin de vie
 *
 *   Probabilité de panne dans les N prochaines années, sachant que l'élément tient encore à l'âge t :
 *     P = 1 - exp(-(H(t + N) - H(t)) × m)
 *   où m multiplie le risque selon l'état observé (un élément déjà dégradé lâche plus vite).
 *
 * Les paramètres sont des ordres de grandeur issus de la littérature sur les réseaux d'eau,
 * à recaler quand l'historique des pannes réelles sera suffisant.
 */
class ProbabiliteDefaillance
{
    // matériau => [β, η en années]
    public const PARAMETRES = [
        'fonte'          => [3.0, 70],
        'acier'          => [2.5, 55],
        'pvc'            => [2.2, 60],
        'pehd'           => [2.5, 90],
        'beton'          => [3.0, 95],
        'amiante_ciment' => [3.0, 55],
    ];

    // État observé => multiplicateur du risque
    public const MULTIPLICATEURS = ['bon' => 1.0, 'degrade' => 3.0];

    /**
     * @return array{horsService: bool, un_an: ?float, cinq_ans: ?float, vie_restante: ?int}
     *   probabilités en % (0-100), vie restante médiane en années ; null si l'élément est déjà hors service
     */
    public static function pourReseau(ReseauEau $reseau): array
    {
        if ($reseau->etat === 'hors_service') {
            return ['horsService' => true, 'un_an' => null, 'cinq_ans' => null, 'vie_restante' => null];
        }

        [$beta, $eta] = self::PARAMETRES[$reseau->materiau] ?? [2.5, 60];
        $m = self::MULTIPLICATEURS[$reseau->etat] ?? 1.0;
        $age = max(0, $reseau->age());

        $risque = fn (float $t) => ($t / $eta) ** $beta;
        $probabilite = fn (int $annees) => round(100 * (1 - exp(-($risque($age + $annees) - $risque($age)) * $m)), 1);

        // Vie restante médiane : âge supplémentaire x tel que la probabilité de panne atteigne 50 %
        //   (H(t + x) - H(t)) × m = ln 2   =>   t + x = η × (ln2 / m + H(t))^(1/β)
        $vieRestante = (int) round($eta * ((log(2) / $m + $risque($age)) ** (1 / $beta)) - $age);

        return [
            'horsService'  => false,
            'un_an'        => $probabilite(1),
            'cinq_ans'     => $probabilite(5),
            'vie_restante' => max(0, $vieRestante),
        ];
    }

    // Couleur Tabler pour une probabilité à 5 ans
    public static function couleur(?float $pourcentage): string
    {
        return match (true) {
            $pourcentage === null => 'red',
            $pourcentage >= 40    => 'red',
            $pourcentage >= 20    => 'orange',
            $pourcentage >= 8     => 'yellow',
            default               => 'green',
        };
    }
}
