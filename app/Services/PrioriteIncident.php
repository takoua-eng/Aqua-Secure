<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Intervention;
use Illuminate\Support\Collection;

/*
 * Priorisation des incidents (binôme 2) : moteur de règles métier explicable, score sur 100.
 * Ce n'est PAS un modèle d'apprentissage automatique : les poids sont fixés par expertise métier,
 * chaque point attribué est justifié et affiché.
 *
 *   Gravité            40 pts max   faible 10 · moyenne 25 · critique 40
 *   Type               25 pts max   fuite 10 · coupure 15 · contamination 25 (risque sanitaire)
 *   Ancienneté         15 pts max   1 point par tranche de 2 jours depuis le signalement
 *   Prise en charge    15 pts max   aucune intervention 15 · planifiée en retard 12 · planifiée 8 · en cours 3
 *   État du réseau      5 pts max   hors service 5 · dégradé 3 · bon 0 (colonne reseaux_eau.etat)
 */
class PrioriteIncident
{
    public const POINTS_GRAVITE = ['faible' => 10, 'moyenne' => 25, 'critique' => 40];

    public const POINTS_TYPE = ['fuite' => 10, 'coupure' => 15, 'contamination' => 25];

    public const MAX_ANCIENNETE = 15;
    public const JOURS_PAR_POINT = 2;

    public const POINTS_PRISE_EN_CHARGE = ['aucune' => 15, 'planifiee_en_retard' => 12, 'planifiee' => 8, 'en_cours' => 3];

    public const POINTS_ETAT_RESEAU = ['hors_service' => 5, 'degrade' => 3, 'bon' => 0];

    // Seuil minimal => niveau (du plus urgent au moins urgent)
    public const NIVEAUX = [
        ['seuil' => 70, 'cle' => 'urgente', 'libelle' => 'Urgente', 'couleur' => 'red'],
        ['seuil' => 45, 'cle' => 'haute',   'libelle' => 'Haute',   'couleur' => 'orange'],
        ['seuil' => 25, 'cle' => 'normale', 'libelle' => 'Normale', 'couleur' => 'yellow'],
        ['seuil' => 0,  'cle' => 'basse',   'libelle' => 'Basse',   'couleur' => 'green'],
    ];

    // Un réseau qui a au moins ce nombre d'incidents sur 12 mois est "à incidents récurrents"
    public const SEUIL_RECURRENCE = 3;

    /*
     * Score d'un incident (relations reseau et interventions chargées de préférence) :
     * ['score' => 82, 'niveau' => [...], 'facteurs' => [[cle, libelle, points, max, explication], ...],
     *  'action' => '...', 'resolu' => false]
     */
    public static function calculer(Incident $incident): array
    {
        $interventions = $incident->interventions;

        if (Incident::statutSelon($interventions->pluck('statut')) === 'resolu') {
            return [
                'score'    => 0,
                'niveau'   => null,
                'facteurs' => [],
                'action'   => 'Aucune action : toutes les interventions sont terminées.',
                'resolu'   => true,
            ];
        }

        $jours = $incident->joursDepuisSignalement();
        $priseEnCharge = self::priseEnCharge($interventions);
        $etatReseau = $incident->reseau?->etat;

        $facteurs = [
            [
                'cle'         => 'gravite',
                'libelle'     => 'Gravité',
                'points'      => self::POINTS_GRAVITE[$incident->gravite] ?? 0,
                'max'         => max(self::POINTS_GRAVITE),
                'explication' => 'Gravité ' . mb_strtolower($incident->libelleGravite()),
            ],
            [
                'cle'         => 'type',
                'libelle'     => "Type d'incident",
                'points'      => self::POINTS_TYPE[$incident->type] ?? 0,
                'max'         => max(self::POINTS_TYPE),
                'explication' => match ($incident->type) {
                    'contamination' => 'Contamination : risque sanitaire pour la population',
                    'coupure'       => "Coupure : privation d'accès à l'eau",
                    'fuite'         => "Fuite : pertes d'eau",
                    default         => $incident->libelleType(),
                },
            ],
            [
                'cle'         => 'anciennete',
                'libelle'     => 'Ancienneté',
                'points'      => min(self::MAX_ANCIENNETE, intdiv($jours, self::JOURS_PAR_POINT)),
                'max'         => self::MAX_ANCIENNETE,
                'explication' => match (true) {
                    $jours === 0 => "Signalé aujourd'hui",
                    $jours === 1 => 'Signalé hier',
                    default      => "Signalé il y a {$jours} jours",
                },
            ],
            [
                'cle'         => 'prise_en_charge',
                'libelle'     => 'Prise en charge',
                'points'      => self::POINTS_PRISE_EN_CHARGE[$priseEnCharge['cle']],
                'max'         => max(self::POINTS_PRISE_EN_CHARGE),
                'explication' => $priseEnCharge['explication'],
            ],
            [
                'cle'         => 'etat_reseau',
                'libelle'     => 'État du réseau',
                'points'      => self::POINTS_ETAT_RESEAU[$etatReseau] ?? 0,
                'max'         => max(self::POINTS_ETAT_RESEAU),
                'explication' => $etatReseau ? 'Réseau ' . mb_strtolower($incident->reseau->libelleEtat()) : 'État du réseau inconnu',
            ],
        ];

        $score = min(100, array_sum(array_column($facteurs, 'points')));

        return [
            'score'    => $score,
            'niveau'   => self::niveau($score),
            'facteurs' => $facteurs,
            'action'   => self::action($incident, $priseEnCharge),
            'resolu'   => false,
        ];
    }

