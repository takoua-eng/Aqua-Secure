<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Binôme 3 — « Ce projet me concerne » : un citoyen soutient un projet publié (un seul soutien par projet)
class SoutienProjet extends Model
{
    protected $table = 'soutiens_projets';

    protected $fillable = ['projet_id', 'user_id'];

    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRenovation::class, 'projet_id');
    }
}
