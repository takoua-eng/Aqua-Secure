<?php

namespace App\Exceptions;

use App\Models\Incident;
use App\Models\Intervention;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// Règle métier du module Incidents / Interventions (binôme 2) non respectée.
// Laravel appelle render() automatiquement : retour à la page précédente avec le message,
// ou réponse JSON 422 pour les appels API.
class RegleIncidentException extends DomainException
{
    // Règle T5 : seules les transitions de Intervention::TRANSITIONS sont permises
    public static function transitionInterdite(?string $de, string $vers): self
    {
        $libelleDe = Intervention::STATUTS[$de] ?? (string) $de;
        $libelleVers = Intervention::STATUTS[$vers] ?? $vers;

        return new self("Une intervention « {$libelleDe} » ne peut pas passer à « {$libelleVers} ».");
    }

    // Règle T1
    public static function incidentVerrouille(): self
    {
        return new self("L'incident d'une intervention ne peut pas être changé après sa création.");
    }

    // Règle T7
    public static function interventionTerminee(): self
    {
        return new self('Une intervention terminée ne peut plus être modifiée.');
    }

    // Règle T8
    public static function interventionNonSupprimable(Intervention $intervention): self
    {
        return new self(
            "Seule une intervention planifiée peut être supprimée (celle-ci est « {$intervention->libelleStatut()} ») : "
            . 'elle est conservée pour la traçabilité.'
        );
    }

    // Règle I3
    public static function reseauVerrouille(): self
    {
        return new self("Une intervention a déjà commencé sur cet incident : son réseau ne peut plus être changé.");
    }

    // Règle I5
    public static function incidentNonSupprimable(Incident $incident): self
    {
        return new self("L'incident n° {$incident->id} a des interventions : il ne peut pas être supprimé.");
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withErrors(['regle' => $this->getMessage()])->withInput();
    }
}
