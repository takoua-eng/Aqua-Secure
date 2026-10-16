<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Capteur extends Model
{
    use HasFactory;

    // Valeur en base => libellé affiché
    public const TYPES_MESURE = [
        'pression'    => 'Pression',
        'debit'       => 'Débit',
        'niveau'      => 'Niveau',
        'temperature' => 'Température',
    ];

    // L'unité dépend du type de mesure
    public const UNITES = [
        'pression'    => 'bar',
        'debit'       => 'm3/h',
        'niveau'      => 'm',
        'temperature' => '°C',
    ];

        // Plage physique de mesure de chaque type de capteur (min, max) : les seuils et les relevés
    // doivent rester dans cette plage. Seule la température peut être négative.
    public const LIMITES = [
        'pression'    => [0, 25],     // bar
        'debit'       => [0, 5000],   // m3/h
        'niveau'      => [0, 50],     // m
        'temperature' => [-10, 60],   // °C
    ];

    // Format du code : CAP-<2 ou 3 lettres>-<3 ou 4 chiffres>, ex : CAP-TN-001
    public const FORMAT_CODE = 'CAP-[A-Z]{2,3}-[0-9]{3,4}';

    public const STATUTS = [
        'actif'        => 'Actif',
        'en_panne'     => 'En panne',
        'hors_service' => 'Hors service',
    ];

    // Un capteur actif sans relevé depuis ce nombre d'heures est signalé "muet"
    public const HEURES_SILENCE = 24;

    protected $fillable = [
        'reseau_id', 'code', 'modele', 'type_mesure', 'unite',
        'seuil_min', 'seuil_max', 'date_installation', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'seuil_min'         => 'float',
            'seuil_max'         => 'float',
            'date_installation' => 'date',
        ];
    }

    // Si les seuils changent, on recalcule "hors_seuil" de tous les relevés du capteur
    protected static function booted(): void
    {
        static::updated(function (Capteur $capteur) {
            if ($capteur->wasChanged(['seuil_min', 'seuil_max'])) {
                $min = (float) $capteur->seuil_min;
                $max = (float) $capteur->seuil_max;

                $capteur->releves()->update([
                    'hors_seuil' => DB::raw("CASE WHEN valeur < {$min} OR valeur > {$max} THEN 1 ELSE 0 END"),
                ]);
            }
        });
    }

    // ---------- Relations ----------

    // CAPTEUR appartient à RESEAU_EAU (clé étrangère reseau_id)
    public function reseau(): BelongsTo
    {
        return $this->belongsTo(ReseauEau::class, 'reseau_id');
    }

    // CAPTEUR possède plusieurs RELEVE
    public function releves(): HasMany
    {
        return $this->hasMany(Releve::class);
    }

    // Le relevé le plus récent (pour la liste des capteurs)
    public function dernierReleve(): HasOne
    {
        return $this->hasOne(Releve::class)->latestOfMany('date_releve');
    }

    // ---------- Aides d'affichage ----------

    public function libelleTypeMesure(): string
    {
        return self::TYPES_MESURE[$this->type_mesure] ?? $this->type_mesure;
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    // Couleur Tabler associée au statut
    public function couleurStatut(): string
    {
        return ['actif' => 'green', 'en_panne' => 'yellow', 'hors_service' => 'red'][$this->statut] ?? 'secondary';
    }

    // Icône Tabler associée au type de mesure
    public function iconeType(): string
    {
        return [
            'pression'    => 'gauge',
            'debit'       => 'wave-sine',
            'niveau'      => 'ruler-measure',
            'temperature' => 'temperature',
        ][$this->type_mesure] ?? 'wifi';
    }

    // Vrai si la valeur sort de l'intervalle [seuil_min, seuil_max]
    public function estHorsSeuil(float $valeur): bool
    {
        return $valeur < $this->seuil_min || $valeur > $this->seuil_max;
    }

        // Message d'erreur si la valeur est physiquement impossible pour ce capteur, sinon null
    public function erreurValeur(float $valeur): ?string
    {
        [$limMin, $limMax] = self::LIMITES[$this->type_mesure];
        $mesure = mb_strtolower($this->libelleTypeMesure());

        if ($valeur < 0 && $limMin >= 0) {
            return "Une mesure de {$mesure} ne peut pas être négative.";
        }
        if ($valeur < $limMin || $valeur > $limMax) {
            return "Valeur impossible : un capteur de {$mesure} mesure entre {$limMin} et {$limMax} {$this->unite}.";
        }

        return null;
    }

    

    // Capteur actif qui n'envoie plus de mesures (panne de transmission probable)
    public function estMuet(): bool
    {
        if ($this->statut !== 'actif') {
            return false;
        }

        $dernier = $this->dernierReleve?->date_releve;

        return ! $dernier || $dernier->lt(now()->subHours(self::HEURES_SILENCE));
    }

    // "2 – 6 bar"
    public function plageSeuils(): string
    {
        return self::nombre($this->seuil_min) . ' – ' . self::nombre($this->seuil_max) . ' ' . $this->unite;
    }

    // "4,25 bar"
    public function avecUnite(?float $valeur): string
    {
        return $valeur === null ? '—' : self::nombre($valeur) . ' ' . $this->unite;
    }

    // Libellé pour les listes déroulantes : "CAP-TN-001 — Pression (bar)"
    public function libelle(): string
    {
        return "{$this->code} — {$this->libelleTypeMesure()} ({$this->unite})";
    }

    // 4.50 => "4,5" ; 10.00 => "10"
    public static function nombre(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 2, ',', ' '), '0'), ',');
    }
    
}