<?php

namespace App\Http\Requests;

use App\Models\PointPrelevement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PointPrelevementRequest extends FormRequest
{
    // Gestionnaires et admins gèrent les points de prélèvement
    public function authorize(): bool
    {
        return $this->user()?->peutAccederBackOffice() ?? false;
    }

    // Code en majuscules ("pp-tn-012" => "PP-TN-012") et virgule décimale acceptée ("36,8" => "36.8")
    protected function prepareForValidation(): void
    {
        if (is_string($this->code)) {
            $this->merge(['code' => mb_strtoupper(trim($this->code))]);
        }

        foreach (['latitude', 'longitude'] as $champ) {
            if (is_string($this->{$champ})) {
                $this->merge([$champ => str_replace(',', '.', trim($this->{$champ}))]);
            }
        }

        // Case à cocher "En service" : absente ou décochée = hors service
        if ($this->has('actif')) {
            $this->merge(['actif' => $this->boolean('actif')]);
        }
    }

    public function rules(): array
    {
        // En modification, le point est dans l'URL : /admin/prelevements/{point}
        $point = $this->route('point');

        return [
            'reseau_id'  => ['required', 'integer', 'exists:reseaux_eau,id'],
            'code'       => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[A-Z0-9]+(-[A-Z0-9]+)*$/',
                Rule::unique('points_prelevement', 'code')->ignore($point?->id),
            ],
            'nom'        => ['required', 'string', 'min:2', 'max:100'],
            'adresse'    => ['required', 'string', 'min:3', 'max:255'],
            'type_point' => ['required', Rule::in(array_keys(PointPrelevement::TYPES))],
            // Position facultative, mais latitude et longitude vont ensemble
            'latitude'   => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude'  => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'actif'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'reseau_id.required' => 'Le réseau est obligatoire.',
            'reseau_id.integer'  => "Le réseau choisi n'est pas valide.",
            'reseau_id.exists'   => "Le réseau choisi n'existe pas.",

            'code.required'      => 'Le code est obligatoire.',
            'code.min'           => 'Le code doit contenir au moins 3 caractères.',
            'code.max'           => 'Le code ne peut pas dépasser 30 caractères.',
            'code.regex'         => 'Le code ne contient que des lettres, des chiffres et des tirets (ex : PP-TN-012).',
            'code.unique'        => 'Ce code est déjà utilisé par un autre point de prélèvement.',

            'nom.required'       => 'Le nom du point est obligatoire.',
            'nom.min'            => 'Le nom doit contenir au moins 2 caractères.',
            'nom.max'            => 'Le nom ne peut pas dépasser 100 caractères.',

            'adresse.required'   => "L'adresse est obligatoire.",
            'adresse.min'        => "L'adresse doit contenir au moins 3 caractères.",
            'adresse.max'        => "L'adresse ne peut pas dépasser 255 caractères.",

            'type_point.required' => 'Le type de point est obligatoire.',
            'type_point.in'       => 'Le type doit être robinet public, réservoir ou sortie de station.',

            'latitude.required_with'  => 'Indiquez aussi la latitude (ou cliquez sur la carte).',
            'latitude.numeric'        => 'La latitude doit être un nombre.',
            'latitude.between'        => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.required_with' => 'Indiquez aussi la longitude (ou cliquez sur la carte).',
            'longitude.numeric'       => 'La longitude doit être un nombre.',
            'longitude.between'       => 'La longitude doit être comprise entre -180 et 180.',

            'actif.boolean'      => "L'état « en service » n'est pas valide.",
        ];
    }
}
