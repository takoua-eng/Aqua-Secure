<?php

namespace App\Policies;

use App\Models\SimulationFinancement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/*
 * Binôme 3 — cycle de validation des scénarios What If?
 *   gestionnaire : simule, enregistre, soumet ses brouillons ;
 *   administrateur : approuve ou rejette, puis applique (seule action qui crée des financements réels).
 * Un brouillon est privé : visible seulement par son auteur et les administrateurs.
 */
class SimulationFinancementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->peutAccederBackOffice();
    }

    public function view(User $user, SimulationFinancement $simulation): bool
    {
        return $user->isAdmin() || $this->estAuteur($user, $simulation)
            || ($user->peutAccederBackOffice() && $simulation->statut !== 'brouillon');
    }

    public function create(User $user): bool
    {
        return $user->peutAccederBackOffice();
    }

    // Ajouter l'analyse IA : tant que la décision n'est pas prise
    public function update(User $user, SimulationFinancement $simulation): bool
    {
        return ($user->isAdmin() || $this->estAuteur($user, $simulation))
            && in_array($simulation->statut, ['brouillon', 'soumise'], true);
    }

    public function delete(User $user, SimulationFinancement $simulation): Response
    {
        if ($this->estAuteur($user, $simulation) && $simulation->statut === 'brouillon') {
            return Response::allow();
        }
        if ($user->isAdmin() && in_array($simulation->statut, ['brouillon', 'rejetee'], true)) {
            return Response::allow();
        }

        return Response::deny('Seul un brouillon (par son auteur) ou une simulation rejetée (par un administrateur) peut être supprimé.');
    }

    public function soumettre(User $user, SimulationFinancement $simulation): Response
    {
        return $this->estAuteur($user, $simulation) && $simulation->statut === 'brouillon'
            ? Response::allow()
            : Response::deny('Seul l\'auteur peut soumettre son brouillon pour validation.');
    }

    public function decider(User $user, SimulationFinancement $simulation): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny('Seul un administrateur peut approuver ou rejeter un scénario.');
        }

        return $simulation->statut === 'soumise'
            ? Response::allow()
            : Response::deny('Ce scénario n\'est pas en attente de validation.');
    }

    public function appliquer(User $user, SimulationFinancement $simulation): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny('Seul un administrateur peut appliquer un scénario.');
        }

        return $simulation->statut === 'approuvee'
            ? Response::allow()
            : Response::deny('Seul un scénario approuvé peut être appliqué.');
    }

    private function estAuteur(User $user, SimulationFinancement $simulation): bool
    {
        return $simulation->user_id !== null && $simulation->user_id === $user->id;
    }
}
