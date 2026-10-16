<?php

namespace App\Services;

use App\Models\HistoriqueEtat;
use App\Models\ReseauEau;
use Illuminate\Support\Carbon;

// Chiffres des graphiques du tableau de bord (parc d'infrastructure)
class StatistiquesReseau
{
    public const TRANCHES_AGE = [
        '0-9 ans' => [0, 9], '10-19 ans' => [10, 19], '20-29 ans' => [20, 29],
        '30-39 ans' => [30, 39], '40-49 ans' => [40, 49], '50 ans et +' => [50, PHP_INT_MAX],
    ];

    public static function calculer(): array
    {
        $reseaux = ReseauEau::all();

        return [
            'total'     => $reseaux->count(),
            'etats'     => collect(ReseauEau::ETATS)->map(fn ($libelle, $cle) => [
                'cle' => $cle, 'libelle' => $libelle, 'nombre' => $reseaux->where('etat', $cle)->count(),
            ])->values()->all(),
            'materiaux' => collect(ReseauEau::MATERIAUX)
                ->map(fn ($libelle, $cle) => ['libelle' => $libelle, 'nombre' => $reseaux->where('materiau', $cle)->count()])
                ->sortByDesc('nombre')->values()->all(),
            'ages'      => collect(self::TRANCHES_AGE)->map(fn ($bornes, $libelle) => [
                'libelle' => $libelle,
                'nombre'  => $reseaux->filter(fn ($r) => $r->age() >= $bornes[0] && $r->age() <= $bornes[1])->count(),
            ])->values()->all(),
            'evolution' => self::evolution(12),
        ];
    }

    /*
     * Nombre d'éléments dégradés / hors service à la fin de chacun des N derniers mois,
     * reconstitué à partir de l'historique des états (dernier état connu à cette date).
     */
    public static function evolution(int $mois): array
    {
        $historique = HistoriqueEtat::orderBy('created_at')->orderBy('id')->get(['reseau_eau_id', 'nouvel_etat', 'created_at']);
        $resultat = [];

        for ($i = $mois - 1; $i >= 0; $i--) {
            $fin = Carbon::now()->startOfMonth()->subMonths($i)->endOfMonth();

            // État de chaque élément à cette date = sa dernière ligne d'historique avant la date
            $etats = $historique->filter(fn ($h) => $h->created_at <= $fin)
                ->groupBy('reseau_eau_id')
                ->map(fn ($lignes) => $lignes->last()->nouvel_etat);

            $resultat[] = [
                'mois'         => ucfirst($fin->locale('fr')->isoFormat('MMM YY')),
                'degrade'      => $etats->filter(fn ($e) => $e === 'degrade')->count(),
                'hors_service' => $etats->filter(fn ($e) => $e === 'hors_service')->count(),
            ];
        }

        return $resultat;
    }
}
