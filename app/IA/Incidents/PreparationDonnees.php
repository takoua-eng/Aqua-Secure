<?php

namespace App\IA\Incidents;

use App\Models\Incident;
use App\Models\Intervention;
use App\Models\ReseauEau;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Préparation des données de l'analyse IA des incidents (binôme 2). LECTURE SEULE de la base.
 *
 * Une observation = un réseau d'eau pendant un mois. Variables (toutes numériques) :
 *   incidents, critiques, part de contaminations, écart avec les 3 mois précédents,
 *   délai moyen de prise en charge, relevés de capteurs hors seuil (tables du binôme 4, lues sans modification).
 * Les caractéristiques fixes du réseau (âge, matériau, état) ne sont pas utilisées : on cherche des
 * COMPORTEMENTS inhabituels, pas les réseaux anciens (déjà couverts par le score de risque du binôme 1).
 */
class PreparationDonnees
{
    // Fenêtre d'analyse : au plus ce nombre de mois, jusqu'au mois en cours
    public const MOIS_MAX = 24;

    // Nombre de mois précédents pour mesurer une hausse
    public const MOIS_TENDANCE = 3;

    // Ordre des variables = ordre des colonnes données au modèle
    public const VARIABLES = [
        'incidents'             => ['libelle' => 'Incidents signalés dans le mois', 'unite' => ''],
        'critiques'             => ['libelle' => 'Incidents critiques', 'unite' => ''],
        'part_contamination'    => ['libelle' => 'Part de contaminations', 'unite' => ' %'],
        'hausse'                => ['libelle' => 'Écart avec la moyenne des 3 mois précédents', 'unite' => ''],
        'delai_prise_en_charge' => ['libelle' => 'Délai moyen de prise en charge', 'unite' => ' j'],
        'releves_hors_seuil'    => ['libelle' => 'Relevés de capteurs hors seuil', 'unite' => ''],
    ];

    // Conditions minimales pour lancer le modèle : en dessous, aucun résultat n'est produit
    public const CRITERES = [
        'incidents'              => ['libelle' => 'Incidents enregistrés', 'minimum' => 30],
        'mois_couverts'          => ['libelle' => 'Mois différents couverts par les incidents', 'minimum' => 6],
        'reseaux_avec_incidents' => ['libelle' => 'Réseaux ayant au moins un incident', 'minimum' => 3],
        'observations'           => ['libelle' => 'Observations (réseau × mois)', 'minimum' => 36],
    ];

    // Audit des données disponibles (volume, période, répartition, qualité)
    public static function diagnostic(): array
    {
        $dates = Incident::query()->toBase()->selectRaw('MIN(date_signalement) as premier, MAX(date_signalement) as dernier')->first();
        $capteurs = self::capteursDisponibles();

        return [
            'incidents'                   => Incident::count(),
            'interventions'               => Intervention::count(),
            'premier'                     => $dates?->premier ? substr((string) $dates->premier, 0, 10) : null,
            'dernier'                     => $dates?->dernier ? substr((string) $dates->dernier, 0, 10) : null,
            'mois_couverts'               => Incident::query()->toBase()->pluck('date_signalement')
                ->map(fn ($date) => substr((string) $date, 0, 7))->unique()->count(),
            'reseaux_total'               => ReseauEau::count(),
            'reseaux_avec_incidents'      => Incident::query()->distinct()->count('reseau_id'),
            'max_incidents_par_reseau'    => (int) (Incident::query()->toBase()->selectRaw('COUNT(*) as n')
                ->groupBy('reseau_id')->orderByDesc('n')->value('n') ?? 0),
            'par_type'                    => collect(Incident::TYPES)->map(fn ($libelle, $cle) => [
                'cle' => $cle, 'libelle' => $libelle, 'nombre' => Incident::where('type', $cle)->count(),
            ])->values()->all(),
            'par_gravite'                 => collect(Incident::GRAVITES)->map(fn ($libelle, $cle) => [
                'cle' => $cle, 'libelle' => $libelle, 'nombre' => Incident::where('gravite', $cle)->count(),
            ])->values()->all(),
            'incidents_sans_intervention' => Incident::nonTraites()->count(),
            'incidents_avec_delai'        => Incident::whereHas('interventions', fn ($q) => $q->whereIn('statut', Intervention::STATUTS_COMMENCES))->count(),
            'capteurs_disponibles'        => $capteurs,
            'releves'                     => $capteurs ? DB::table('releves')->count() : 0,
            'releves_hors_seuil'          => $capteurs ? DB::table('releves')->where('hors_seuil', true)->count() : 0,
        ];
    }

