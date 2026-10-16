<?php

namespace App\Http\Requests;

use App\Models\ReseauEau;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReseauEauRequest extends FormRequest
{
    // Gestionnaires et admins gèrent les réseaux
    public function authorize(): bool
    {
        return $this->user()?->peutAccederBackOffice() ?? false;
    }

    // Le code est toujours enregistré en majuscules : "can-tun-001" => "CAN-TUN-001"
    protected function prepareForValidation(): void
    {
        if (is_string($this->code)) {
            $this->merge(['code' => mb_strtoupper(trim($this->code))]);
        }
    }

    public function rules(): array
    {
        // En modification, le réseau est dans l'URL : /admin/reseaux/{reseau}
        return self::regles($this->route('reseau'));
    }

    // Règles partagées avec l'import CSV (ReseauImportController)
    public static function regles(?ReseauEau $reseau = null): array
    {
        return [
            'zone_id'  => ['required', 'integer', 'exists:zones,id'],
            'code'     => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[A-Z0-9]+(-[A-Z0-9]+)*$/',
                Rule::unique('reseaux_eau', 'code')->ignore($reseau?->id),
            ],
            'type'     => ['required', Rule::in(array_keys(ReseauEau::TYPES))],
            'materiau' => ['required', Rule::in(array_keys(ReseauEau::MATERIAUX))],
            'date_mise_en_service' => ['required', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'etat'     => ['required', Rule::in(array_keys(ReseauEau::ETATS))],
            // Position facultative, mais latitude et longitude vont ensemble (limites de la Tunisie)
            'latitude'  => ['nullable', 'required_with:longitude', 'numeric', 'between:30,38'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:7,12'],
        ];
    }

    public function messages(): array
    {
        return self::messagesErreur();
    }

    public static function messagesErreur(): array
    {
        return [
            'zone_id.required' => 'La zone est obligatoire.',
            'zone_id.integer'  => "La zone choisie n'est pas valide.",
            'zone_id.exists'   => "La zone choisie n'existe pas.",

            'code.required'    => 'Le code est obligatoire.',
            'code.min'         => 'Le code doit contenir au moins 3 caractères.',
            'code.max'         => 'Le code ne peut pas dépasser 30 caractères.',
            'code.regex'       => 'Le code ne contient que des lettres, des chiffres et des tirets (ex : CAN-TUN-001).',
            'code.unique'      => 'Ce code est déjà utilisé par un autre réseau.',

            'type.required'    => 'Le type est obligatoire.',
            'type.in'          => 'Le type doit être canalisation, réservoir ou station.',

            'materiau.required' => 'Le matériau est obligatoire.',
            'materiau.in'       => "Le matériau choisi n'existe pas.",

            'date_mise_en_service.required'        => 'La date de mise en service est obligatoire.',
            'date_mise_en_service.date'            => "La date de mise en service n'est pas valide.",
            'date_mise_en_service.after_or_equal'  => 'La date de mise en service ne peut pas être avant 1900.',
            'date_mise_en_service.before_or_equal' => 'La date de mise en service ne peut pas être dans le futur.',

            'etat.required'    => "L'état est obligatoire.",
            'etat.in'          => "L'état doit être bon, dégradé ou hors service.",

            'latitude.required_with'  => 'Indiquez aussi la latitude (ou cliquez sur la carte).',
            'latitude.numeric'        => 'La latitude doit être un nombre.',
            'latitude.between'        => 'La latitude doit être comprise entre 30 et 38 (Tunisie).',
            'longitude.required_with' => 'Indiquez aussi la longitude (ou cliquez sur la carte).',
            'longitude.numeric'       => 'La longitude doit être un nombre.',
            'longitude.between'       => 'La longitude doit être comprise entre 7 et 12 (Tunisie).',
        ];
    }
}
