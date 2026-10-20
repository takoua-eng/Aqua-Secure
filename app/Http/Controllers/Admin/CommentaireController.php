<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommentaireCoupure;
use Illuminate\Support\Str;

// Modération : le gestionnaire / l'admin supprime un commentaire depuis "Voir détails" d'une coupure
class CommentaireController extends Controller
{
    public function destroy(CommentaireCoupure $commentaire)
    {
        $coupureId = $commentaire->coupure_programmee_id;
        $commentaire->delete(); // ses "j'aime" sont supprimés avec lui (clé étrangère en cascade)

        // Retour sur la liste des coupures (même page, mêmes filtres), panneau de détails rouvert
        $page = Str::before(url()->previous(), '#');
        if (! Str::startsWith($page, route('admin.coupures.index'))) {
            $page = route('admin.coupures.index');
        }

        return redirect()->to($page . '#details-' . $coupureId)->with('success', 'Commentaire supprimé.');
    }
}
