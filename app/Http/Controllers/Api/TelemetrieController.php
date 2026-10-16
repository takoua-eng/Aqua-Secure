<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Capteur;
use App\Services\EnregistrementReleve;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/*
 * API de télémétrie : les capteurs (ou le simulateur) envoient eux-mêmes leurs mesures.
 *
 *   POST /api/telemetrie/releves     en-tête X-Api-Key
 *   { "code": "CAP-TN-001", "valeur": 4.2, "date_releve": "2026-10-16 14:00" (facultatif) }
 *
 *   GET  /api/telemetrie/capteurs    liste des capteurs et de leurs seuils
 */
class TelemetrieController extends Controller
{
    public function capteurs(): JsonResponse
    {
        return response()->json(
            Capteur::orderBy('code')->get()->map(fn (Capteur $c) => [
                'code'        => $c->code,
                'type_mesure' => $c->type_mesure,
                'unite'       => $c->unite,
                'seuil_min'   => $c->seuil_min,
                'seuil_max'   => $c->seuil_max,
                'statut'      => $c->statut,
            ])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validateur = Validator::make($request->all(), [
            'code'        => ['required', 'string', 'exists:capteurs,code'],
            'valeur'      => ['required', 'numeric', 'between:-50,100000'],
            'date_releve' => ['nullable', 'date', 'before_or_equal:now'],
        ], [
            'code.required'               => 'Le code du capteur est obligatoire.',
            'code.exists'                 => 'Aucun capteur ne porte ce code.',
            'valeur.required'             => 'La valeur est obligatoire.',
            'valeur.numeric'              => 'La valeur doit être un nombre.',
            'valeur.between'              => 'La valeur doit être comprise entre -50 et 100 000.',
            'date_releve.date'            => "La date du relevé n'est pas valide.",
            'date_releve.before_or_equal' => 'Le relevé ne peut pas être dans le futur.',
        ]);

        if ($validateur->fails()) {
            return response()->json(['erreurs' => $validateur->errors()], 422);
        }

        $capteur = Capteur::where('code', $request->code)->first();

        // Mêmes règles métier que le formulaire
        if ($capteur->statut === 'hors_service') {
            return response()->json(['erreur' => "Le capteur {$capteur->code} est hors service."], 422);
        }
        if ($erreur = $capteur->erreurValeur((float) $request->valeur)) {
            return response()->json(['erreur' => $erreur], 422);
        }

        $resultat = EnregistrementReleve::enregistrer(
            $capteur,
            (float) $request->valeur,
            $request->date_releve ?? now(),
            'Reçu par télémétrie',
        );
        $releve = $resultat['releve'];

        return response()->json([
            'id'             => $releve->id,
            'capteur'        => $capteur->code,
            'valeur'         => $releve->valeur,
            'unite'          => $capteur->unite,
            'date_releve'    => $releve->date_releve->format('Y-m-d H:i:s'),
            'hors_seuil'     => $releve->hors_seuil,
            'sens'           => $releve->sens(),
            'anomalie'       => $resultat['anomalie'],
            'alerte_envoyee' => $resultat['alerte'],
        ], 201);
    }
}