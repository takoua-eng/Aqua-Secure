<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Intervention;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Statistiques du module Incidents / Interventions (binôme 2), calculées à partir de la base.
 * Filtres facultatifs : ['du' => 'AAAA-MM-JJ', 'au' => 'AAAA-MM-JJ', 'type' => 'fuite'|'contamination'|'coupure']
 * appliqués à la date de signalement et au type des incidents ; les interventions comptées sont celles des incidents filtrés.
 */
class StatistiquesIncidents
{
    // L'évolution mensuelle couvre au plus ce nombre de mois
    public const MOIS_MAX = 24;

    public static function calculer(array $filtres = []): array
    {
        $f = self::normaliser($filtres);
        $incidents = fn () => self::filtrer(Incident::query(), $f);
        $interventions = fn () => self::filtrerInterventions(Intervention::query(), $f);

        $total = $incidents()->count();
        $resolus = $incidents()->resolus()->count();

        return [
            'filtres'                   => $f,
            'total'                     => $total,
            'parType'                   => self::repartition($incidents(), 'type', Incident::TYPES),
            'parGravite'                => self::repartition($incidents(), 'gravite', Incident::GRAVITES),
            'parStatut'                 => [
                ['cle' => 'non_traite',    'libelle' => Incident::STATUTS['non_traite'],    'nombre' => $incidents()->nonTraites()->count()],
                ['cle' => 'en_traitement', 'libelle' => Incident::STATUTS['en_traitement'], 'nombre' => $incidents()->enTraitement()->count()],
                ['cle' => 'resolu',        'libelle' => Incident::STATUTS['resolu'],        'nombre' => $resolus],
            ],
            'parZone'                   => self::parZone($f),
            'evolution'                 => self::evolution(12, $f),
            'tauxResolution'            => $total ? round($resolus / $total * 100, 1) : null,
            'delaiPriseEnCharge'        => self::delaiPriseEnCharge($incidents()),
            'dureeResolution'           => self::dureeResolution($incidents()),
            'interventions'             => collect(Intervention::STATUTS)->map(fn ($libelle, $cle) => [
                'cle' => $cle, 'libelle' => $libelle, 'nombre' => $interventions()->where('statut', $cle)->count(),
            ])->values()->all(),
            'interventionsEnRetard'     => $interventions()->enRetard()->count(),
            'critiquesSansIntervention' => $incidents()->critiquesSansIntervention()->count(),
        ];
    }

    // Filtres connus uniquement ; une valeur absente ou vide vaut null
    public static function normaliser(array $filtres): array
    {
        return [
            'du'   => ($filtres['du'] ?? null) ?: null,
            'au'   => ($filtres['au'] ?? null) ?: null,
            'type' => array_key_exists($filtres['type'] ?? '', Incident::TYPES) ? $filtres['type'] : null,
        ];
    }

    public static function filtreActif(array $filtres): bool
    {
        return (bool) array_filter(self::normaliser($filtres));
    }

