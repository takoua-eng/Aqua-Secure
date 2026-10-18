<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Binôme 3 — question d'un citoyen sur un projet ; visible publiquement seulement après réponse d'un gestionnaire
class QuestionProjet extends Model
{
    protected $table = 'questions_projets';

    protected $fillable = ['projet_id', 'user_id', 'question', 'reponse', 'repondu_par', 'repondu_le', 'publiee'];

    protected function casts(): array
    {
        return ['repondu_le' => 'datetime', 'publiee' => 'boolean'];
    }

    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRenovation::class, 'projet_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function repondant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repondu_par');
    }

    public function scopePubliees(Builder $query): Builder
    {
        return $query->where('publiee', true)->whereNotNull('reponse');
    }

    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->whereNull('reponse');
    }
}
