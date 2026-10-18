<?php

namespace App\Policies;

use App\Models\Financement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Binôme 3 — droits sur les financements
class FinancementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function view(User $user, Financement $financement): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function create(User $user): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function update(User $user, Financement $financement): Response
    {
        return $this->modifiable($user, $financement, 'modifier');
    }

    public function delete(User $user, Financement $financement): Response
    {
        return $this->modifiable($user, $financement, 'supprimer');
    }

    // Financement issu d'une décision validée, ou d'un projet terminé : réservé à l'administrateur
    private function modifiable(User $user, Financement $financement, string $action): Response
    {
        if (! $user->peutAccederBackOffice()) {
            return Response::deny('Accès non autorisé pour votre rôle.');
        }
        if ($user->isAdmin()) {
            return Response::allow();
        }
        if ($financement->simulation_id) {
            return Response::deny("Ce financement vient d'un scénario validé par un administrateur : seul un administrateur peut le {$action}.");
        }
        if ($financement->projet?->statut === 'termine') {
            return Response::deny("Le projet est terminé : seul un administrateur peut {$action} ses financements.");
        }

        return Response::allow();
    }
}
