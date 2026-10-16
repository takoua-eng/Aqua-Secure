<?php

namespace App\Services;

use App\Models\Capteur;
use App\Models\Releve;

/*
 * Enregistre un nouveau relevé, quelle que soit sa provenance
 * (formulaire, API de télémétrie, simulateur) :
 *   1. création du relevé (hors_seuil est calculé par le modèle Releve) ;
 *   2. détection d'anomalie par rapport aux relevés précédents ;
 *   3. alerte email aux gestionnaires si le relevé est hors seuil ou anormal.
 */
class EnregistrementReleve
{
    /**
     * @return array{releve: Releve, anomalie: ?array, alerte: bool}
     */
    public static function enregistrer(Capteur $capteur, float $valeur, $date = null, ?string $remarque = null): array
    {
        $releve = $capteur->releves()->create([
            'valeur'      => $valeur,
            'date_releve' => $date ?? now(),
            'remarque'    => $remarque,
        ]);
        $releve->setRelation('capteur', $capteur);

        $anomalie = self::anomalie($releve);

        $alerte = ($releve->hors_seuil || $anomalie)
            ? AlerteCapteur::envoyer($releve, $anomalie)
            : false;

        return ['releve' => $releve, 'anomalie' => $anomalie, 'alerte' => $alerte];
    }

    // Le relevé est-il anormal par rapport aux relevés précédents du même capteur ?
    public static function anomalie(Releve $releve): ?array
    {
        $historique = Releve::where('capteur_id', $releve->capteur_id)
            ->where('date_releve', '<=', $releve->date_releve)
            ->orderByDesc('date_releve')
            ->limit(AnalyseCapteur::FENETRE + 1)
            ->get()->reverse()->values();

        return AnalyseCapteur::anomalies($historique)[$releve->id] ?? null;
    }
}