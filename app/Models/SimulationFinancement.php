<?php

namespace App\Models;

use App\Services\Renovation\MoteurWhatIf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Binôme 3 — scénario enregistré du What If? Engine (hypothèse, jamais une donnée réelle tant qu'il n'est pas appliqué)
class SimulationFinancement extends Model
{
    protected $table = 'simulations_financement';

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'soumise'   => 'En attente de validation',
        'approuvee' => 'Approuvée',
        'rejetee'   => 'Rejetée',
        'appliquee' => 'Appliquée',
    ];

    protected $fillable = [
        'user_id', 'nom', 'budget', 'strategie', 'parametres', 'resultats', 'empreinte', 'analyse_ia',
        'statut', 'source_financement', 'decide_par', 'soumise_le', 'decide_le', 'commentaire_decision', 'appliquee_le',
    ];

    protected function casts(): array
    {
        return [
            'budget'       => 'decimal:2',
            'parametres'   => 'array',
            'resultats'    => 'array',
            'analyse_ia'   => 'array',
            'soumise_le'   => 'datetime',
            'decide_le'    => 'datetime',
            'appliquee_le' => 'datetime',
        ];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par');
    }

    // Financements réels créés lors de l'application du scénario
    public function financements(): HasMany
    {
        return $this->hasMany(Financement::class, 'simulation_id');
    }

    // Résultat détaillé de la stratégie retenue
    public function scenarioRetenu(): ?array
    {
        return $this->resultats['scenarios'][$this->strategie] ?? null;
    }

    public function libelleStrategie(): string
    {
        return MoteurWhatIf::STRATEGIES[$this->strategie]['libelle'] ?? $this->strategie;
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function couleurStatut(): string
    {
        return [
            'brouillon' => 'secondary', 'soumise' => 'orange', 'approuvee' => 'blue',
            'rejetee' => 'red', 'appliquee' => 'green',
        ][$this->statut] ?? 'secondary';
    }
}
