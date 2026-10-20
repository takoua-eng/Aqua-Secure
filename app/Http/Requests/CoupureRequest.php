<?php

namespace App\Http\Requests;

use App\Models\CoupureProgrammee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class CoupureRequest extends FormRequest
{
    // Gestionnaires et admins planifient les coupures
    public function authorize(): bool
    {
        return $this->user()?->peutAccederBackOffice() ?? false;
    }

    public function rules(): array
    {
        return [
            'reseau_eau_id' => ['required', 'integer', 'exists:reseaux_eau,id'],
            'motif'         => ['required', 'string', 'min:5', 'max:150'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'debut'         => ['required', 'date', 'after:now'],
            'fin'           => ['required', 'date', 'after:debut'],
        ];
    }

    // Règles qui portent sur plusieurs champs : durée maximale et chevauchement sur le même réseau
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['reseau_eau_id', 'debut', 'fin'])) {
                    return;
                }

                $debut = Carbon::parse($this->input('debut'));
                $fin = Carbon::parse($this->input('fin'));

                if ($debut->diffInHours($fin) > CoupureProgrammee::DUREE_MAX_HEURES) {
                    $validator->errors()->add('fin', 'Une coupure programmée ne peut pas durer plus de 7 jours.');

                    return;
                }

                $autre = CoupureProgrammee::chevauchement(
                    (int) $this->input('reseau_eau_id'), $debut, $fin, $this->route('coupure')?->id
                );
                if ($autre) {
                    $validator->errors()->add('debut', 'Une autre coupure est déjà prévue sur ce réseau du '
                        . $autre->debut->format('d/m/Y H:i') . ' au ' . $autre->fin->format('d/m/Y H:i') . '.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'reseau_eau_id.required' => 'Choisissez le réseau concerné par les travaux.',
            'reseau_eau_id.exists'   => "Le réseau choisi n'existe pas.",
            'motif.required'         => 'Le motif est obligatoire.',
            'motif.min'              => 'Le motif doit contenir au moins 5 caractères.',
            'motif.max'              => 'Le motif ne peut pas dépasser 150 caractères.',
            'description.max'        => 'La description ne peut pas dépasser 1 000 caractères.',
            'debut.required'         => 'La date de début est obligatoire.',
            'debut.date'             => "La date de début n'est pas valide.",
            'debut.after'            => 'La coupure doit commencer dans le futur.',
            'fin.required'           => 'La date de fin est obligatoire.',
            'fin.date'               => "La date de fin n'est pas valide.",
            'fin.after'              => 'La fin doit être après le début.',
        ];
    }
}
