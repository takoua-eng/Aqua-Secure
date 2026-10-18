<?php

namespace App\Models;

use App\Services\Renovation\ModeleCout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Binôme 3 — PROJET_RENOVATION : appartient à un RESEAU_EAU, possède plusieurs FINANCEMENT
class ProjetRenovation extends Model
{
    protected $table = 'projets_renovation';

    // Valeur en base => libellé affiché
    public const STATUTS = [
        'planifie' => 'Planifié',
        'en_cours' => 'En cours',
        'termine'  => 'Terminé',
    ];

    protected $fillable = ['reseau_id', 'titre', 'statut', 'cout_estime', 'description', 'publie'];

    protected function casts(): array
    {
        return [
            'cout_estime' => 'decimal:2',
            'publie'      => 'boolean',
        ];
    }

    public function reseau(): BelongsTo
    {
        return $this->belongsTo(ReseauEau::class, 'reseau_id');
    }

    public function financements(): HasMany
    {
        return $this->hasMany(Financement::class, 'projet_id');
    }

    // Participation citoyenne
    public function soutiens(): HasMany
    {
        return $this->hasMany(SoutienProjet::class, 'projet_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuestionProjet::class, 'projet_id');
    }

    // Projets visibles par les citoyens
    public function scopePublics(Builder $query): Builder
    {
        return $query->where('publie', true);
    }

    // Total réellement financé (utilise financements_sum_montant si chargé avec withSum)
    public function totalFinance(): float
    {
        if (array_key_exists('financements_sum_montant', $this->attributes)) {
            return round((float) $this->attributes['financements_sum_montant'], 2);
        }

        return round((float) ($this->relationLoaded('financements')
            ? $this->financements->sum('montant')
            : $this->financements()->sum('montant')), 2);
    }

    /** Coût du projet : déclaré par le gestionnaire, sinon estimation signalée comme telle. */
    public function cout(): array
    {
        return ModeleCout::pourProjet($this);
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function couleurStatut(): string
    {
        return ['planifie' => 'blue', 'en_cours' => 'orange', 'termine' => 'green'][$this->statut] ?? 'secondary';
    }

    public function iconeStatut(): string
    {
        return ['planifie' => 'calendar-event', 'en_cours' => 'progress', 'termine' => 'circle-check'][$this->statut] ?? 'point';
    }
}
