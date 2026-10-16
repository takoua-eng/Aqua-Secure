<?php

namespace App\Services;

use App\Models\ReseauEau;
use Illuminate\Support\Collection;

/*
 * Plan de renouvellement : quels éléments rénover avec un budget donné pour réduire le plus le risque ?
 *
 * C'est le problème du sac à dos (0/1) : chaque élément a un coût estimé et un gain
 * (les points de risque supprimés s'il est remplacé à neuf). La programmation dynamique
 * trouve la combinaison au gain total maximal sans dépasser le budget.
 * Le plan "naïf" (rénover dans l'ordre du score, tant que le budget le permet) sert de comparaison.
 */
class PlanRenovation
{
    // Coût estimé d'un remplacement, en TND (ordres de grandeur, à ajuster)
    public const COUT_TYPE = ['canalisation' => 120000, 'reservoir' => 400000, 'station' => 900000];

    // Surcoût selon le matériau retiré (désamiantage, dépose de fonte…)
    public const FACTEUR_MATERIAU = ['amiante_ciment' => 1.3, 'fonte' => 1.1];

    // Le calcul se fait par tranches de 10 000 TND (précision largement suffisante)
    private const PAS = 10000;

    public static function cout(ReseauEau $reseau): int
    {
        $cout = (self::COUT_TYPE[$reseau->type] ?? 200000) * (self::FACTEUR_MATERIAU[$reseau->materiau] ?? 1.0);

        return (int) (round($cout / self::PAS) * self::PAS);
    }

    /**
     * Gain = points de risque supprimés. Un élément neuf garde seulement les points
     * liés à la population de sa zone (qui ne changent pas) : tout le reste disparaît.
     */
    public static function gain(array $risque): int
    {
        $population = collect($risque['facteurs'])->last()['points'] ?? 0;

        return max(0, $risque['score'] - $population);
    }

    /**
     * @return array{choisis: Collection, naif: array, budget: int, cout: int, gain: int, risqueAvant: int, risqueApres: int}
     */
    public static function calculer(int $budget): array
    {
        // Candidats : uniquement les éléments dont le remplacement fait vraiment baisser le risque
        $candidats = ReseauEau::with('zone')
            ->withMax('historiques as etat_depuis', 'created_at')
            ->get()
            ->map(function (ReseauEau $r) {
                $risque = ScoreRisque::pourReseau($r);

                return ['reseau' => $r, 'risque' => $risque, 'cout' => self::cout($r), 'gain' => self::gain($risque)];
            })
            ->filter(fn ($c) => $c['gain'] >= 10)
            ->values();

        $choisis = self::sacADos($candidats, $budget);
        $risqueTotal = (int) $candidats->sum(fn ($c) => $c['risque']['score']);

        return [
            'choisis'     => $choisis->sortByDesc(fn ($c) => $c['risque']['score'])->values(),
            'naif'        => self::planNaif($candidats, $budget),
            'budget'      => $budget,
            'cout'        => (int) $choisis->sum('cout'),
            'gain'        => (int) $choisis->sum('gain'),
            'nbCandidats' => $candidats->count(),
            'risqueAvant' => $risqueTotal,
            'risqueApres' => $risqueTotal - (int) $choisis->sum('gain'),
        ];
    }

    // Programmation dynamique : meilleur[b] = gain maximal avec un budget de b tranches
    private static function sacADos(Collection $candidats, int $budget): Collection
    {
        $capacite = intdiv($budget, self::PAS);
        $meilleur = array_fill(0, $capacite + 1, 0);
        $garde = [];   // $garde[i][b] = true si l'élément i fait partie du meilleur choix pour b

        foreach ($candidats as $i => $c) {
            $poids = intdiv($c['cout'], self::PAS);
            for ($b = $capacite; $b >= $poids; $b--) {
                if ($meilleur[$b - $poids] + $c['gain'] > $meilleur[$b]) {
                    $meilleur[$b] = $meilleur[$b - $poids] + $c['gain'];
                    $garde[$i][$b] = true;
                }
            }
        }

        // On remonte les choix depuis le budget complet
        $choisis = collect();
        $b = $capacite;
        for ($i = $candidats->count() - 1; $i >= 0; $i--) {
            if (! empty($garde[$i][$b])) {
                $choisis->push($candidats[$i]);
                $b -= intdiv($candidats[$i]['cout'], self::PAS);
            }
        }

        return $choisis;
    }

    // Comparaison : on prend les plus hauts scores d'abord, tant qu'ils rentrent dans le budget
    private static function planNaif(Collection $candidats, int $budget): array
    {
        $reste = $budget;
        $gain = 0;
        $nombre = 0;

        foreach ($candidats->sortByDesc(fn ($c) => $c['risque']['score']) as $c) {
            if ($c['cout'] <= $reste) {
                $reste -= $c['cout'];
                $gain += $c['gain'];
                $nombre++;
            }
        }

        return ['gain' => $gain, 'cout' => $budget - $reste, 'nombre' => $nombre];
    }
}
