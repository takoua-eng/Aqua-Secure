<?php

namespace App\Http\Requests;

use App\Models\Incident;
use App\Models\ReseauEau;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IncidentRequest extends FormRequest
{
    // Gestionnaires et admins gèrent les incidents
    public function authorize(): bool
    {
        return $this->user()?->peutAccederBackOffice() ?? false;
    }

    public function rules(): array
    {
        return [
            'reseau_id'        => ['required', 'integer', 'exists:reseaux_eau,id'],
            'type'             => ['required', Rule::in(array_keys(Incident::TYPES))],
            'gravite'          => ['required', Rule::in(array_keys(Incident::GRAVITES))],
            // Règle I1 : jamais dans le futur
            'date_signalement' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    // Règles qui dépendent de la base : I2 (mise en service du réseau) et I3 (réseau verrouillé)
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['reseau_id', 'date_signalement'])) {
                    return;
                }

                $reseau = ReseauEau::find($this->input('reseau_id'));
                $date = Carbon::parse($this->input('date_signalement'));

                // Règle I2
                if ($reseau?->date_mise_en_service && $date->lt($reseau->date_mise_en_service)) {
                    $validator->errors()->add('date_signalement',
                        "La date de signalement ne peut pas précéder la mise en service du réseau {$reseau->code} "
                        . "({$reseau->date_mise_en_service->format('d/m/Y')}).");
                }

                // Règle I3 (en modification : l'incident est dans l'URL /admin/incidents/{incident})
                $incident = $this->route('incident');
                if ($incident instanceof Incident
                    && (int) $this->input('reseau_id') !== (int) $incident->reseau_id
                    && ! $incident->peutChangerDeReseau()) {
                    $validator->errors()->add('reseau_id',
                        'Une intervention a déjà commencé sur cet incident : son réseau ne peut plus être changé.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'reseau_id.required' => 'Le réseau concerné est obligatoire.',
            'reseau_id.integer'  => "Le réseau choisi n'est pas valide.",
            'reseau_id.exists'   => "Le réseau choisi n'existe pas.",

            'type.required'      => "Le type d'incident est obligatoire.",
            'type.in'            => 'Le type doit être fuite, contamination ou coupure.',

            'gravite.required'   => 'La gravité est obligatoire.',
            'gravite.in'         => 'La gravité doit être faible, moyenne ou critique.',

            'date_signalement.required'        => 'La date de signalement est obligatoire.',
            'date_signalement.date_format'     => "La date de signalement n'est pas valide.",
            'date_signalement.before_or_equal' => 'La date de signalement ne peut pas être dans le futur.',
        ];
    }
}
