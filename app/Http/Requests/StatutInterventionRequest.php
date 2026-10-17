<?php

namespace App\Http\Requests;

use App\Models\Intervention;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// Changement de statut d'une intervention : PATCH /admin/interventions/{intervention}/statut (règle T5)
class StatutInterventionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->peutAccederBackOffice() ?? false;
    }

    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::in(array_keys(Intervention::STATUTS))],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $intervention = $this->route('intervention');

                if (! $validator->errors()->has('statut') && ! $intervention->peutPasserA($this->input('statut'))) {
                    $validator->errors()->add('statut',
                        "Une intervention « {$intervention->libelleStatut()} » ne peut pas passer à « "
                        . Intervention::libelleStatutDe($this->input('statut')) . ' ».');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'statut.required' => 'Choisissez le nouveau statut.',
            'statut.in'       => 'Le statut doit être planifiée, en cours ou terminée.',
        ];
    }
}
