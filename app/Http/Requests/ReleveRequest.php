<?php

namespace App\Http\Requests;

use App\Models\Capteur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Contrôle de saisie du formulaire Relevé (ajout et modification).
 */
class ReleveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'capteur_id'  => ['required', 'integer', 'exists:capteurs,id'],
            'valeur'      => ['bail', 'required', 'numeric', 'decimal:0,3'],            'date_releve' => ['required', 'date', 'before_or_equal:now'],
            'remarque'    => ['nullable', 'string', 'max:255'],
        ];
    }

    // Contrôles qui dépendent du capteur choisi
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $capteur = Capteur::find($this->capteur_id);

                // Un capteur hors service ne produit plus de mesures
                if ($capteur->statut === 'hors_service') {
                    $validator->errors()->add('capteur_id',
                        "Le capteur {$capteur->code} est hors service : impossible d'enregistrer un relevé.");
                }

                // Pas de mesure avant l'installation du capteur
                if ($this->date('date_releve')->lt($capteur->date_installation)) {
                    $validator->errors()->add('date_releve',
                        "Le capteur {$capteur->code} a été installé le "
                        . $capteur->date_installation->format('d/m/Y') . ' : le relevé ne peut pas être antérieur.');
                }

                // Seule une température peut être négative
                     // La valeur doit rester dans la plage physique du capteur ; seule une température peut être négative
                if ($erreur = $capteur->erreurValeur((float) $this->valeur)) {
                    $validator->errors()->add('valeur', $erreur);
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'capteur_id.required'         => 'Choisissez le capteur.',
            'capteur_id.exists'           => "Ce capteur n'existe pas.",

            'valeur.required'             => 'La valeur mesurée est obligatoire.',
            'valeur.numeric'              => 'La valeur doit être un nombre (ex : 4.25).',
            'valeur.decimal'              => 'La valeur peut avoir au plus 3 chiffres après la virgule.',
            'date_releve.required'        => 'La date et l\'heure du relevé sont obligatoires.',
            'date_releve.date'            => "La date du relevé n'est pas valide.",
            'date_releve.before_or_equal' => 'Le relevé ne peut pas être dans le futur.',

            'remarque.max'                => 'La remarque ne peut pas dépasser 255 caractères.',
        ];
    }
}