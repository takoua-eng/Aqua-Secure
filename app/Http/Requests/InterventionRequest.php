<?php

namespace App\Http\Requests;

use App\Models\Incident;
use App\Models\Intervention;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/*
 * Création : /admin/incidents/{incident}/interventions (statut + date)
 * Modification : /admin/interventions/{intervention} (date seulement : le statut change par une action dédiée, règle T5)
 */
class InterventionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->peutAccederBackOffice() ?? false;
    }

    public function rules(): array
    {
        $creation = ! $this->route('intervention') instanceof Intervention;

        return [
            'statut'            => $creation
                ? ['required', Rule::in(array_keys(Intervention::STATUTS))]
                : ['prohibited'],
            'date_intervention' => ['required', 'date_format:Y-m-d'],
        ];
    }

    // Règles de date T2, T3 et T4 (elles dépendent de l'incident et du statut)
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['statut', 'date_intervention'])) {
                    return;
                }

                $intervention = $this->route('intervention');
                $intervention = $intervention instanceof Intervention ? $intervention : null;
                $incident = $intervention?->incident ?? $this->route('incident');
                $statut = $intervention?->statut ?? $this->input('statut');
                $date = Carbon::parse($this->input('date_intervention'))->startOfDay();

                if ($incident instanceof Incident && $date->lt($incident->date_signalement)) {
                    // Règle T2
                    $validator->errors()->add('date_intervention',
                        "La date d'intervention ne peut pas précéder le signalement de l'incident "
                        . "({$incident->date_signalement->format('d/m/Y')}).");
                } elseif ($statut === 'planifiee' && $date->lt(today())) {
                    // Règle T3
                    $validator->errors()->add('date_intervention',
                        "Une intervention planifiée doit avoir une date à venir (aujourd'hui ou plus tard).");
                } elseif ($statut !== 'planifiee' && $date->gt(today())) {
                    // Règle T4
                    $validator->errors()->add('date_intervention',
                        'Une intervention en cours ou terminée ne peut pas avoir une date dans le futur.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'statut.required'               => 'Le statut est obligatoire.',
            'statut.in'                     => 'Le statut doit être planifiée, en cours ou terminée.',
            'statut.prohibited'             => 'Le statut se change avec le bouton « Changer le statut », pas dans ce formulaire.',
            'date_intervention.required'    => "La date d'intervention est obligatoire.",
            'date_intervention.date_format' => "La date d'intervention n'est pas valide.",
        ];
    }
}
