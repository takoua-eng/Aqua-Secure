<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Releve extends Model
{
    use HasFactory;

    protected $table = 'releves';

    // hors_seuil n'est PAS modifiable par formulaire : il est calculé
    protected $fillable = ['capteur_id', 'valeur', 'date_releve', 'remarque'];

    protected function casts(): array
    {
        return [
            'valeur'      => 'float',
            'date_releve' => 'datetime',
            'hors_seuil'  => 'boolean',
        ];
    }

    // Avant chaque enregistrement : la valeur sort-elle des seuils du capteur ?
    protected static function booted(): void
    {
        static::saving(function (Releve $releve) {
            $releve->hors_seuil = $releve->capteur->estHorsSeuil($releve->valeur);
        });
    }

    // RELEVE appartient à CAPTEUR
    public function capteur(): BelongsTo
    {
        return $this->belongsTo(Capteur::class);
    }

    // Trop bas ou trop haut (pour les messages)
    public function sens(): ?string
    {
        if (! $this->hors_seuil) {
            return null;
        }

        return $this->valeur < $this->capteur->seuil_min ? 'trop bas' : 'trop haut';
    }
}