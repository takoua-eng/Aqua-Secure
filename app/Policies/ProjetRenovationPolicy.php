<?php

namespace App\Policies;

use App\Models\ProjetRenovation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Binôme 3 — droits sur les projets de rénovation (en plus du middleware role:gestionnaire,admin des routes)
class ProjetRenovationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function view(User $user, ProjetRenovation $projet): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function create(User $user): bool
    {
        return $user->peutAccederBackOffice();
    }

    // Un projet terminé est figé : seul un administrateur peut encore le corriger
    public function update(User $user, ProjetRenovation $projet): Response
    {
        if (! $user->peutAccederBackOffice()) {
            return Response::deny('Accès non autorisé pour votre rôle.');
        }

        return $projet->statut === 'termine' && ! $user->isAdmin()
            ? Response::deny('Ce projet est terminé : seul un administrateur peut le modifier.')
            : Response::allow();
    }

    // Les financements sont des pièces comptables : un projet financé ne peut pas être supprimé
    public function delete(User $user, ProjetRenovation $projet): Response
    {
        if (! $user->peutAccederBackOffice()) {
            return Response::deny('Accès non autorisé pour votre rôle.');
        }
        if ($projet->financements()->exists()) {
            return Response::deny("Le projet « {$projet->titre} » a des financements : il ne peut pas être supprimé.");
        }

        return $projet->statut === 'termine' && ! $user->isAdmin()
            ? Response::deny('Ce projet est terminé : seul un administrateur peut le supprimer.')
            : Response::allow();
    }
}
