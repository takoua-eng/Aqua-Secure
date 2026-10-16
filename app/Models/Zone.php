<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    protected $fillable = ['nom', 'commune', 'population'];

    // ZONE 1 — N RESEAU_EAU
    public function reseaux(): HasMany
    {
        return $this->hasMany(ReseauEau::class);
    }

    // Libellé utilisé dans les listes déroulantes : "Centre-ville — Tunis"
    public function libelle(): string
    {
        return "{$this->nom} — {$this->commune}";
    }

    // Communes pour la liste déroulante, groupées par gouvernorat : ['Tunis' => ['La Marsa' => 'La Marsa', ...], ...]
    public static function communesParGouvernorat(): array
    {
        return collect(config('communes'))
            ->map(fn ($communes) => array_combine(array_keys($communes), array_keys($communes)))
            ->all();
    }

    // Noms de toutes les communes autorisées (pour la validation)
    public static function nomsCommunes(): array
    {
        return collect(config('communes'))->flatMap(fn ($communes) => array_keys($communes))->all();
    }

    // [latitude, longitude] du centre de la commune, ou null si elle n'est pas dans la liste
    public static function coordonneesCommune(string $commune): ?array
    {
        foreach (config('communes') as $communes) {
            if (isset($communes[$commune])) {
                return $communes[$commune];
            }
        }

        return null;
    }

    // État le plus grave parmi les réseaux de la zone (null si aucun réseau)
    public function etatGlobal(): ?string
    {
        $etats = $this->reseaux->pluck('etat');

        foreach (['hors_service', 'degrade', 'bon'] as $etat) {
            if ($etats->contains($etat)) {
                return $etat;
            }
        }

        return null;
    }

    // Données des zones pour la carte du tableau de bord (zones + leurs réseaux)
    public static function pourCarte(): array
    {
        $dejaPlacees = [];
        $groupes = \App\Services\GroupesZones::calculer()['parZone'];   // groupe K-means de chaque zone

        return static::with([
                // etat_depuis = date du dernier changement d'état (table historique_etats)
                'reseaux' => fn ($q) => $q->orderBy('type')->orderBy('code')
                    ->withMax('historiques as etat_depuis', 'created_at'),
            ])
            ->orderBy('commune')->orderBy('nom')
            ->get()
            ->map(function (Zone $zone) use (&$dejaPlacees, $groupes) {
                $places = $zone->reseaux->filter(fn ($r) => $r->latitude !== null && $r->longitude !== null);

                if ($places->isNotEmpty()) {
                    // Des réseaux ont une position exacte : la zone est placée au milieu d'eux
                    $lat = $places->avg('latitude');
                    $lng = $places->avg('longitude');
                } else {
                    [$lat, $lng] = self::coordonneesCommune($zone->commune) ?? [36.8065, 10.1815];

                    // Plusieurs zones dans la même commune : on les écarte en spirale autour du centre
                    $rang = $dejaPlacees[$zone->commune] = ($dejaPlacees[$zone->commune] ?? -1) + 1;
                    $angle = deg2rad($rang * 137.5);
                    $rayon = 0.012 * sqrt($rang);
                    $lat += $rayon * sin($angle);
                    $lng += $rayon * cos($angle);
                }

                return [
                    'id'         => $zone->id,
                    'nom'        => $zone->nom,
                    'commune'    => $zone->commune,
                    'population' => $zone->population,
                    'lat'        => round($lat, 5),
                    'lng'        => round($lng, 5),
                    'etat'       => $zone->etatGlobal(),
                    'risque'     => \App\Services\ScoreRisque::pourZone($zone),
                    'groupe'     => $groupes[$zone->id] ?? null,
                    'reseaux'    => $zone->reseaux->map(fn (ReseauEau $r) => [
                        'id'           => $r->id,
                        'code'         => $r->code,
                        'type'         => $r->type,
                        'libelleType'  => $r->libelleType(),
                        'materiau'     => $r->libelleMateriau(),
                        'age'          => $r->age(),
                        'etat'         => $r->etat,
                        'libelleEtat'  => $r->libelleEtat(),
                        'etatDepuis'   => $r->etat_depuis ? \Illuminate\Support\Carbon::parse($r->etat_depuis)->format('d/m/Y') : null,
                        'lat'          => $r->latitude,
                        'lng'          => $r->longitude,
                    ])->all(),
                ];
            })
            ->all();
    }
}
