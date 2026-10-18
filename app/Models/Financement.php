<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Binôme 3 — FINANCEMENT : appartient à un PROJET_RENOVATION
class Financement extends Model
{
    // Valeurs du modèle officiel => libellé affiché
    public const SOURCES = [
        'Etat'         => 'État',
        'municipalite' => 'Municipalité',
        'bailleur'     => 'Bailleur de fonds',
        'don'          => 'Don',
    ];

    // Montant maximal d'une ligne : decimal(10,2)
    public const MONTANT_MAX = 99999999.99;

    protected $fillable = ['projet_id', 'source', 'montant', 'simulation_id'];

    protected function casts(): array
    {
        return ['montant' => 'decimal:2'];
    }

    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRenovation::class, 'projet_id');
    }

    // Scénario What If? appliqué qui a créé ce financement (null = saisie manuelle)
    public function simulation(): BelongsTo
    {
        return $this->belongsTo(SimulationFinancement::class, 'simulation_id');
    }

    public function libelleSource(): string
    {
        return self::libelleSourceDe($this->source);
    }

    public static function libelleSourceDe(?string $source): string
    {
        return self::SOURCES[$source] ?? (string) $source;
    }

    public static function couleurSourceDe(?string $source): string
    {
        return ['Etat' => 'blue', 'municipalite' => 'teal', 'bailleur' => 'purple', 'don' => 'pink'][$source] ?? 'secondary';
    }

    public static function iconeSourceDe(?string $source): string
    {
        return ['Etat' => 'building-bank', 'municipalite' => 'building-community', 'bailleur' => 'world', 'don' => 'heart-handshake'][$source] ?? 'coin';
    }
}
