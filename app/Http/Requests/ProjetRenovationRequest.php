<?php

namespace App\Http\Requests;

use App\Models\ProjetRenovation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Binôme 3 — contrôle de saisie du formulaire Projet de rénovation (ajout et modification).
 * Les droits sont vérifiés par ProjetRenovationPolicy dans le contrôleur.
 */
class ProjetRenovationRequest extends FormRequest
{
    public const COUT_MIN = 1000;

    public const COUT_MAX = 100000000;

    public function authorize(): bool
    {
        return true;
    }

    // Avant la validation : titre nettoyé, montant « 1 250 000,50 » accepté, case à cocher en booléen
    protected function prepareForValidation(): void
    {
        $this->merge([
            'titre'       => preg_replace('/\s+/u', ' ', trim((string) $this->titre)),
            'cout_estime' => self::montant($this->cout_estime),
            'publie'      => $this->boolean('publie'),
        ]);
    }

    public function rules(): array
    {
        $projet = $this->route('projet');

        return [
            'reseau_id'   => ['required', 'integer', 'exists:reseaux_eau,id'],
            'titre'       => ['required', 'string', 'min:5', 'max:150', 'regex:/^(?=.*\pL)[\pL0-9 .,;:\'’()°\/&+\-]+$/u',
                              Rule::unique('projets_renovation', 'titre')
                                  ->where('reseau_id', $this->input('reseau_id'))
                                  ->ignore($projet?->id)],
            'statut'      => ['required', Rule::in(array_keys(ProjetRenovation::STATUTS))],
            'cout_estime' => ['nullable', 'numeric', 'decimal:0,2', 'min:' . self::COUT_MIN, 'max:' . self::COUT_MAX],
            'description' => ['nullable', 'string', 'max:2000'],
            'publie'      => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // Le coût déclaré ne peut pas être inférieur à ce qui est déjà financé
                $projet = $this->route('projet');
                if ($projet && $this->filled('cout_estime')) {
                    $finance = $projet->totalFinance();
                    if ((float) $this->cout_estime + 0.005 < $finance) {
                        $validator->errors()->add('cout_estime',
                            'Le coût déclaré ne peut pas être inférieur aux financements déjà enregistrés ('
                            . number_format($finance, 2, ',', ' ') . ' DT).');
                    }
                }

                // Un projet publié doit avoir une description lisible par les citoyens
                if ($this->boolean('publie') && blank($this->description)) {
                    $validator->errors()->add('description', 'Ajoutez une description avant de publier le projet aux citoyens.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'reseau_id.required'  => 'Choisissez le réseau concerné.',
            'reseau_id.exists'    => "Ce réseau n'existe pas.",
            'titre.required'      => 'Le titre est obligatoire.',
            'titre.min'           => 'Le titre doit contenir au moins 5 caractères.',
            'titre.max'           => 'Le titre ne peut pas dépasser 150 caractères.',
            'titre.regex'         => 'Le titre doit contenir des lettres ; seuls les chiffres et la ponctuation courante sont permis en plus.',
            'titre.unique'        => 'Un projet porte déjà ce titre sur ce réseau.',
            'statut.required'     => 'Choisissez le statut.',
            'statut.in'           => "Ce statut n'existe pas.",
            'cout_estime.numeric' => 'Le coût doit être un montant en dinars.',
            'cout_estime.decimal' => 'Le coût peut avoir au plus 2 chiffres après la virgule.',
            'cout_estime.min'     => 'Le coût doit être d\'au moins 1 000 DT.',
            'cout_estime.max'     => 'Le coût ne peut pas dépasser 100 000 000 DT.',
            'description.max'     => 'La description ne peut pas dépasser 2 000 caractères.',
        ];
    }

    // « 1 250 000,50 » => « 1250000.50 » ; vide => null
    public static function montant(mixed $valeur): ?string
    {
        if ($valeur === null || trim((string) $valeur) === '') {
            return null;
        }

        return str_replace(',', '.', preg_replace('/[\s\x{00A0}\x{202F}]/u', '', (string) $valeur));
    }
}
