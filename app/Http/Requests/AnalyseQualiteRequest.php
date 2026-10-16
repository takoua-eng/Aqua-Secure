<?php

namespace App\Http\Requests;

use App\Models\PointPrelevement;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AnalyseQualiteRequest extends FormRequest
{
    // Valeurs mesurées en décimal : la virgule est acceptée ("7,5" => "7.5")
    private const DECIMAUX = ['ph', 'chlore_residuel', 'turbidite', 'nitrates'];

    // Gestionnaires et admins saisissent les analyses
    public function authorize(): bool
    {
        return $this->user()?->peutAccederBackOffice() ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach ([...self::DECIMAUX, 'bacteries_coliformes'] as $champ) {
            if (is_string($this->{$champ})) {
                $this->merge([$champ => str_replace(',', '.', trim($this->{$champ}))]);
            }
        }

        if (is_string($this->laboratoire)) {
            $this->merge(['laboratoire' => trim($this->laboratoire)]);
        }
    }

    public function rules(): array
    {
        // En modification, l'analyse est dans l'URL : /admin/analyses/{analyse}
        $analyse = $this->route('analyse');

        return [
            'point_id' => [
                'required', 'integer', 'exists:points_prelevement,id',
                // Pas de nouvelle analyse sur un point hors service
                // (une analyse existante peut être corrigée même si son point a été mis hors service depuis)
                function (string $attribut, $valeur, Closure $echec) use ($analyse) {
                    $point = PointPrelevement::find($valeur);
                    if ($point && ! $point->actif && (int) $analyse?->point_id !== $point->id) {
                        $echec("Le point {$point->code} est hors service : impossible d'y enregistrer une nouvelle analyse.");
                    }
                },
            ],
            'date_prelevement'     => ['required', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'ph'                   => ['required', 'numeric', 'decimal:0,2', 'between:0,14'],
            'chlore_residuel'      => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999.99'],
            'turbidite'            => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'nitrates'             => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'bacteries_coliformes' => ['required', 'integer', 'min:0', 'max:1000000'],
            'laboratoire'          => ['required', 'string', 'min:2', 'max:150'],
            // "conforme" n'est pas dans les règles : il est toujours calculé par le modèle AnalyseQualite
        ];
    }

    public function messages(): array
    {
        return [
            'point_id.required' => 'Le point de prélèvement est obligatoire.',
            'point_id.integer'  => "Le point de prélèvement choisi n'est pas valide.",
            'point_id.exists'   => "Le point de prélèvement choisi n'existe pas.",

            'date_prelevement.required'        => 'La date de prélèvement est obligatoire.',
            'date_prelevement.date'            => "La date de prélèvement n'est pas valide.",
            'date_prelevement.after_or_equal'  => 'La date de prélèvement ne peut pas être avant 2000.',
            'date_prelevement.before_or_equal' => 'La date de prélèvement ne peut pas être dans le futur.',

            'ph.required'  => 'Le pH est obligatoire.',
            'ph.numeric'   => 'Le pH doit être un nombre (ex : 7,2).',
            'ph.decimal'   => 'Le pH a au plus 2 décimales.',
            'ph.between'   => 'Le pH doit être compris entre 0 et 14.',

            'chlore_residuel.required' => 'Le chlore résiduel est obligatoire.',
            'chlore_residuel.numeric'  => 'Le chlore résiduel doit être un nombre (ex : 0,35).',
            'chlore_residuel.decimal'  => 'Le chlore résiduel a au plus 2 décimales.',
            'chlore_residuel.min'      => 'Le chlore résiduel ne peut pas être négatif.',
            'chlore_residuel.max'      => 'Le chlore résiduel ne peut pas dépasser 999,99 mg/L.',

            'turbidite.required' => 'La turbidité est obligatoire.',
            'turbidite.numeric'  => 'La turbidité doit être un nombre (ex : 1,8).',
            'turbidite.decimal'  => 'La turbidité a au plus 2 décimales.',
            'turbidite.min'      => 'La turbidité ne peut pas être négative.',
            'turbidite.max'      => 'La turbidité ne peut pas dépasser 9 999,99 NTU.',

            'nitrates.required' => 'Le taux de nitrates est obligatoire.',
            'nitrates.numeric'  => 'Le taux de nitrates doit être un nombre (ex : 12,5).',
            'nitrates.decimal'  => 'Le taux de nitrates a au plus 2 décimales.',
            'nitrates.min'      => 'Le taux de nitrates ne peut pas être négatif.',
            'nitrates.max'      => 'Le taux de nitrates ne peut pas dépasser 9 999,99 mg/L.',

            'bacteries_coliformes.required' => 'Le nombre de bactéries coliformes est obligatoire.',
            'bacteries_coliformes.integer'  => 'Le nombre de bactéries coliformes est un nombre entier (UFC/100 mL).',
            'bacteries_coliformes.min'      => 'Le nombre de bactéries coliformes ne peut pas être négatif.',
            'bacteries_coliformes.max'      => 'Le nombre de bactéries coliformes ne peut pas dépasser 1 000 000.',

            'laboratoire.required' => 'Le laboratoire est obligatoire.',
            'laboratoire.min'      => 'Le nom du laboratoire doit contenir au moins 2 caractères.',
            'laboratoire.max'      => 'Le nom du laboratoire ne peut pas dépasser 150 caractères.',
        ];
    }
}
