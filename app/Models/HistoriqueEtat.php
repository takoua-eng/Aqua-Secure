<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueEtat extends Model
{
    protected $table = 'historique_etats';

    protected $fillable = ['reseau_eau_id', 'ancien_etat', 'nouvel_etat', 'user_id'];

    public function reseau(): BelongsTo
    {
        return $this->belongsTo(ReseauEau::class, 'reseau_eau_id');
    }

    // Personne qui a fait le changement (null si fait par le système / le seeder)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
