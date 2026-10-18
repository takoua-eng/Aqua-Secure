<?php

namespace App\Http\Resources;

use App\Models\Incident;
use App\Services\PrioriteIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Format JSON d'un incident (pages du module appelées avec l'en-tête Accept: application/json)
class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statut = $this->statutActuel();

        return [
            'id'               => $this->id,
            'reseau'           => $this->whenLoaded('reseau', fn () => [
                'id'   => $this->reseau->id,
                'code' => $this->reseau->code,
                'etat' => $this->reseau->etat,
                'zone' => $this->reseau->relationLoaded('zone') ? $this->reseau->zone?->nom : null,
            ]),
            'type'             => ['valeur' => $this->type, 'libelle' => $this->libelleType()],
            'gravite'          => ['valeur' => $this->gravite, 'libelle' => $this->libelleGravite()],
            'date_signalement' => $this->date_signalement->toDateString(),
            'statut'           => ['valeur' => $statut, 'libelle' => Incident::STATUTS[$statut]],
            'interventions'    => InterventionResource::collection($this->whenLoaded('interventions')),
            'priorite'         => $this->when(
                $this->relationLoaded('reseau') && $this->relationLoaded('interventions'),
                fn () => PrioriteIncident::calculer($this->resource),
            ),
            'cree_le'          => $this->created_at?->toIso8601String(),
            'modifie_le'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
