<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueIntervention extends Model
{
    protected $table = 'historique_interventions';

    protected $fillable = ['intervention_id', 'ancien_statut', 'nouveau_statut', 'user_id'];

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    // Personne qui a fait le changement (null si fait par le système / le seeder)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