    public static function niveau(int $score): array
    {
        foreach (self::NIVEAUX as $niveau) {
            if ($score >= $niveau['seuil']) {
                return $niveau;
            }
        }

        return self::NIVEAUX[array_key_last(self::NIVEAUX)];
    }

    // Incidents non résolus triés par score décroissant (à score égal : le plus ancien d'abord)
    public static function classement(): Collection
    {
        return Incident::nonResolus()
            ->with(['reseau.zone', 'interventions'])
            ->get()
            ->map(fn (Incident $incident) => ['incident' => $incident, 'priorite' => self::calculer($incident)])
            ->sort(fn ($a, $b) => [$b['priorite']['score'], $a['incident']->date_signalement->timestamp, $a['incident']->id]
                <=> [$a['priorite']['score'], $b['incident']->date_signalement->timestamp, $b['incident']->id])
            ->values();
    }

    // Réseaux ayant au moins SEUIL_RECURRENCE incidents sur les 12 derniers mois
    public static function reseauxRecurrents(): Collection
    {
        return Incident::query()
            ->selectRaw('reseau_id, COUNT(*) as nombre, MAX(date_signalement) as dernier')
            ->whereDate('date_signalement', '>=', today()->subMonths(12)->toDateString())
            ->groupBy('reseau_id')
            ->havingRaw('COUNT(*) >= ?', [self::SEUIL_RECURRENCE])
            ->orderByDesc('nombre')
            ->with('reseau.zone')
            ->get();
    }

    // ---------- Outils ----------

    // Situation de prise en charge d'après les interventions actives
    private static function priseEnCharge(Collection $interventions): array
    {
        $actives = $interventions->filter(fn (Intervention $i) => in_array($i->statut, Intervention::STATUTS_ACTIFS, true));

        if ($actives->contains(fn (Intervention $i) => $i->statut === 'en_cours')) {
            return ['cle' => 'en_cours', 'explication' => 'Intervention en cours', 'intervention' => $actives->firstWhere('statut', 'en_cours')];
        }

        $enRetard = $actives->first(fn (Intervention $i) => $i->estEnRetard());
        if ($enRetard) {
            $retard = (int) $enRetard->date_intervention->diffInDays(today());

            return [
                'cle'          => 'planifiee_en_retard',
                'explication'  => "Intervention prévue le {$enRetard->date_intervention->format('d/m/Y')}, non démarrée ({$retard} j de retard)",
                'intervention' => $enRetard,
            ];
        }

        if ($actives->isNotEmpty()) {
            $prochaine = $actives->sortBy('date_intervention')->first();

            return [
                'cle'          => 'planifiee',
                'explication'  => "Intervention planifiée le {$prochaine->date_intervention->format('d/m/Y')}",
                'intervention' => $prochaine,
            ];
        }

        return ['cle' => 'aucune', 'explication' => 'Aucune intervention planifiée', 'intervention' => null];
    }

    // Action recommandée, en fonction de la prise en charge et du type
    private static function action(Incident $incident, array $priseEnCharge): string
    {
        $intervention = $priseEnCharge['intervention'];

        $action = match ($priseEnCharge['cle']) {
            'aucune'              => $incident->gravite === 'critique'
                ? 'Planifier une intervention immédiatement : incident critique non pris en charge.'
                : 'Planifier une intervention.',
            'planifiee_en_retard' => "Démarrer l'intervention prévue le {$intervention->date_intervention->format('d/m/Y')}, ou la replanifier.",
            'planifiee'           => "Confirmer l'équipe pour l'intervention du {$intervention->date_intervention->format('d/m/Y')}.",
            'en_cours'            => "Suivre l'intervention en cours jusqu'à sa clôture.",
        };

        if ($incident->type === 'contamination') {
            $action .= " Prévoir une analyse de contrôle de l'eau après intervention.";
        }

        return $action;
    }
}
