<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Droits sur les incidents (découverte automatique par Laravel : App\Models\Incident => App\Policies\IncidentPolicy).
// Les routes sont déjà réservées aux gestionnaires et admins par le middleware role:gestionnaire,admin.
class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function view(User $user, Incident $incident): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function create(User $user): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function update(User $user, Incident $incident): bool
    {
        return $user->peutAccederBackOffice();
    }

    // Suppression réservée à l'administrateur (la règle I5 est vérifiée ensuite par le contrôleur et le modèle)
    public function delete(User $user, Incident $incident): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny("Seul l'administrateur peut supprimer un incident.");
    }
}
