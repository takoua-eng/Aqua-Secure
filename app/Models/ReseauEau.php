<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReseauEau extends Model
{
    protected $table = 'reseaux_eau';

    // Valeur en base => libellé affiché
    public const TYPES = [
        'canalisation' => 'Canalisation',
        'reservoir'    => 'Réservoir',
        'station'      => 'Station de traitement',
    ];

    public const ETATS = [
        'bon'          => 'Bon',
        'degrade'      => 'Dégradé',
        'hors_service' => 'Hors service',
    ];

    public const MATERIAUX = [
        'fonte'          => 'Fonte',
        'acier'          => 'Acier',
        'pvc'            => 'PVC',
        'pehd'           => 'PEHD (polyéthylène)',
        'beton'          => 'Béton',
        'amiante_ciment' => 'Amiante-ciment',
    ];

    protected $fillable = [
        'zone_id', 'code', 'type', 'materiau', 'date_mise_en_service', 'etat', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'date_mise_en_service' => 'date',
            'latitude'  => 'float',
            'longitude' => 'float',
        ];
    }

    // Chaque création ou changement d'état est enregistré dans l'historique
    protected static function booted(): void
    {
        static::created(function (ReseauEau $reseau) {
            $reseau->historiques()->create([
                'ancien_etat' => null,
                'nouvel_etat' => $reseau->etat,
                'user_id'     => auth()->id(),
            ]);
        });

        static::updated(function (ReseauEau $reseau) {
            if ($reseau->wasChanged('etat')) {
                $reseau->historiques()->create([
                    'ancien_etat' => $reseau->getOriginal('etat'),
                    'nouvel_etat' => $reseau->etat,
                    'user_id'     => auth()->id(),
                ]);
            }
        });
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function historiques(): HasMany
    {
        return $this->hasMany(HistoriqueEtat::class)->latest()->latest('id');
    }

    // RESEAU_EAU 1 — N POINT_PRELEVEMENT (module Qualité de l'eau)
    public function pointsPrelevement(): HasMany
    {
        return $this->hasMany(PointPrelevement::class, 'reseau_id');
    }

    public function libelleType(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function libelleEtat(): string
    {
        return self::ETATS[$this->etat] ?? $this->etat;
    }

    public static function libelleEtatDe(?string $etat): string
    {
        return self::ETATS[$etat] ?? (string) $etat;
    }

    public function libelleMateriau(): string
    {
        return self::MATERIAUX[$this->materiau] ?? $this->materiau;
    }

    // Âge en années depuis la mise en service
    public function age(): int
    {
        return (int) $this->date_mise_en_service?->diffInYears(now());
    }

    // Couleur Tabler associée à l'état
    public function couleurEtat(): string
    {
        return self::couleurDe($this->etat);
    }

    public static function couleurDe(?string $etat): string
    {
        return ['bon' => 'green', 'degrade' => 'yellow', 'hors_service' => 'red'][$etat] ?? 'secondary';
    }

    // Icône Tabler associée au type
    public function iconeType(): string
    {
        return ['canalisation' => 'line', 'reservoir' => 'cylinder', 'station' => 'building-factory-2'][$this->type] ?? 'droplet';
    }

    // Date du dernier changement d'état (pour "Dégradé depuis …")
    public function etatDepuis(): ?\Illuminate\Support\Carbon
    {
        return $this->relationLoaded('historiques')
            ? $this->historiques->first()?->created_at
            : $this->historiques()->first()?->created_at;
    }
}
