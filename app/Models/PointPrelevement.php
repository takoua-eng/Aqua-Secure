<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PointPrelevement extends Model
{
    protected $table = 'points_prelevement';

    // Valeur en base => libellé affiché
    public const TYPES = [
        'robinet_public' => 'Robinet public',
        'reservoir'      => 'Réservoir',
        'sortie_station' => 'Sortie de station',
    ];

    // Au-delà de ce nombre de jours sans analyse, un point en service est "en retard"
    public const JOURS_RETARD = 30;

    protected $fillable = [
        'reseau_id', 'code', 'nom', 'adresse', 'latitude', 'longitude', 'type_point', 'actif',
    ];

    protected $attributes = [
        'actif' => true,
    ];

    protected function casts(): array
    {
        return [
            'latitude'  => 'float',
            'longitude' => 'float',
            'actif'     => 'boolean',
        ];
    }

    // RESEAU_EAU 1 — N POINT_PRELEVEMENT
    public function reseau(): BelongsTo
    {
        return $this->belongsTo(ReseauEau::class, 'reseau_id');
    }

    // POINT_PRELEVEMENT 1 — N ANALYSE_QUALITE
    public function analyses(): HasMany
    {
        return $this->hasMany(AnalyseQualite::class, 'point_id');
    }

    // Analyse la plus récente du point (la plus grande date, puis le plus grand id)
    public function derniereAnalyse(): HasOne
    {
        return $this->hasOne(AnalyseQualite::class, 'point_id')->ofMany(['date_prelevement' => 'max', 'id' => 'max']);
    }

    public function libelleType(): string
    {
        return self::TYPES[$this->type_point] ?? $this->type_point;
    }

    // Icône Tabler associée au type
    public function iconeType(): string
    {
        return ['robinet_public' => 'droplet', 'reservoir' => 'cylinder', 'sortie_station' => 'building-factory-2'][$this->type_point] ?? 'droplet';
    }

    public function libelleActif(): string
    {
        return $this->actif ? 'En service' : 'Hors service';
    }

    // Libellé utilisé dans les listes déroulantes : "PP-TN-012 — Fontaine Bab Saadoun"
    public function libelle(): string
    {
        return "{$this->code} — {$this->nom}";
    }

    // Pourcentage d'analyses conformes (null si aucune analyse)
    public function tauxConformite(): ?float
    {
        $analyses = $this->relationLoaded('analyses') ? $this->analyses : $this->analyses()->get(['conforme']);

        if ($analyses->isEmpty()) {
            return null;
        }

        return round($analyses->where('conforme', true)->count() / $analyses->count() * 100, 1);
    }

    // Retard d'analyse d'un point en service : null si à jour (ou hors service),
    // sinon "Jamais analysé" ou "Pas d'analyse depuis plus de 30 jours"
    public static function retardAnalyse(bool $actif, $derniereDate): ?string
    {
        if (! $actif) {
            return null;
        }
        if ($derniereDate === null) {
            return 'Jamais analysé';
        }

        return \Illuminate\Support\Carbon::parse($derniereDate)->lt(now()->subDays(self::JOURS_RETARD)->startOfDay())
            ? 'Pas d\'analyse depuis plus de ' . self::JOURS_RETARD . ' jours'
            : null;
    }

    // Points en service sans analyse récente (filtre "en retard" des listes)
    public function scopeEnRetard($query)
    {
        return $query->where('actif', true)->whereDoesntHave('analyses', fn ($q) => $q
            ->whereDate('date_prelevement', '>=', now()->subDays(self::JOURS_RETARD)->toDateString()));
    }

    // Données d'un point pour les cartes Leaflet (relations reseau.zone et derniereAnalyse chargées)
    public function pourCarte(): array
    {
        $derniere = $this->derniereAnalyse;

        return [
            'id'        => $this->id,
            'code'      => $this->code,
            'nom'       => $this->nom,
            'adresse'   => $this->adresse,
            'type'      => $this->libelleType(),
            'reseau'    => $this->reseau->code,
            'commune'   => $this->reseau->zone->commune,
            'actif'     => $this->actif,
            'lat'       => $this->latitude,
            'lng'       => $this->longitude,
            'etat'      => $derniere ? ($derniere->conforme ? 'conforme' : 'non_conforme') : 'inconnu',
            'date'      => $derniere?->date_prelevement->format('d/m/Y'),
            'problemes' => array_values($derniere?->parametresHorsNorme() ?? []),
            'retard'    => self::retardAnalyse($this->actif, $derniere?->date_prelevement),
        ];
    }

    // Séries du graphique d'évolution, dans l'ordre chronologique :
    // ['dates' => ['15/03/2026', ...], 'valeurs' => ['ph' => [7.2, ...], ...]]
    public function evolution(): array
    {
        $chronologie = $this->analyses->sortBy([['date_prelevement', 'asc'], ['id', 'asc']])->values();

        $evolution = [
            'dates' => $chronologie->map(fn (AnalyseQualite $a) => $a->date_prelevement->format('d/m/Y'))->all(),
        ];
        foreach (array_keys(AnalyseQualite::SEUILS) as $parametre) {
            $evolution['valeurs'][$parametre] = $chronologie->pluck($parametre)->all();
        }

        return $evolution;
    }

    // Couleur Tabler du taux de conformité
    public static function couleurTaux(?float $taux): string
    {
        return match (true) {
            $taux === null => 'secondary',
            $taux >= 90    => 'green',
            $taux >= 70    => 'yellow',
            default        => 'red',
        };
    }
}
