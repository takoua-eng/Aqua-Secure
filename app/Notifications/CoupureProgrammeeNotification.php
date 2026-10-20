<?php

namespace App\Notifications;

use App\Models\CoupureProgrammee;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/*
 * Avis aux citoyens d'une zone : coupure d'eau programmée, modifiée ou annulée.
 * Envoyé par email ; la page Coupures du site affiche toujours les coupures à jour.
 */
class CoupureProgrammeeNotification extends Notification
{
    public const EVENEMENTS = [
        'planifiee' => 'Coupure d\'eau programmée',
        'modifiee'  => 'Coupure d\'eau modifiée',
        'annulee'   => 'Coupure d\'eau annulée',
    ];

    public function __construct(
        public CoupureProgrammee $coupure,
        public string $evenement = 'planifiee',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    private function periode(): string
    {
        $c = $this->coupure;

        return 'du ' . $c->debut->format('d/m/Y à H:i') . ' au ' . $c->fin->format('d/m/Y à H:i');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->coupure->loadMissing('reseau.zone');
        $zone = "{$c->reseau->zone->nom} ({$c->reseau->zone->commune})";

        $message = (new MailMessage)
            ->subject('[AquaSecure] ' . self::EVENEMENTS[$this->evenement] . ' — ' . $c->reseau->zone->nom)
            ->greeting('Bonjour ' . ($notifiable->nom ?? '') . ',');

        if ($this->evenement === 'annulee') {
            $message->line("La coupure d'eau prévue dans votre zone **{$zone}** {$this->periode()} est **annulée**.")
                ->line("L'alimentation en eau ne sera pas interrompue.");
        } else {
            $message->line(($this->evenement === 'modifiee' ? 'Les horaires de la coupure ont changé. ' : '')
                    . "Une coupure d'eau est programmée dans votre zone **{$zone}** :")
                ->line("**Quand :** {$this->periode()}")
                ->line("**Pourquoi :** {$c->motif}")
                ->line('Pensez à prévoir une réserve d\'eau pour cette période.');
        }

        return $message
            ->action('Voir les coupures prévues', route('coupures.index'))
            ->salutation("L'équipe AquaSecure");
    }
}
