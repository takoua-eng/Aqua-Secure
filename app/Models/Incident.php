<?php

namespace App\Models;

use App\Exceptions\RegleIncidentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Incident extends Model
{
    use HasFactory;

    // Valeur en base => libellé affiché
    public const TYPES = [
        'fuite'         => 'Fuite',
        'contamination' => 'Contamination',
        'coupure'       => 'Coupure',
    ];

    public const GRAVITES = [
        'faible'   => 'Faible',
        'moyenne'  => 'Moyenne',
        'critique' => 'Critique',
    ];

    // Statut calculé à partir des interventions : il n'est pas stocké en base
    public const STATUTS = [
        'non_traite'    => 'Non traité',
        'en_traitement' => 'En traitement',
        'resolu'        => 'Résolu',
    ];

    // Règle I7 : un incident critique sans intervention depuis ce nombre de jours est en alerte
    public const JOURS_ALERTE_CRITIQUE = 1;

    // Règle I6 : un incident du même type sur le même réseau à moins de N jours est un doublon possible
    public const JOURS_DOUBLON = 7;

    // Site citoyen : un incident résolu reste affiché ce nombre de jours après sa dernière intervention
    public const JOURS_RESOLUS_PUBLICS = 30;

    protected $fillable = ['reseau_id', 'type', 'gravite', 'date_signalement'];

    protected function casts(): array
    {
        return [
            'date_signalement' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Règle I3 : le réseau ne change plus dès qu'une intervention a commencé
        static::updating(function (Incident $incident) {
            if ($incident->isDirty('reseau_id') && ! $incident->peutChangerDeReseau()) {
                throw RegleIncidentException::reseauVerrouille();
            }
        });

        // Règle I5 : un incident qui a des interventions ne peut pas être supprimé
        // (la clé étrangère restrictOnDelete le refuse aussi en base)
        static::deleting(function (Incident $incident) {
            if ($incident->interventions()->exists()) {
                throw RegleIncidentException::incidentNonSupprimable($incident);
            }
        });
    }

    // ---------- Relations ----------

    // INCIDENT appartient à RESEAU_EAU (clé étrangère reseau_id)
    public function reseau(): BelongsTo
    {
        return $this->belongsTo(ReseauEau::class, 'reseau_id');
    }

    // INCIDENT 1 — N INTERVENTION
    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    // ---------- Statut calculé ----------

    // Statut d'un incident d'après les statuts de ses interventions :
    // aucune => non traité ; au moins une planifiée ou en cours => en traitement ; toutes terminées => résolu
    public static function statutSelon(iterable $statutsInterventions): string
    {
        $statuts = collect($statutsInterventions);

        if ($statuts->isEmpty()) {
            return 'non_traite';
        }

        return $statuts->intersect(Intervention::STATUTS_ACTIFS)->isNotEmpty() ? 'en_traitement' : 'resolu';
    }

    public function statutActuel(): string
    {
        $statuts = $this->relationLoaded('interventions')
            ? $this->interventions->pluck('statut')
            : $this->interventions()->pluck('statut');

        return self::statutSelon($statuts);
    }

    public function estResolu(): bool
    {
        return $this->statutActuel() === 'resolu';
    }

    // ---------- Règles métier ----------

    // Règle I3 : tant qu'aucune intervention n'a commencé (en cours ou terminée), le réseau peut être corrigé
    public function peutChangerDeReseau(): bool
    {
        return ! $this->interventions()->whereIn('statut', Intervention::STATUTS_COMMENCES)->exists();
    }

    // Règle I5
    public function peutEtreSupprime(): bool
    {
        return ! $this->interventions()->exists();
    }

    // Règle I7 : critique, sans aucune intervention, signalé depuis au moins JOURS_ALERTE_CRITIQUE jour(s)
    public function estEnAlerteCritique(): bool
    {
        return $this->gravite === 'critique'
            && $this->statutActuel() === 'non_traite'
            && $this->date_signalement->lte(today()->subDays(self::JOURS_ALERTE_CRITIQUE));
    }

    public function joursDepuisSignalement(): int
    {
        return max(0, (int) $this->date_signalement->diffInDays(today()));
    }

    // Règle I6 : incidents non résolus du même type, sur le même réseau, à ± JOURS_DOUBLON jours de la date donnée
    public static function doublonsPotentiels(int $reseauId, string $type, $date, ?int $ignorerId = null): Builder
    {
        $date = Carbon::parse($date);

        return self::query()
            ->where('reseau_id', $reseauId)
            ->where('type', $type)
            ->whereBetween('date_signalement', [
                $date->copy()->subDays(self::JOURS_DOUBLON)->toDateString(),
                $date->copy()->addDays(self::JOURS_DOUBLON)->toDateString(),
            ])
            ->nonResolus()
            ->when($ignorerId, fn ($query) => $query->whereKeyNot($ignorerId));
    }

    // ---------- Filtres (scopes) ----------

    public function scopeNonTraites(Builder $query): Builder
    {
        return $query->whereDoesntHave('interventions');
    }

    public function scopeEnTraitement(Builder $query): Builder
    {
        return $query->whereHas('interventions', fn ($q) => $q->whereIn('statut', Intervention::STATUTS_ACTIFS));
    }

    public function scopeResolus(Builder $query): Builder
    {
        return $query->whereHas('interventions')
            ->whereDoesntHave('interventions', fn ($q) => $q->whereIn('statut', Intervention::STATUTS_ACTIFS));
    }

    // Non traités ou en traitement
    public function scopeNonResolus(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereDoesntHave('interventions')
            ->orWhereHas('interventions', fn ($i) => $i->whereIn('statut', Intervention::STATUTS_ACTIFS)));
    }

    // Filtre "statut" des listes : non_traite, en_traitement ou resolu (toute autre valeur est ignorée)
    public function scopeAvecStatut(Builder $query, ?string $statut): Builder
    {
        return match ($statut) {
            'non_traite'    => $query->nonTraites(),
            'en_traitement' => $query->enTraitement(),
            'resolu'        => $query->resolus(),
            default         => $query,
        };
    }

    // Site citoyen : incidents non résolus, et résolus récemment (dernière intervention depuis moins de JOURS_RESOLUS_PUBLICS jours)
    public function scopeVisiblesPublic(Builder $query): Builder
    {
        $depuis = today()->subDays(self::JOURS_RESOLUS_PUBLICS)->toDateString();

        return $query->where(fn ($q) => $q
            ->nonResolus()
            ->orWhere(fn ($r) => $r->resolus()->whereHas('interventions', fn ($i) => $i->whereDate('date_intervention', '>=', $depuis))));
    }

    // Règle I7
    public function scopeCritiquesSansIntervention(Builder $query): Builder
    {
        return $query->where('gravite', 'critique')
            ->whereDoesntHave('interventions')
            ->whereDate('date_signalement', '<=', today()->subDays(self::JOURS_ALERTE_CRITIQUE)->toDateString());
    }

    // Tri par niveau de gravité (critique > moyenne > faible), "desc" par défaut
    public function scopeOrdreGravite(Builder $query, string $sens = 'desc'): Builder
    {
        $sens = $sens === 'asc' ? 'asc' : 'desc';
        $colonne = $query->qualifyColumn('gravite');

        return $query->orderByRaw(
            "CASE {$colonne} WHEN 'critique' THEN 3 WHEN 'moyenne' THEN 2 WHEN 'faible' THEN 1 ELSE 0 END {$sens}"
        );
    }

    // ---------- Aides d'affichage ----------

    public function libelleType(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function libelleGravite(): string
    {
        return self::GRAVITES[$this->gravite] ?? $this->gravite;
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statutActuel()];
    }

    // Couleur Tabler associée à la gravité
    public function couleurGravite(): string
    {
        return self::couleurGraviteDe($this->gravite);
    }

    public static function couleurGraviteDe(?string $gravite): string
    {
        return ['faible' => 'yellow', 'moyenne' => 'orange', 'critique' => 'red'][$gravite] ?? 'secondary';
    }

    // Couleur Tabler associée au statut calculé
    public function couleurStatut(): string
    {
        return self::couleurStatutDe($this->statutActuel());
    }

    public static function couleurStatutDe(?string $statut): string
    {
        return ['non_traite' => 'red', 'en_traitement' => 'azure', 'resolu' => 'green'][$statut] ?? 'secondary';
    }

    // Icône Tabler associée au type
    public function iconeType(): string
    {
        return ['fuite' => 'droplet', 'contamination' => 'virus', 'coupure' => 'droplet-off'][$this->type] ?? 'alert-triangle';
    }
}
