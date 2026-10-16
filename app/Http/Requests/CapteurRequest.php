<?php

namespace App\Http\Requests;

use App\Models\Capteur;
use App\Models\ReseauEau;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Contrôle de saisie du formulaire Capteur (ajout et modification).
 */
class CapteurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'accès est déjà limité aux gestionnaires/admins par les routes
    }

    // Avant la validation : code en majuscules, unité déduite du type de mesure
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code'  => strtoupper(trim((string) $this->code)),
            'unite' => Capteur::UNITES[$this->type_mesure] ?? null,
        ]);
    }

    public function rules(): array
    {
        // En modification, le capteur est dans l'URL : /admin/capteurs/{capteur}
        $capteur = $this->route('capteur');

        return [
            'reseau_id'         => ['required', 'integer', 'exists:reseaux_eau,id'],
                       'code'              => ['required', 'string', 'regex:/^' . Capteur::FORMAT_CODE . '$/',
                                    Rule::unique('capteurs', 'code')->ignore($capteur?->id)],
            // Lettres, chiffres, espaces et . + - / ( ) ; au moins une lettre
            'modele'            => ['required', 'string', 'min:2', 'max:100', 'regex:/^(?=.*\pL)[\pL0-9 .+\-\/()]+$/u'],
            'type_mesure'       => ['required', Rule::in(array_keys(Capteur::TYPES_MESURE))],
            'unite'             => ['required', Rule::in(array_values(Capteur::UNITES))],
                       'seuil_min'         => ['bail', 'required', 'numeric', 'decimal:0,2'],
            'seuil_max'         => ['bail', 'required', 'numeric', 'decimal:0,2', 'gt:seuil_min'],
            'date_installation' => ['required', 'date', 'before_or_equal:today'],
            'statut'            => ['required', Rule::in(array_keys(Capteur::STATUTS))],
        ];
    }

    // Contrôles qui dépendent de plusieurs champs ou de la base de données
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return; // on attend que les règles simples soient correctes
                }

                // Seule la température peut être négative
                // Les seuils doivent rester dans la plage physique du type de mesure
                // (0 – 25 bar pour une pression…) ; seule la température peut être négative
                [$limMin, $limMax] = Capteur::LIMITES[$this->type_mesure];
                $mesure = mb_strtolower(Capteur::TYPES_MESURE[$this->type_mesure]);
                $plage = "{$limMin} et {$limMax} {$this->unite}";

                foreach (['seuil_min' => 'minimum', 'seuil_max' => 'maximum'] as $champ => $nom) {
                    if ($this->$champ < 0 && $limMin >= 0) {
                        $validator->errors()->add($champ, "Le seuil {$nom} ne peut pas être négatif pour une mesure de {$mesure}.");
                    } elseif ($this->$champ < $limMin || $this->$champ > $limMax) {
                        $validator->errors()->add($champ, "Le seuil {$nom} doit être compris entre {$plage} pour une mesure de {$mesure}.");
                    }
                }

                // Un capteur ne peut pas être installé avant la mise en service du réseau
                $reseau = ReseauEau::find($this->reseau_id);
                if ($reseau && $reseau->date_mise_en_service
                    && $this->date('date_installation')->lt($reseau->date_mise_en_service)) {
                    $validator->errors()->add('date_installation',
                        "Le réseau {$reseau->code} a été mis en service le "
                        . $reseau->date_mise_en_service->format('d/m/Y') . ' : le capteur ne peut pas être installé avant.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'reseau_id.required'                => 'Choisissez le réseau équipé.',
            'reseau_id.exists'                  => "Ce réseau n'existe pas.",

            'code.required'                     => 'Le code est obligatoire.',
            'code.regex'                        => 'Format attendu : CAP-XX-000, soit CAP, 2 ou 3 lettres, puis 3 ou 4 chiffres (ex : CAP-TN-001).',
            'code.unique'                       => 'Ce code est déjà utilisé par un autre capteur.',

            'modele.required'                   => 'Le modèle est obligatoire.',
            'modele.min'                        => 'Le modèle doit contenir au moins 2 caractères.',
            'modele.max'                        => 'Le modèle ne peut pas dépasser 100 caractères.',
            'modele.regex'                      => 'Le modèle doit contenir des lettres ; seuls les chiffres, espaces et . + - / ( ) sont permis en plus.',

            'type_mesure.required'              => 'Choisissez le type de mesure.',
            'type_mesure.in'                    => "Ce type de mesure n'existe pas.",
            'unite.required'                    => "L'unité n'a pas pu être déterminée : choisissez un type de mesure.",
            'unite.in'                          => "Cette unité n'est pas autorisée.",

            'seuil_min.required'                => 'Le seuil minimum est obligatoire.',
            'seuil_min.numeric'                 => 'Le seuil minimum doit être un nombre.',
            'seuil_min.decimal'                 => 'Le seuil minimum peut avoir au plus 2 chiffres après la virgule.',            'seuil_max.required'                => 'Le seuil maximum est obligatoire.',
            'seuil_max.numeric'                 => 'Le seuil maximum doit être un nombre.',
            'seuil_max.decimal'                 => 'Le seuil maximum peut avoir au plus 2 chiffres après la virgule.',
            'seuil_max.gt'                      => 'Le seuil maximum doit être supérieur au seuil minimum.',

            'date_installation.required'        => "La date d'installation est obligatoire.",
            'date_installation.date'            => "La date d'installation n'est pas valide.",
            'date_installation.before_or_equal' => "La date d'installation ne peut pas être dans le futur.",

            'statut.required'                   => 'Choisissez le statut.',
            'statut.in'                         => "Ce statut n'existe pas.",
        ];
    }
}