    /*
     * Observations réseau × mois, du mois du premier incident (au plus MOIS_MAX mois en arrière) au mois en cours.
     * ['debut' => 'AAAA-MM-JJ', 'fin' => ..., 'mois' => ['2026-01', ...], 'delai_median' => 2.0,
     *  'lignes' => [['reseau_id', 'reseau', 'zone', 'mois', 'valeurs' => [variable => nombre], 'delai_impute' => bool], ...]]
     */
    public static function observations(): array
    {
        $premier = Incident::min('date_signalement');
        if (! $premier) {
            return ['debut' => null, 'fin' => null, 'mois' => [], 'lignes' => [], 'delai_median' => null];
        }

        $fin = Carbon::now()->startOfMonth();
        $debut = Carbon::parse($premier)->startOfMonth();
        if ($debut->lt($fin->copy()->subMonths(self::MOIS_MAX - 1))) {
            $debut = $fin->copy()->subMonths(self::MOIS_MAX - 1);
        }

        $mois = [];
        for ($m = $debut->copy(); $m->lte($fin); $m->addMonth()) {
            $mois[] = $m->format('Y-m');
        }

        // On lit aussi les mois précédant la fenêtre, pour calculer la hausse des premiers mois
        $incidents = Incident::query()
            ->with(['interventions' => fn ($q) => $q->whereIn('statut', Intervention::STATUTS_COMMENCES)])
            ->whereDate('date_signalement', '>=', $debut->copy()->subMonths(self::MOIS_TENDANCE)->toDateString())
            ->get(['id', 'reseau_id', 'type', 'gravite', 'date_signalement']);

        $parReseauMois = $incidents->groupBy(fn (Incident $i) => $i->reseau_id . '|' . $i->date_signalement->format('Y-m'));

        // Délai d'un incident : du signalement à sa première intervention commencée (null si aucune)
        $delai = fn (Incident $i) => $i->interventions->isEmpty()
            ? null
            : max(0, (int) $i->date_signalement->diffInDays($i->interventions->min('date_intervention')));

        // Valeur de remplacement quand un mois n'a aucun délai mesurable : la médiane observée (neutre)
        $delais = $incidents->map($delai)->filter(fn ($d) => $d !== null);
        $delaiMedian = $delais->isEmpty() ? 0.0 : (float) $delais->median();

        $horsSeuil = self::relevesHorsSeuil($debut, $fin);

        $lignes = [];
        foreach (ReseauEau::with('zone')->orderBy('code')->get() as $reseau) {
            foreach ($mois as $m) {
                $duMois = $parReseauMois->get($reseau->id . '|' . $m, collect());
                $nombre = $duMois->count();

                $precedents = 0;
                for ($k = 1; $k <= self::MOIS_TENDANCE; $k++) {
                    $cle = $reseau->id . '|' . Carbon::parse($m . '-01')->subMonths($k)->format('Y-m');
                    $precedents += $parReseauMois->get($cle, collect())->count();
                }

                $delaisDuMois = $duMois->map($delai)->filter(fn ($d) => $d !== null);

                $lignes[] = [
                    'reseau_id'    => $reseau->id,
                    'reseau'       => $reseau->code,
                    'zone'         => $reseau->zone?->nom,
                    'mois'         => $m,
                    'valeurs'      => [
                        'incidents'             => $nombre,
                        'critiques'             => $duMois->where('gravite', 'critique')->count(),
                        'part_contamination'    => $nombre ? round($duMois->where('type', 'contamination')->count() / $nombre * 100, 1) : 0.0,
                        'hausse'                => round($nombre - $precedents / self::MOIS_TENDANCE, 2),
                        'delai_prise_en_charge' => $delaisDuMois->isEmpty() ? $delaiMedian : round($delaisDuMois->avg(), 1),
                        'releves_hors_seuil'    => $horsSeuil[$reseau->id . '|' . $m] ?? 0,
                    ],
                    'delai_impute' => $delaisDuMois->isEmpty(),
                ];
            }
        }

        return [
            'debut'        => $debut->toDateString(),
            'fin'          => $fin->copy()->endOfMonth()->toDateString(),
            'mois'         => $mois,
            'lignes'       => $lignes,
            'delai_median' => $delaiMedian,
        ];
    }

    // Chaque critère de CRITERES avec sa valeur réelle et son verdict
    public static function suffisance(array $diagnostic, int $observations): array
    {
        $valeurs = [
            'incidents'              => $diagnostic['incidents'],
            'mois_couverts'          => $diagnostic['mois_couverts'],
            'reseaux_avec_incidents' => $diagnostic['reseaux_avec_incidents'],
            'observations'           => $observations,
        ];

        return collect(self::CRITERES)->map(fn ($critere, $cle) => [
            'cle'     => $cle,
            'libelle' => $critere['libelle'],
            'valeur'  => $valeurs[$cle],
            'minimum' => $critere['minimum'],
            'ok'      => $valeurs[$cle] >= $critere['minimum'],
        ])->values()->all();
    }

    // ---------- Données des capteurs (binôme 4, lecture seule) ----------

    private static function capteursDisponibles(): bool
    {
        return Schema::hasTable('capteurs') && Schema::hasTable('releves');
    }

    // ['reseau_id|AAAA-MM' => nombre de relevés hors seuil]
    private static function relevesHorsSeuil(Carbon $debut, Carbon $fin): array
    {
        if (! self::capteursDisponibles()) {
            return [];
        }

        $mois = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', releves.date_releve)"
            : "DATE_FORMAT(releves.date_releve, '%Y-%m')";

        return DB::table('releves')
            ->join('capteurs', 'capteurs.id', '=', 'releves.capteur_id')
            ->where('releves.hors_seuil', true)
            ->whereBetween('releves.date_releve', [$debut->copy()->startOfDay(), $fin->copy()->endOfMonth()])
            ->selectRaw("capteurs.reseau_id as reseau_id, {$mois} as mois, COUNT(*) as nombre")
            ->groupBy('capteurs.reseau_id', DB::raw($mois))
            ->get()
            ->mapWithKeys(fn ($ligne) => [$ligne->reseau_id . '|' . $ligne->mois => (int) $ligne->nombre])
            ->all();
    }
}
