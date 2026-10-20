<?php

namespace App\Providers;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */


public function boot(): void
{
        // En production (Kubernetes derrière l'Ingress sur le port 30080) :
        // l'adresse publique vient de APP_URL, pas des en-têtes du proxy.
        if ($this->app->environment('production') && filled(config('app.url'))) {
            // 1. Liens générés (route(), url(), redirections, liens signés)
            URL::forceRootUrl(config('app.url'));

            // 2. Requête reçue (pagination, vérification des liens signés) :
            //    on lui donne l'hôte et le port de APP_URL
            $racine = parse_url(config('app.url'));
            $hote = $racine['host'] . (isset($racine['port']) ? ':' . $racine['port'] : '');

            $requete = $this->app['request'];
            $requete->headers->set('Host', $hote);
            $requete->server->set('HTTP_HOST', $hote);
            $requete->headers->remove('X-Forwarded-Host');
            $requete->headers->remove('X-Forwarded-Port');
        }
    Paginator::useBootstrapFive();
            // Email de réinitialisation du mot de passe, en français
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
                // Email de vérification de l'adresse (envoyé après l'inscription), en français
        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            return (new MailMessage)
                ->subject('AquaSecure — Confirmez votre adresse email')
                ->greeting('Bonjour ' . $notifiable->nom . ',')
                ->line('Merci pour votre inscription sur AquaSecure. Cliquez sur le bouton ci-dessous pour confirmer votre adresse email.')
                ->action('Confirmer mon adresse email', $url)
                ->line('Ce lien expire dans ' . config('auth.verification.expire', 60) . ' minutes.')
                ->line("Si vous n'avez pas créé de compte, ignorez simplement cet email.")
                ->salutation("L'équipe AquaSecure");
        });

            return (new MailMessage)
                ->subject('AquaSecure — Réinitialisation de votre mot de passe')
                ->greeting('Bonjour ' . $notifiable->nom . ',')
                ->line('Vous avez demandé à réinitialiser le mot de passe de votre compte AquaSecure.')
                ->action('Choisir un nouveau mot de passe', $url)
                ->line('Ce lien expire dans ' . config('auth.passwords.users.expire') . ' minutes.')
                ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email : votre mot de passe ne changera pas.")
                ->salutation("L'équipe AquaSecure");
        });
}
}
