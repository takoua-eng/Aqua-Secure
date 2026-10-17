<?php

namespace App\Policies;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Droits sur les interventions (découverte automatique par Laravel).
class InterventionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function view(User $user, Intervention $intervention): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function create(User $user): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function update(User $user, Intervention $intervention): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function changerStatut(User $user, Intervention $intervention): bool
    {
        return $user->peutAccederBackOffice();
    }

    // Suppression réservée à l'administrateur (la règle T8 est vérifiée ensuite)
    public function delete(User $user, Intervention $intervention): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny("Seul l'administrateur peut supprimer une intervention.");
    }
}
