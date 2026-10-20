<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Commentaire d'un citoyen sur une coupure programmée ; les autres citoyens peuvent l'aimer
class CommentaireCoupure extends Model
{
    protected $table = 'commentaires_coupure';

    public const LONGUEUR_MIN = 3;
    public const LONGUEUR_MAX = 500;

    protected $fillable = ['coupure_programmee_id', 'user_id', 'contenu'];

    public function coupure(): BelongsTo
    {
        return $this->belongsTo(CoupureProgrammee::class, 'coupure_programmee_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Citoyens qui ont aimé ce commentaire (table likes_commentaire)
    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'likes_commentaire', 'commentaire_coupure_id', 'user_id')->withTimestamps();
    }

    // Nom affiché publiquement : prénom + initiale du nom de famille ("Salma Ben Ali" => "Salma B.") pour la vie privée
    public function nomPublic(): string
    {
        $mots = collect(explode(' ', trim($this->auteur->nom ?? 'Citoyen')))->filter()->values();

        return $mots->count() > 1
            ? $mots[0] . ' ' . mb_strtoupper(mb_substr($mots[1], 0, 1)) . '.'
            : (string) $mots->first();
    }
}
