<?php

namespace App\Models;

use App\Exceptions\RegleIncidentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Intervention extends Model
{
    use HasFactory;

    // Valeur en base => libellé affiché
    public const STATUTS = [
        'planifiee' => 'Planifiée',
        'en_cours'  => 'En cours',
        'terminee'  => 'Terminée',
    ];

    // Une intervention active rend son incident "en traitement"
    public const STATUTS_ACTIFS = ['planifiee', 'en_cours'];

    // Une intervention commencée verrouille le réseau de son incident (règle I3)
    public const STATUTS_COMMENCES = ['en_cours', 'terminee'];

    // Transitions autorisées : statut actuel => statuts suivants possibles.
    // Pas de retour en arrière ; "terminee" est définitif.
    public const TRANSITIONS = [
        'planifiee' => ['en_cours', 'terminee'],
        'en_cours'  => ['terminee'],
        'terminee'  => [],
    ];

    protected $fillable = ['incident_id', 'statut', 'date_intervention'];

    protected function casts(): array
    {
        return [
            'date_intervention' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Les règles sont vérifiées ici pour qu'aucun chemin (formulaire, API, tinker, seeder) ne les contourne
        static::updating(function (Intervention $intervention) {
            // Règle T1
            if ($intervention->isDirty('incident_id')) {
                throw RegleIncidentException::incidentVerrouille();
            }

            // Règle T7
            if ($intervention->getOriginal('statut') === 'terminee') {
                throw RegleIncidentException::interventionTerminee();
            }

            // Règle T5
            if ($intervention->isDirty('statut')
                && ! self::transitionAutorisee($intervention->getOriginal('statut'), $intervention->statut)) {
                throw RegleIncidentException::transitionInterdite($intervention->getOriginal('statut'), $intervention->statut);
            }
        });

        // Règle T8 : seule une intervention planifiée (pas encore commencée) peut être supprimée
        static::deleting(function (Intervention $intervention) {
            if (! $intervention->peutEtreSupprimee()) {
                throw RegleIncidentException::interventionNonSupprimable($intervention);
            }
        });

        // Chaque création ou changement de statut est enregistré dans l'historique
        static::created(function (Intervention $intervention) {
            $intervention->historiques()->create([
                'ancien_statut'  => null,
                'nouveau_statut' => $intervention->statut,
                'user_id'        => auth()->id(),
            ]);
        });

        static::updated(function (Intervention $intervention) {
            if ($intervention->wasChanged('statut')) {
                $intervention->historiques()->create([
                    'ancien_statut'  => $intervention->getOriginal('statut'),
                    'nouveau_statut' => $intervention->statut,
                    'user_id'        => auth()->id(),
                ]);
            }
        });
    }

    // ---------- Relations ----------

    // INTERVENTION appartient à INCIDENT (clé étrangère incident_id)
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function historiques(): HasMany
    {
        return $this->hasMany(HistoriqueIntervention::class)->latest()->latest('id');
    }

    // ---------- Transitions de statut ----------

    // $de = null : création (tout statut connu est accepté)
    public static function transitionAutorisee(?string $de, string $vers): bool
    {
        if ($de === null) {
            return array_key_exists($vers, self::STATUTS);
        }

        return in_array($vers, self::TRANSITIONS[$de] ?? [], true);
    }

    public function peutPasserA(string $statut): bool
    {
        return self::transitionAutorisee($this->statut, $statut);
    }

    // Statuts proposés dans le bouton "Changer le statut"
    public function statutsSuivants(): array
    {
        return self::TRANSITIONS[$this->statut] ?? [];
    }

    // Changement de statut contrôlé (règles T5 et T6), enregistré dans l'historique.
    // Transaction : le statut et sa ligne d'historique sont écrits ensemble, ou pas du tout.
    public function passerA(string $statut): void
    {
        DB::transaction(function () use ($statut) {
            // Statut relu en base et verrouillé : deux changements simultanés ne peuvent pas contourner les transitions
            $statutEnBase = static::query()->whereKey($this->getKey())->lockForUpdate()->value('statut');
            if ($statutEnBase !== null && $statutEnBase !== $this->statut) {
                $this->refresh();
            }

            if (! $this->peutPasserA($statut)) {
                throw RegleIncidentException::transitionInterdite($this->statut, $statut);
            }

            $this->statut = $statut;

            // Règle T6 : en cours ou terminée, la date est celle de l'exécution réelle, jamais dans le futur
            if ($this->date_intervention->gt(today())) {
                $this->date_intervention = today();
            }

            $this->save();
        });
    }

    // ---------- Règles métier ----------

    // Règle T7
    public function estModifiable(): bool
    {
        return $this->statut !== 'terminee';
    }

    // Règle T8
    public function peutEtreSupprimee(): bool
    {
        return $this->statut === 'planifiee';
    }

    // Planifiée dont la date est passée sans que l'intervention ait commencé
    public function estEnRetard(): bool
    {
        return $this->statut === 'planifiee' && $this->date_intervention->lt(today());
    }

    // ---------- Filtres (scopes) ----------

    public function scopeActives(Builder $query): Builder
    {
        return $query->whereIn('statut', self::STATUTS_ACTIFS);
    }

    public function scopeEnRetard(Builder $query): Builder
    {
        return $query->where('statut', 'planifiee')
            ->whereDate('date_intervention', '<', today()->toDateString());
    }

    // ---------- Aides d'affichage ----------

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public static function libelleStatutDe(?string $statut): string
    {
        return self::STATUTS[$statut] ?? (string) $statut;
    }

    // Couleur Tabler associée au statut
    public function couleurStatut(): string
    {
        return self::couleurStatutDe($this->statut);
    }

    public static function couleurStatutDe(?string $statut): string
    {
        return ['planifiee' => 'blue', 'en_cours' => 'orange', 'terminee' => 'green'][$statut] ?? 'secondary';
    }

    // Icône Tabler associée au statut
    public function iconeStatut(): string
    {
        return ['planifiee' => 'calendar-event', 'en_cours' => 'tool', 'terminee' => 'circle-check'][$this->statut] ?? 'tool';
    }
}
