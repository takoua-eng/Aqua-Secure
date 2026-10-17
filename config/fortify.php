<?php

use Laravel\Fortify\Features;

/*
 * Laravel Fortify : moteur d'authentification (connexion, inscription, mot de passe oublié,
 * vérification de l'email, authentification à deux facteurs).
 * Fortify ne fournit aucune page : nos vues Tabler / Bootstrap sont branchées
 * dans App\Providers\FortifyServiceProvider.
 */
return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    // Page d'arrivée par défaut (la redirection selon le rôle est faite dans App\Http\Responses\ConnexionReussie)
    'home' => '/',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],

    // Fortify affiche nos vues (définies dans FortifyServiceProvider)
    'views' => true,

    // Adresses en français (sinon Fortify utilise /login, /register…).
    // Les noms de routes avec un point (password.request…) s'écrivent en tableaux imbriqués.
    'paths' => [
        'login'    => '/connexion',
        'logout'   => '/deconnexion',
        'register' => '/inscription',
        'password' => [
            'request' => '/mot-de-passe-oublie',
            'email'   => '/mot-de-passe-oublie',
            'reset'   => '/reinitialiser-mot-de-passe/{token}',
            'update'  => '/reinitialiser-mot-de-passe',
            'confirm' => '/confirmer-mot-de-passe',
        ],
        'verification' => [
            'notice' => '/email/verification',
            'verify' => '/email/verification/{id}/{hash}',
            'send'   => '/email/verification/renvoyer',
        ],
        'two-factor' => [
            'login' => '/connexion/double-authentification',
        ],
    ],

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::twoFactorAuthentication([
            'confirm' => true,          // l'utilisateur doit saisir un premier code pour activer la 2FA
            'confirmPassword' => true,  // mot de passe redemandé avant d'activer / désactiver
        ]),
        // Profil et mot de passe : gérés par notre ProfileController (pages Tabler existantes)
    ],

];