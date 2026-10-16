<?php

namespace App\Http\Requests;

use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZoneRequest extends FormRequest
{
    // Gestionnaires et admins gèrent les zones
    public function authorize(): bool
    {
        return $this->user()?->peutAccederBackOffice() ?? false;
    }

    public function rules(): array
    {
        // En modification, la zone est dans l'URL : /admin/zones/{zone}
        $zone = $this->route('zone');

        return [
            'nom' => [
                'required', 'string', 'min:2', 'max:100',
                "regex:/^[\pL\d\s'\-]+$/u",
                // Pas deux zones du même nom dans la même commune
                Rule::unique('zones', 'nom')
                    ->where('commune', $this->input('commune'))
                    ->ignore($zone?->id),
            ],
            // La commune doit être choisie dans la liste (config/communes.php)
            'commune' => ['required', 'string', Rule::in(Zone::nomsCommunes())],
            'population' => ['required', 'integer', 'min:0', 'max:5000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'     => 'Le nom de la zone est obligatoire.',
            'nom.min'          => 'Le nom doit contenir au moins 2 caractères.',
            'nom.max'          => 'Le nom ne peut pas dépasser 100 caractères.',
            'nom.regex'        => 'Le nom ne peut contenir que des lettres, des chiffres, des espaces, des tirets et des apostrophes.',
            'nom.unique'       => 'Cette zone existe déjà dans cette commune.',

            'commune.required' => 'La commune est obligatoire.',
            'commune.in'       => 'Choisissez une commune dans la liste.',

            'population.required' => 'La population desservie est obligatoire.',
            'population.integer'  => "La population doit être un nombre entier d'habitants.",
            'population.min'      => 'La population ne peut pas être négative.',
            'population.max'      => 'La population ne peut pas dépasser 5 000 000 habitants.',
        ];
    }
}
