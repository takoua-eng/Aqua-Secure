<?php

namespace App\Services\Renovation;

use App\Models\Financement;
use App\Models\ProjetRenovation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Binôme 3 — données du rapport mensuel (toutes calculées à partir de la base, aucune écriture).
 * L'IA rédige ensuite une synthèse à partir de CES chiffres seulement (AnalyseFinancementIA::syntheseMensuelle).
 */
class RapportMensuel
{
    public static function donnees(Carbon $mois): array
    {
        $debut = $mois->copy()->startOfMonth();
        $fin = $mois->copy()->endOfMonth();
        $precedent = [$debut->copy()->subMonthNoOverflow()->startOfMonth(), $debut->copy()->subMonthNoOverflow()->endOfMonth()];

        $duMois = Financement::whereBetween('created_at', [$debut, $fin]);
        $parSource = (clone $duMois)->select('source', DB::raw('SUM(montant) as total'), DB::raw('COUNT(*) as nombre'))
            ->groupBy('source')->get()
            ->map(fn ($l) => ['source' => Financement::libelleSourceDe($l->source), 'montant' => round((float) $l->total, 2), 'nombre' => (int) $l->nombre])
            ->values()->all();

        $suivi = SuiviBudget::calculer();
        $alertes = DetectionRisquesFinanciers::pourBase();
        $equite = EquiteTerritoriale::lorenz(array_values(array_filter(EquiteTerritoriale::parGouvernorat(), fn ($t) => $t['population'] > 0)));

        return [
            'mois'                    => $debut->locale('fr')->translatedFormat('F Y'),
            'financements_du_mois'    => [
                'nombre'     => (clone $duMois)->count(),
                'montant'    => round((float) (clone $duMois)->sum('montant'), 2),
                'par_source' => $parSource,
            ],
            'montant_mois_precedent'  => round((float) Financement::whereBetween('created_at', $precedent)->sum('montant'), 2),
            'projets_crees_du_mois'   => ProjetRenovation::whereBetween('created_at', [$debut, $fin])->count(),
            'situation_actuelle'      => [
                'projets'                 => $suivi['nb_projets'],
                'par_statut'              => collect($suivi['par_statut'])->mapWithKeys(fn ($s) => [$s['libelle'] => $s['nombre']])->all(),
                'total_finance'           => $suivi['total_finance'],
                'projets_sans_financement' => $suivi['sans_financement']->count(),
                'taux_couverture_devis_pct' => $suivi['couverture']['taux'],
                'indice_gini_territorial' => $equite['gini'],
            ],
            'alertes' => array_map(fn ($a) => ['code' => $a['code'], 'niveau' => $a['niveau'], 'titre' => $a['titre']], array_slice($alertes, 0, 15)),
            'projets_prioritaires' => $suivi['prioritaires']->take(5)->map(fn ($p) => [
                'titre' => $p->titre, 'score' => $p->priorite['score'], 'statut' => $p->libelleStatut(),
            ])->values()->all(),
        ];
    }
}
