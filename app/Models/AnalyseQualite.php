<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyseQualite extends Model
{
    protected $table = 'analyses_qualite';

    // Seuils de potabilité (OMS / norme tunisienne NT 09.14) : null = pas de limite de ce côté
    public const SEUILS = [
        'ph'                   => ['min' => 6.5, 'max' => 8.5],
        'chlore_residuel'      => ['min' => 0.1, 'max' => 1.0],    // mg/L
        'turbidite'            => ['min' => null, 'max' => 5.0],   // NTU
        'nitrates'             => ['min' => null, 'max' => 50.0],  // mg/L
        'bacteries_coliformes' => ['min' => null, 'max' => 0],     // UFC/100 mL : aucune tolérée
    ];

    // Libellé, unité, nombre de décimales affichées, et mots utilisés quand la valeur sort de la norme
    public const PARAMETRES = [
        'ph'                   => ['libelle' => 'pH',                   'unite' => '',           'decimales' => 2, 'trop_bas' => 'trop acide',   'trop_haut' => 'trop basique'],
        'chlore_residuel'      => ['libelle' => 'Chlore résiduel',      'unite' => 'mg/L',       'decimales' => 2, 'trop_bas' => 'insuffisant (désinfection non assurée)', 'trop_haut' => 'trop élevé'],
        'turbidite'            => ['libelle' => 'Turbidité',            'unite' => 'NTU',        'decimales' => 2, 'trop_bas' => 'trop faible',  'trop_haut' => 'trop élevée (eau trouble)'],
        'nitrates'             => ['libelle' => 'Nitrates',             'unite' => 'mg/L',       'decimales' => 2, 'trop_bas' => 'trop faibles', 'trop_haut' => 'trop élevés'],
        'bacteries_coliformes' => ['libelle' => 'Bactéries coliformes', 'unite' => 'UFC/100 mL', 'decimales' => 0, 'trop_bas' => 'trop faibles', 'trop_haut' => 'présentes (contamination bactérienne)'],
    ];

    // Explication simple de chaque paramètre, pour les citoyens (front office)
    public const EXPLICATIONS = [
        'ph'                   => "Mesure l'acidité de l'eau. Une eau trop acide ou trop basique peut abîmer les canalisations et avoir mauvais goût.",
        'chlore_residuel'      => "Désinfectant ajouté pour tuer les microbes. Trop peu : l'eau n'est plus protégée. Trop : goût et odeur de javel.",
        'turbidite'            => "Indique si l'eau est trouble. Une eau trouble peut contenir des particules qui protègent les microbes.",
        'nitrates'             => "Proviennent surtout des engrais agricoles. En excès, ils sont dangereux pour les nourrissons et les femmes enceintes.",
        'bacteries_coliformes' => "Bactéries qui signalent une contamination (eaux usées, infiltration). Il ne doit y en avoir aucune.",
    ];

    protected $fillable = [
        'point_id', 'date_prelevement', 'ph', 'chlore_residuel', 'turbidite', 'nitrates',
        'bacteries_coliformes', 'laboratoire',
    ];

    protected function casts(): array
    {
        return [
            'date_prelevement'     => 'date',
            'ph'                   => 'float',
            'chlore_residuel'      => 'float',
            'turbidite'            => 'float',
            'nitrates'             => 'float',
            'bacteries_coliformes' => 'integer',
            'conforme'             => 'boolean',
        ];
    }

    // La conformité n'est jamais saisie : elle est recalculée à chaque enregistrement
    protected static function booted(): void
    {
        static::saving(function (AnalyseQualite $analyse) {
            $analyse->conforme = empty($analyse->parametresHorsNorme());
        });
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(PointPrelevement::class, 'point_id');
    }

    // Paramètres hors norme : ['ph' => 'pH trop acide : 6,20 (norme : entre 6,5 et 8,5)', ...]
    public function parametresHorsNorme(): array
    {
        $horsNorme = [];

        foreach (array_keys(self::SEUILS) as $parametre) {
            if ($message = self::messageHorsNorme($parametre, $this->{$parametre})) {
                $horsNorme[$parametre] = $message;
            }
        }

        return $horsNorme;
    }

    // Vrai si la valeur de ce paramètre est hors norme (pour l'afficher en rouge)
    public function estHorsNorme(string $parametre): bool
    {
        return self::messageHorsNorme($parametre, $this->{$parametre}) !== null;
    }

    // Message expliquant pourquoi une valeur est hors norme, ou null si elle est dans la norme
    public static function messageHorsNorme(string $parametre, $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        ['min' => $min, 'max' => $max] = self::SEUILS[$parametre];
        $infos = self::PARAMETRES[$parametre];
        $valeur = (float) $valeur;

        if ($min !== null && $valeur < $min) {
            $sens = $infos['trop_bas'];
        } elseif ($max !== null && $valeur > $max) {
            $sens = $infos['trop_haut'];
        } else {
            return null;
        }

        return "{$infos['libelle']} {$sens} : " . self::formater($parametre, $valeur) . ' (norme : ' . self::norme($parametre) . ')';
    }

    // Norme lisible : "entre 6,5 et 8,5", "≤ 5 NTU", "0 UFC/100 mL"
    public static function norme(string $parametre): string
    {
        ['min' => $min, 'max' => $max] = self::SEUILS[$parametre];
        $unite = self::PARAMETRES[$parametre]['unite'];
        $suffixe = $unite ? " {$unite}" : '';

        if ($min !== null && $max !== null) {
            return 'entre ' . self::nombre($min) . ' et ' . self::nombre($max) . $suffixe;
        }

        return ($max == 0 ? '' : '≤ ') . self::nombre($max) . $suffixe;
    }

    // Valeur formatée à la française avec son unité : "0,35 mg/L"
    public static function formater(string $parametre, $valeur): string
    {
        $infos = self::PARAMETRES[$parametre];
        $texte = number_format((float) $valeur, $infos['decimales'], ',', ' ');

        return $infos['unite'] ? "{$texte} {$infos['unite']}" : $texte;
    }

    // Nombre sans zéros inutiles : 6.5 => "6,5", 50.0 => "50"
    private static function nombre($valeur): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format((float) $valeur, 2, '.', ''), '0'), '.'));
    }

    public function libelleConformite(): string
    {
        return $this->conforme ? 'Conforme' : 'Non conforme';
    }

    public function couleurConformite(): string
    {
        return $this->conforme ? 'green' : 'red';
    }
}