    private static function filtrer(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['du'], fn ($q, $du) => $q->whereDate('incidents.date_signalement', '>=', $du))
            ->when($f['au'], fn ($q, $au) => $q->whereDate('incidents.date_signalement', '<=', $au))
            ->when($f['type'], fn ($q, $type) => $q->where('incidents.type', $type));
    }

    // Interventions des incidents filtrés (sans filtre : toutes les interventions)
    private static function filtrerInterventions(Builder $query, array $f): Builder
    {
        return array_filter($f)
            ? $query->whereHas('incident', fn ($q) => self::filtrer($q, $f))
            : $query;
    }

    // [['cle' => 'fuite', 'libelle' => 'Fuite', 'nombre' => 12], ...] — toutes les valeurs, même à 0
    private static function repartition(Builder $query, string $colonne, array $libelles): array
    {
        $nombres = $query->select($colonne, DB::raw('COUNT(*) as nombre'))
            ->groupBy($colonne)->pluck('nombre', $colonne);

        return collect($libelles)->map(fn ($libelle, $cle) => [
            'cle' => $cle, 'libelle' => $libelle, 'nombre' => (int) ($nombres[$cle] ?? 0),
        ])->values()->all();
    }

    // Les 10 zones qui ont le plus d'incidents (jointure en lecture seule sur reseaux_eau et zones)
    private static function parZone(array $f): array
    {
        return self::filtrer(Incident::query(), $f)
            ->join('reseaux_eau', 'reseaux_eau.id', '=', 'incidents.reseau_id')
            ->join('zones', 'zones.id', '=', 'reseaux_eau.zone_id')
            ->selectRaw('zones.nom as libelle, COUNT(*) as nombre')
            ->groupBy('zones.id', 'zones.nom')
            ->orderByDesc('nombre')
            ->limit(10)
            ->get()
            ->map(fn ($ligne) => ['libelle' => $ligne->libelle, 'nombre' => (int) $ligne->nombre])
            ->all();
    }

    /*
     * Incidents signalés chaque mois (dont critiques).
     * Sans période : les N derniers mois. Avec une période : ses mois, MOIS_MAX au plus (les plus récents).
     */
    public static function evolution(int $mois, array $filtres = []): array
    {
        $f = self::normaliser($filtres);
        $fin = ($f['au'] ? Carbon::parse($f['au']) : Carbon::now())->startOfMonth();
        $debut = $f['du'] ? Carbon::parse($f['du'])->startOfMonth() : $fin->copy()->subMonths($mois - 1);

        if ($debut->gt($fin)) {
            $debut = $fin->copy();
        }
        if ($debut->diffInMonths($fin) >= self::MOIS_MAX) {
            $debut = $fin->copy()->subMonths(self::MOIS_MAX - 1);
        }

        $incidents = self::filtrer(Incident::query(), $f)
            ->whereDate('date_signalement', '>=', $debut->toDateString())
            ->whereDate('date_signalement', '<=', $fin->copy()->endOfMonth()->toDateString())
            ->get(['date_signalement', 'gravite']);

        $resultat = [];
        for ($moisCourant = $debut->copy(); $moisCourant->lte($fin); $moisCourant->addMonth()) {
            $duMois = $incidents->filter(fn ($incident) => $incident->date_signalement->isSameMonth($moisCourant));

            $resultat[] = [
                'mois'      => ucfirst($moisCourant->locale('fr')->isoFormat('MMM YY')),
                'total'     => $duMois->count(),
                'critiques' => $duMois->where('gravite', 'critique')->count(),
            ];
        }

        return $resultat;
    }

    /*
     * Délai moyen de prise en charge : du signalement à la première intervention commencée
     * (en cours ou terminée). null s'il n'y a encore aucune donnée.
     */
    private static function delaiPriseEnCharge(Builder $incidents): ?array
    {
        $liste = $incidents
            ->whereHas('interventions', fn ($q) => $q->whereIn('statut', Intervention::STATUTS_COMMENCES))
            ->withMin(['interventions as premiere_intervention' => fn ($q) => $q->whereIn('statut', Intervention::STATUTS_COMMENCES)], 'date_intervention')
            ->get(['id', 'date_signalement']);

        return self::moyenneJours($liste, 'premiere_intervention');
    }

    // Durée moyenne de résolution : du signalement à la dernière intervention terminée (incidents résolus)
    private static function dureeResolution(Builder $incidents): ?array
    {
        $liste = $incidents->resolus()
            ->withMax('interventions as derniere_intervention', 'date_intervention')
            ->get(['id', 'date_signalement']);

        return self::moyenneJours($liste, 'derniere_intervention');
    }

    private static function moyenneJours($incidents, string $colonneFin): ?array
    {
        if ($incidents->isEmpty()) {
            return null;
        }

        $jours = $incidents->map(fn ($incident) => max(0, (int) $incident->date_signalement->diffInDays(Carbon::parse($incident->{$colonneFin}))));

        return ['jours' => round($jours->avg(), 1), 'base' => $incidents->count()];
    }
}
