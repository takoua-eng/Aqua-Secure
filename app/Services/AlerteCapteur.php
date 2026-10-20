<?php

namespace App\Services;

use App\Models\Releve;
use App\Models\User;
use App\Notifications\AlerteCapteurNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/*
 * Alerte email envoyée aux gestionnaires et administrateurs
 * quand un relevé sort des seuils ou est jugé anormal par l'IA.
 * Anti-spam : au plus une alerte par capteur toutes les DELAI_MINUTES.
 */
class AlerteCapteur
{
    public const DELAI_MINUTES = 60;

    // Retourne true si l'alerte a été envoyée
    public static function envoyer(Releve $releve, ?array $anomalie = null): bool
    {
        // Cache::add ne réussit que si la clé n'existe pas encore : une seule alerte par heure et par capteur
        // Une clé par mode d'envoi : les alertes du simulateur (écrites dans les logs)
        // ne bloquent plus les vraies alertes email saisies dans le back office
        $cle = 'alerte-capteur:' . config('mail.default') . ':' . $releve->capteur_id;
        if (! Cache::add($cle, true, now()->addMinutes(self::DELAI_MINUTES))) {
            return false;
        }

        $destinataires = User::whereIn('role', ['gestionnaire', 'admin'])->get();
        if ($destinataires->isEmpty()) {
            return false;
        }

        try {
            Notification::send($destinataires, new AlerteCapteurNotification($releve, $anomalie));
        } catch (Throwable $e) {
            // Un problème de messagerie ne doit pas empêcher l'enregistrement du relevé
            Log::warning('Alerte capteur : email non envoyé', ['capteur' => $releve->capteur_id, 'erreur' => $e->getMessage()]);

            return false;
        }

        return true;
    }
}