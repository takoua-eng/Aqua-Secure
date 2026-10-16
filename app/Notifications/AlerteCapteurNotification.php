<?php

namespace App\Notifications;

use App\Models\Releve;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/*
 * Email d'alerte : relevé hors seuil et/ou anomalie détectée par l'IA.
 */
class AlerteCapteurNotification extends Notification
{
    public function __construct(
        public Releve $releve,
        public ?array $anomalie = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $releve = $this->releve;
        $capteur = $releve->capteur->loadMissing('reseau.zone');
        $mesure = mb_strtolower($capteur->libelleTypeMesure());

        $objet = $releve->hors_seuil
            ? "valeur hors seuil ({$releve->sens()})"
            : 'valeur inhabituelle';

        $message = (new MailMessage)
            ->subject("[AquaSecure] Alerte {$capteur->code} : {$objet}")
            ->greeting('Bonjour ' . ($notifiable->nom ?? '') . ',')
            ->line("Le capteur **{$capteur->code}** ({$mesure}) installé sur le réseau **{$capteur->reseau->code}**"
                . " — {$capteur->reseau->zone->nom}, {$capteur->reseau->zone->commune} — vient de transmettre une mesure à surveiller.")
            ->line('**Valeur mesurée :** ' . $capteur->avecUnite($releve->valeur)
                . ' le ' . $releve->date_releve->format('d/m/Y à H:i'))
            ->line('**Seuils normaux :** ' . $capteur->plageSeuils());

        if ($releve->hors_seuil) {
            $message->line("⚠ Valeur **hors seuil ({$releve->sens()})** : elle sort de la plage normale.");
        }

        if ($this->anomalie) {
            $message->line('⚠ Analyse IA : valeur inhabituelle par rapport aux mesures précédentes (attendue vers '
                . $capteur->avecUnite($this->anomalie['attendu']) . ', score z = ' . $this->anomalie['score'] . ').');
        }

        return $message
            ->action('Voir le capteur', route('admin.capteurs.show', $capteur))
            ->line("Vous recevrez au plus une alerte par heure pour ce capteur.")
            ->salutation("L'équipe AquaSecure");
    }
}