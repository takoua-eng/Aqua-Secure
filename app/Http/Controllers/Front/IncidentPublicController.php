<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/*
 * Incidents et travaux côté citoyens (binôme 2) : consultation uniquement.
 * L'habitant voit ce qui le concerne, en langage simple : où, quoi, quel impact, où en est la réparation.
 * Les informations internes (score de priorité, analyse IA, historique, retards) ne sont jamais affichées.
 */
class IncidentPublicController extends Controller
{
    // État vu par l'habitant : libellé, couleur Bootstrap, icône Bootstrap Icons
    public const ETATS = [
        'signale'       => ['Signalé — intervention à programmer', 'danger',  'bi-exclamation-circle-fill'],
        'programme'     => ['Intervention programmée',             'info',    'bi-calendar-event'],
        'en_reparation' => ['Réparation en cours',                 'warning', 'bi-tools'],
        'resolu'        => ['Résolu',                              'success', 'bi-check-circle-fill'],
    ];

    // Gravité traduite pour l'habitant
    public const IMPACTS = [
        'faible'   => ['Impact faible',    'secondary'],
        'moyenne'  => ['Impact modéré',    'warning'],
        'critique' => ['Impact important', 'danger'],
    ];

    public const ICONES = [
        'fuite'         => 'bi-droplet-half',
        'contamination' => 'bi-virus',
        'coupure'       => 'bi-slash-circle',
    ];

    // Ce que l'incident peut changer pour l'habitant, et ce qu'il peut faire
    public const CONSIGNES = [
        'fuite'         => [
            'effet'   => 'Baisse de pression possible au robinet.',
            'conseil' => "Si vous voyez de l'eau s'écouler sur la voie publique, signalez-le.",
        ],
        'coupure'       => [
            'effet'   => "Interruption de l'alimentation en eau possible.",
            'conseil' => "Prévoyez une réserve d'eau pour les besoins essentiels jusqu'à la fin de la réparation.",
        ],
        'contamination' => [
            'effet'   => "La qualité de l'eau est en cours de vérification dans ce secteur.",
            'conseil' => "Suivez les consignes de la régie des eaux ; en cas de doute, utilisez de l'eau en bouteille pour boire et cuisiner.",
        ],
    ];

    public function index(Request $request)
    {
        $commune = is_string($request->query('commune')) && $request->query('commune') !== '' ? $request->query('commune') : null;
        $type = is_string($request->query('type')) && array_key_exists($request->query('type'), Incident::TYPES) ? $request->query('type') : null;
        $situation = in_array($request->query('situation'), ['en_cours', 'resolus'], true) ? $request->query('situation') : null;

        $incidents = Incident::query()
            ->visiblesPublic()
            ->with(['reseau.zone', 'interventions'])
            ->when($commune, fn ($q) => $q->whereHas('reseau.zone', fn ($z) => $z->where('commune', $commune)))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($situation === 'en_cours', fn ($q) => $q->nonResolus())
            ->when($situation === 'resolus', fn ($q) => $q->resolus())
            ->orderByDesc('date_signalement')
            ->orderByDesc('id')
            ->get();

        $etats = $incidents->mapWithKeys(fn (Incident $i) => [$i->id => self::etat($i)]);
        $enCours = $incidents->filter(fn (Incident $i) => $etats[$i->id] !== 'resolu');

        return view('front.incidents.index', [
            // Les plus gênants d'abord : impact important, puis le plus récent
            'enCours'     => $enCours->sortBy(fn (Incident $i) => [
                ['critique' => 0, 'moyenne' => 1, 'faible' => 2][$i->gravite] ?? 3, -$i->date_signalement->timestamp,
            ])->values(),
            'resolus'     => $incidents->filter(fn (Incident $i) => $etats[$i->id] === 'resolu')->values(),
            'etats'       => $etats,
            'communes'    => $this->communes(),
            'indicateurs' => [
                'en_cours'      => $enCours->count(),
                'en_reparation' => $etats->filter(fn ($e) => $e === 'en_reparation')->count(),
                'programmes'    => $etats->filter(fn ($e) => $e === 'programme')->count(),
                'resolus'       => $etats->filter(fn ($e) => $e === 'resolu')->count(),
            ],
            'carte'       => $this->pourCarte($incidents, $etats),
        ]);
    }

