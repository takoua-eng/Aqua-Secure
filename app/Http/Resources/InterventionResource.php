<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Format JSON d'une intervention
class InterventionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'incident_id'       => $this->incident_id,
            'statut'            => ['valeur' => $this->statut, 'libelle' => $this->libelleStatut()],
            'date_intervention' => $this->date_intervention->toDateString(),
            'en_retard'         => $this->estEnRetard(),
            'statuts_suivants'  => $this->statutsSuivants(),
            'historique'        => $this->whenLoaded('historiques', fn () => $this->historiques->map(fn ($h) => [
                'ancien_statut'  => $h->ancien_statut,
                'nouveau_statut' => $h->nouveau_statut,
                'par'            => $h->relationLoaded('user') ? $h->user?->nom : null,
                'le'             => $h->created_at?->toIso8601String(),
            ])),
            'cree_le'           => $this->created_at?->toIso8601String(),
            'modifie_le'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
