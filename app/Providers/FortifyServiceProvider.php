<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\ConnexionReussie;
use App\Http\Responses\EmailVerifie;
use App\Http\Responses\InscriptionReussie;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Nos redirections après connexion, inscription et vérification de l'email
        $this->app->singleton(LoginResponse::class, ConnexionReussie::class);
        $this->app->singleton(TwoFactorLoginResponse::class, ConnexionReussie::class);
        $this->app->singleton(RegisterResponse::class, InscriptionReussie::class);
        $this->app->singleton(VerifyEmailResponse::class, EmailVerifie::class);
    }

    public function boot(): void
    {
        // Actions : inscription et réinitialisation du mot de passe
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Nos pages (Fortify n'en fournit aucune)
        Fortify::loginView(fn () => view('admin.login'));
        Fortify::registerView(fn () => view('front.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            'token' => $request->route('token'),
            'email' => $request->query('email'),
        ]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));

        // Anti force brute : 5 essais par minute (connexion et code 2FA)
        RateLimiter::for('login', function (Request $request) {
            $cle = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($cle);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}