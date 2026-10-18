<?php

namespace App\Http\Requests;

use App\Models\Financement;
use App\Models\ProjetRenovation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Binôme 3 — contrôle de saisie du formulaire Financement (ajout et modification).
 */
class FinancementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['montant' => ProjetRenovationRequest::montant($this->montant)]);
    }

    public function rules(): array
    {
        return [
            'projet_id' => ['required', 'integer', 'exists:projets_renovation,id'],
            'source'    => ['required', Rule::in(array_keys(Financement::SOURCES))],
            'montant'   => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:' . Financement::MONTANT_MAX],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $financement = $this->route('financement');
                $projet = ProjetRenovation::find($this->projet_id);
                $changeDeProjet = ! $financement || (int) $financement->projet_id !== (int) $projet->id;

                // Un projet terminé ne reçoit plus de nouveau financement (sauf correction par un administrateur)
                if ($changeDeProjet && $projet->statut === 'termine' && ! $this->user()->isAdmin()) {
                    $validator->errors()->add('projet_id', "Le projet « {$projet->titre} » est terminé : il ne reçoit plus de financement.");

                    return;
                }

                // Coût déclaré (devis) : le total des financements ne peut pas le dépasser
                if ($projet->cout_estime !== null && (float) $projet->cout_estime > 0) {
                    $autres = (float) $projet->financements()
                        ->when($financement, fn ($q) => $q->whereKeyNot($financement->id))
                        ->sum('montant');
                    $disponible = round((float) $projet->cout_estime - $autres, 2);

                    if ((float) $this->montant > $disponible + 0.005) {
                        $validator->errors()->add('montant', $disponible > 0
                            ? 'Ce montant dépasse le reste à financer du projet (' . number_format($disponible, 2, ',', ' ') . ' DT selon le coût déclaré).'
                            : 'Le projet est déjà entièrement financé selon son coût déclaré.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'projet_id.required' => 'Choisissez le projet financé.',
            'projet_id.exists'   => "Ce projet n'existe pas.",
            'source.required'    => 'Choisissez la source du financement.',
            'source.in'          => "Cette source n'existe pas.",
            'montant.required'   => 'Le montant est obligatoire.',
            'montant.numeric'    => 'Le montant doit être un nombre (en dinars).',
            'montant.decimal'    => 'Le montant peut avoir au plus 2 chiffres après la virgule (millimes non gérés).',
            'montant.gt'         => 'Le montant doit être strictement positif.',
            'montant.max'        => 'Un financement ne peut pas dépasser 99 999 999,99 DT.',
        ];
    }
}
