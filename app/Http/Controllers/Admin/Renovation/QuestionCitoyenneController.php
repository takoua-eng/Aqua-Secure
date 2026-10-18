<?php

namespace App\Http\Controllers\Admin\Renovation;

use App\Http\Controllers\Controller;
use App\Models\ProjetRenovation;
use App\Models\QuestionProjet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// Binôme 3 — réponses des gestionnaires aux questions des citoyens (publiées avec la réponse)
class QuestionCitoyenneController extends Controller
{
    public function repondre(Request $request, ProjetRenovation $projet, QuestionProjet $question)
    {
        Gate::authorize('view', $projet);

        $donnees = $request->validate([
            'reponse' => ['required', 'string', 'min:5', 'max:1500'],
            'publiee' => ['nullable', 'boolean'],
        ], [
            'reponse.required' => 'Écrivez la réponse.',
            'reponse.min'      => 'La réponse doit contenir au moins 5 caractères.',
            'reponse.max'      => 'La réponse ne peut pas dépasser 1 500 caractères.',
        ]);

        $question->update([
            'reponse'     => trim($donnees['reponse']),
            'publiee'     => $request->boolean('publiee', true),
            'repondu_par' => $request->user()->id,
            'repondu_le'  => now(),
        ]);

        return back()->with('success', $question->publiee
            ? 'Réponse publiée sur la page citoyenne du projet.'
            : 'Réponse enregistrée (non publiée).');
    }

    public function supprimer(ProjetRenovation $projet, QuestionProjet $question)
    {
        Gate::authorize('update', $projet);

        $question->delete();

        return back()->with('success', 'Question supprimée.');
    }
}
