<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
 * Coupure d'eau programmée pour travaux sur un élément du réseau.
 * Son statut se déduit des dates : à venir, en cours, terminée (ou annulée).
 */
class CoupureProgrammee extends Model
{
    protected $table = 'coupures_programmees';

    public const STATUTS = [
        'a_venir'  => ['À venir', 'blue'],
        'en_cours' => ['En cours', 'orange'],
        'terminee' => ['Terminée', 'secondary'],
        'annulee'  => ['Annulée', 'red'],
    ];

    public const DUREE_MAX_HEURES = 168; // 7 jours

    protected $fillable = ['reseau_eau_id', 'motif', 'description', 'debut', 'fin', 'user_id'];

    protected function casts(): array
    {
        return ['debut' => 'datetime', 'fin' => 'datetime', 'annulee_le' => 'datetime'];
    }

    public function reseau(): BelongsTo
    {
        return $this->belongsTo(ReseauEau::class, 'reseau_eau_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Commentaires des citoyens sur cette coupure
    public function commentaires(): HasMany
    {
        return $this->hasMany(CommentaireCoupure::class, 'coupure_programmee_id');
    }

    // On peut commenter une coupure maintenue, en cours, à venir ou terminée depuis moins de 7 jours
    public function accepteCommentaires(): bool
    {
        return $this->annulee_le === null && $this->fin->greaterThan(now()->subDays(7));
    }

    // Coupures affichées sur le site : maintenues, pas encore terminées ou terminées depuis moins de 7 jours
    public function scopeVisibles(Builder $query): Builder
    {
        return $query->whereNull('annulee_le')->where('fin', '>', now()->subDays(7));
    }

    public function statut(): string
    {
        return match (true) {
            $this->annulee_le !== null => 'annulee',
            $this->debut->isFuture()   => 'a_venir',
            $this->fin->isFuture()     => 'en_cours',
            default                    => 'terminee',
        };
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut()][0];
    }

    public function couleurStatut(): string
    {
        return self::STATUTS[$this->statut()][1];
    }

    public function dureeHeures(): float
    {
        return round($this->debut->diffInMinutes($this->fin) / 60, 1);
    }

    // On ne modifie qu'une coupure qui n'a pas encore commencé
    public function estModifiable(): bool
    {
        return $this->statut() === 'a_venir';
    }

    /*
     * Habitants touchés (estimation) : un réservoir ou une station alimente toute la zone ;
     * une canalisation ne dessert qu'une partie de la zone, répartie entre ses canalisations.
     */
    public function habitantsTouches(): int
    {
        $reseau = $this->reseau;
        $population = (int) $reseau->zone->population;

        if ($reseau->type !== 'canalisation') {
            return $population;
        }

        $canalisations = max(1, $reseau->zone->reseaux()->where('type', 'canalisation')->count());

        return (int) round($population / $canalisations);
    }

    // Coupures maintenues (pas annulées) qui ne sont pas encore terminées
    public function scopeActives(Builder $query): Builder
    {
        return $query->whereNull('annulee_le')->where('fin', '>', now());
    }

    public function scopeDansZone(Builder $query, int $zoneId): Builder
    {
        return $query->whereHas('reseau', fn ($q) => $q->where('zone_id', $zoneId));
    }

    // Autre coupure maintenue sur le même réseau dont la période chevauche [debut, fin]
    public static function chevauchement(int $reseauId, $debut, $fin, ?int $saufId = null): ?self
    {
        return static::where('reseau_eau_id', $reseauId)
            ->whereNull('annulee_le')
            ->when($saufId, fn ($q) => $q->where('id', '!=', $saufId))
            ->where('debut', '<', $fin)
            ->where('fin', '>', $debut)
            ->first();
    }
}
