<?php

namespace App\Services\Renovation;

use App\Models\Financement;
use App\Models\ProjetRenovation;
use Illuminate\Support\Facades\DB;

/*
 * Smart Budget Monitoring : indicateurs calculés UNIQUEMENT à partir des données enregistrées.
 * Le taux de couverture « fiable » ne porte que sur les projets dont le coût est déclaré (devis) ;
 * la couverture estimée est donnée à part, signalée comme indicative.
 */
class SuiviBudget
{
    public static function calculer(): array
    {
        $projets = CandidatsWhatIf::requete()->withCount('financements')->get()
            ->each(function (ProjetRenovation $p) {
                $p->setAttribute('priorite', ScorePriorite::pourProjet($p));
            });

        $parSource = Financement::query()
            ->select('source', DB::raw('SUM(montant) as total'), DB::raw('COUNT(*) as nombre'))
            ->groupBy('source')
            ->get()
            ->keyBy('source');
        $totalFinance = round((float) $parSource->sum('total'), 2);

        $sources = collect(Financement::SOURCES)->map(fn ($libelle, $cle) => [
            'cle'     => $cle,
            'libelle' => $libelle,
            'montant' => round((float) ($parSource[$cle]->total ?? 0), 2),
            'nombre'  => (int) ($parSource[$cle]->nombre ?? 0),
            'part'    => $totalFinance > 0 ? round(100 * ($parSource[$cle]->total ?? 0) / $totalFinance, 1) : 0.0,
            'couleur' => Financement::couleurSourceDe($cle),
            'icone'   => Financement::iconeSourceDe($cle),
        ])->values();

        $declares = $projets->filter(fn ($p) => $p->priorite['cout']['source'] === 'declare');
        // Estimés = sans devis mais avec un coût (prédit par le modèle IA ou barème)
        $estimes = $projets->filter(fn ($p) => in_array($p->priorite['cout']['source'], ['predit', 'estime'], true));
        $actifs = $projets->where('statut', '!=', 'termine');

        $coutDeclare = round($declares->sum(fn ($p) => $p->priorite['cout']['montant']), 2);
        $financeDeclare = round($declares->sum(fn ($p) => min($p->totalFinance(), $p->priorite['cout']['montant'])), 2);
        $coutEstime = round($estimes->sum(fn ($p) => $p->priorite['cout']['montant']), 2);
        $financeEstime = round($estimes->sum(fn ($p) => min($p->totalFinance(), $p->priorite['cout']['montant'])), 2);

        return [
            'nb_projets'       => $projets->count(),
            'par_statut'       => collect(ProjetRenovation::STATUTS)->map(fn ($libelle, $cle) => [
                'cle' => $cle, 'libelle' => $libelle, 'nombre' => $projets->where('statut', $cle)->count(),
                'montant' => round($projets->where('statut', $cle)->sum(fn ($p) => $p->totalFinance()), 2),
            ])->values(),
            'total_finance'    => $totalFinance,
            'nb_financements'  => (int) $parSource->sum('nombre'),
            'sources'          => $sources,
            'sans_financement' => $actifs->filter(fn ($p) => $p->financements_count === 0)->values(),
            'couverture'       => [
                'nb_declares'      => $declares->count(),
                'cout_declare'     => $coutDeclare,
                'finance_declare'  => $financeDeclare,
                'taux'             => $coutDeclare > 0 ? round(100 * $financeDeclare / $coutDeclare, 1) : null,
                'nb_estimes'       => $estimes->count(),
                'cout_estime'      => $coutEstime,
                'taux_estime'      => $coutEstime > 0 ? round(100 * $financeEstime / $coutEstime, 1) : null,
                'reste_declare'    => round(max(0, $coutDeclare - $financeDeclare), 2),
            ],
            // Projets non terminés, du plus prioritaire au moins prioritaire
            'prioritaires'     => $actifs->sortBy([fn ($a, $b) => $b->priorite['score'] <=> $a->priorite['score'], fn ($a, $b) => $a->id <=> $b->id])
                ->take(6)->values(),
            'projets'          => $projets,
        ];
    }
}
