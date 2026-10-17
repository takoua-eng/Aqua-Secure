<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Capteur;
use App\Models\Releve;
use App\Models\Zone;
use App\Services\AnalyseCapteur;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/*
 * Surveillance du réseau côté citoyens (binôme 4) : consultation uniquement.
 * Les mesures des capteurs sont traduites en messages simples
 * (pression normale, pression faible, maintenance…) ; les seuils techniques restent visibles.
 */
class SurveillanceController extends Controller
{
    // Apparence de chaque état : libellé, couleur Bootstrap, icône Bootstrap Icons
    public const ETATS = [
        'normal'      => ['Fonctionnement normal', 'success',   'bi-check-circle-fill'],
        'alerte'      => ['Mesure anormale',       'danger',    'bi-exclamation-triangle-fill'],
        'maintenance' => ['En maintenance',        'warning',   'bi-tools'],
        'inconnu'     => ['Pas de mesure récente', 'secondary', 'bi-hourglass-split'],
    ];

    // Ce que la mesure signifie pour l'habitant
    public const EXPLICATIONS = [
        'pression'    => "La pression indique la force de l'eau au robinet. Trop basse : faible débit ou coupure ; trop haute : risque de fuite.",
        'debit'       => "Le débit est la quantité d'eau qui circule dans la canalisation. Une chute brutale peut signaler une fuite ou une coupure.",
        'niveau'      => "Le niveau d'eau du réservoir ou du château d'eau. Un niveau bas peut annoncer des coupures aux heures de pointe.",
        'temperature' => "La température de l'eau dans le réseau. Une eau trop chaude favorise le développement des bactéries.",
    ];

    public function index(Request $request)
    {
        // Les capteurs hors service ne sont pas montrés au public
        $capteurs = Capteur::query()
            ->where('statut', '!=', 'hors_service')
            ->with(['reseau.zone', 'dernierReleve'])
            ->when($request->commune, fn ($q, $commune) => $q->whereHas('reseau.zone', fn ($z) => $z->where('commune', $commune)))
            ->orderBy('code')
            ->get();

        $etats = $capteurs->mapWithKeys(fn (Capteur $c) => [$c->id => self::etat($c)]);

        // Une zone est "perturbée" dès qu'un de ses capteurs a une mesure anormale
        $zones = $capteurs->groupBy(fn (Capteur $c) => $c->reseau->zone->id)
            ->map(function (Collection $liste) use ($etats) {
                $zone = $liste->first()->reseau->zone;
                $alertes = $liste->filter(fn ($c) => $etats[$c->id] === 'alerte')->count();

                return [
                    'zone'     => $zone,
                    'capteurs' => $liste,
                    'alertes'  => $alertes,
                    'etat'     => $alertes ? 'alerte' : 'normal',
                ];
            })
            ->sortByDesc('alertes')
            ->values();

        $mesures24h = Releve::whereIn('capteur_id', $capteurs->pluck('id'))
            ->where('date_releve', '>=', now()->subDay())
            ->count();

        return view('front.surveillance.index', [
            'zones'       => $zones,
            'etats'       => $etats,
            'communes'    => $this->communes(),
            'indicateurs' => [
                'capteurs'  => $capteurs->count(),
                'normaux'   => $etats->filter(fn ($e) => $e === 'normal')->count(),
                'alertes'   => $etats->filter(fn ($e) => $e === 'alerte')->count(),
                'mesures'   => $mesures24h,
            ],
            'carte'       => $this->pourCarte($capteurs, $etats),
        ]);
    }

    // Fiche publique d'un capteur : état actuel, courbe des 7 derniers jours, tendance
    public function show(Capteur $capteur)
    {
        abort_if($capteur->statut === 'hors_service', 404);

        $capteur->load(['reseau.zone', 'dernierReleve']);

        $releves = $capteur->releves()
            ->where('date_releve', '>=', now()->subDays(7))
            ->orderBy('date_releve')
            ->get();

        // Tendance calculée par l'IA (régression linéaire sur les derniers relevés)
        $derniers = $capteur->releves()->orderByDesc('date_releve')->limit(50)->get()->reverse()->values();
        $prevision = AnalyseCapteur::prevision($capteur, $derniers);

        return view('front.surveillance.show', [
            'capteur'     => $capteur,
            'etat'        => self::etat($capteur),
            'explication' => self::EXPLICATIONS[$capteur->type_mesure] ?? '',
            'graphique'   => $releves->map(fn (Releve $r) => ['x' => $r->date_releve->getTimestampMs(), 'y' => round($r->valeur, 2)])->values(),
            'resume'      => [
                'nombre'     => $releves->count(),
                'min'        => $releves->min('valeur'),
                'max'        => $releves->max('valeur'),
                'hors_seuil' => $releves->where('hors_seuil', true)->count(),
            ],
            'tendance'    => $prevision['tendance'] ?? null,
            'derniers'    => $releves->sortByDesc('date_releve')->take(8),
        ]);
    }

    // État d'un capteur vu par le citoyen
    public static function etat(Capteur $capteur): string
    {
        if ($capteur->statut === 'en_panne') {
            return 'maintenance';
        }
        if (! $capteur->dernierReleve || $capteur->estMuet()) {
            return 'inconnu';
        }

        return $capteur->dernierReleve->hors_seuil ? 'alerte' : 'normal';
    }

    // Communes qui ont au moins un capteur visible (liste du filtre)
    private function communes(): array
    {
        return Zone::whereHas('reseaux.capteurs', fn ($q) => $q->where('statut', '!=', 'hors_service'))
            ->orderBy('commune')
            ->pluck('commune')
            ->unique()
            ->values()
            ->all();
    }

    // Capteurs placés sur la carte à la position de leur réseau
    private function pourCarte(Collection $capteurs, Collection $etats): array
    {
        return $capteurs
            ->filter(fn (Capteur $c) => $c->reseau->latitude !== null && $c->reseau->longitude !== null)
            ->map(fn (Capteur $c) => [
                'lat'    => $c->reseau->latitude,
                'lng'    => $c->reseau->longitude,
                'nom'    => $c->libelleTypeMesure() . ' — ' . $c->reseau->zone->nom,
                'valeur' => $c->dernierReleve ? $c->avecUnite($c->dernierReleve->valeur) : null,
                'etat'   => $etats[$c->id],
                'url'    => route('surveillance.show', $c),
            ])
            ->values()
            ->all();
    }
}