    // Fiche publique : état actuel, étapes du suivi, consignes, emplacement
    public function show(Incident $incident)
    {
        // Un incident résolu depuis longtemps n'est plus affiché au public
        abort_unless(Incident::visiblesPublic()->whereKey($incident->id)->exists(), 404);

        $incident->load(['reseau.zone', 'interventions']);
        $etat = self::etat($incident);

        return view('front.incidents.show', [
            'incident' => $incident,
            'etat'     => $etat,
            'detail'   => self::detail($incident, $etat),
            'etapes'   => self::etapes($incident, $etat),
            'position' => self::position($incident),
        ]);
    }

    // ---------- Traduction pour l'habitant ----------

    public static function etat(Incident $incident): string
    {
        return match (Incident::statutSelon($incident->interventions->pluck('statut'))) {
            'non_traite' => 'signale',
            'resolu'     => 'resolu',
            default      => $incident->interventions->contains('statut', 'en_cours') ? 'en_reparation' : 'programme',
        };
    }

    // Précision de l'état ; un retard interne n'est jamais affiché comme tel
    public static function detail(Incident $incident, string $etat): string
    {
        $interventions = $incident->interventions;

        return match ($etat) {
            'signale'       => 'Signalé le ' . $incident->date_signalement->format('d/m/Y') . '.',
            'programme'     => ($prevue = $interventions->where('statut', 'planifiee')->min('date_intervention')) && $prevue->gte(today())
                ? 'Intervention prévue le ' . $prevue->format('d/m/Y') . '.'
                : "Date d'intervention en cours de confirmation.",
            'en_reparation' => 'Équipe en intervention depuis le ' . $interventions->where('statut', 'en_cours')->min('date_intervention')->format('d/m/Y') . '.',
            'resolu'        => 'Réparé le ' . $interventions->max('date_intervention')->format('d/m/Y') . '.',
        };
    }

    // Les 4 étapes du suivi, cochées jusqu'à l'état actuel (une nouvelle intervention après résolution repart à « programmée »)
    public static function etapes(Incident $incident, string $etat): array
    {
        $interventions = $incident->interventions;
        $rang = array_search($etat, array_keys(self::ETATS), true);
        $commencees = $interventions->whereIn('statut', Intervention::STATUTS_COMMENCES);

        $dates = [
            'signale'       => $incident->date_signalement,
            'programme'     => $etat === 'programme'
                ? $interventions->where('statut', 'planifiee')->min('date_intervention')
                : $interventions->min('date_intervention'),
            'en_reparation' => $commencees->min('date_intervention'),
            'resolu'        => $etat === 'resolu' ? $interventions->max('date_intervention') : null,
        ];

        return collect(['signale' => 'Incident constaté', 'programme' => 'Intervention programmée', 'en_reparation' => 'Réparation en cours', 'resolu' => 'Résolu'])
            ->map(function ($libelle, $cle) use ($rang, $dates, $etat) {
                $faite = array_search($cle, array_keys(self::ETATS), true) <= $rang;
                $date = $faite ? $dates[$cle] : null;

                // Une date prévue déjà passée sans démarrage n'est pas affichée (retard interne)
                if ($date && $cle === 'programme' && $etat === 'programme' && $date->lt(today())) {
                    $date = null;
                }

                return ['libelle' => $libelle, 'faite' => $faite, 'date' => $date?->format('d/m/Y')];
            })
            ->values()
            ->all();
    }

    // Position du réseau, sinon centre de la commune (approximative)
    public static function position(Incident $incident): ?array
    {
        $reseau = $incident->reseau;
        if ($reseau->latitude !== null && $reseau->longitude !== null) {
            return ['lat' => $reseau->latitude, 'lng' => $reseau->longitude, 'approximative' => false];
        }

        $centre = Zone::coordonneesCommune($reseau->zone->commune);

        return $centre ? ['lat' => $centre[0], 'lng' => $centre[1], 'approximative' => true] : null;
    }

    // ---------- Outils ----------

    // Communes qui ont au moins un incident visible (liste du filtre)
    private function communes(): array
    {
        return Incident::visiblesPublic()->with('reseau.zone')->get()
            ->map(fn (Incident $i) => $i->reseau->zone->commune)
            ->unique()->sort()->values()->all();
    }

    private function pourCarte(Collection $incidents, Collection $etats): array
    {
        return $incidents
            ->map(fn (Incident $i) => ($position = self::position($i)) ? $position + [
                'type' => $i->libelleType(),
                'zone' => $i->reseau->zone->nom . ' · ' . $i->reseau->zone->commune,
                'etat' => $etats[$i->id],
                'url'  => route('incidents.show', $i),
            ] : null)
            ->filter()
            ->values()
            ->all();
    }
}
