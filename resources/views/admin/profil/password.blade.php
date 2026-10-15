@extends('layouts.admin')

@section('title', 'Paramètres du compte')
@section('pretitle', 'Mon compte')

@section('content')
<div class="card">
    <div class="row g-0">
        <div class="col-12 col-md-3 border-end">
            @include('admin.profil._menu')
        </div>

        <div class="col-12 col-md-9 d-flex flex-column">
            <form method="POST" action="{{ route('admin.parametres.password.update') }}" class="d-flex flex-column h-100" data-validate>
                @csrf
                @method('PUT')

                <div class="card-body">
                    <h2 class="mb-4">Mot de passe</h2>
                    <p class="text-secondary mb-4">
                        Pour votre sécurité, saisissez d'abord votre mot de passe actuel.
                    </p>

                    <div class="row">
                        <div class="col-md-6">
                            <x-input name="current_password" type="password" label="Mot de passe actuel" icon="lock" required
                                     autocomplete="current-password"
                                     data-msg-required="Saisissez votre mot de passe actuel."
                                     valid-message="" />
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <x-input name="password" type="password" label="Nouveau mot de passe" icon="lock-plus" required
                                     minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).+" autocomplete="new-password"
                                     hint="8 caractères minimum, avec une lettre et un chiffre."
                                     data-msg-required="Le nouveau mot de passe est obligatoire."
                                     data-msg-minlength="Au moins 8 caractères."
                                     data-msg-pattern="Il faut au moins une lettre et un chiffre."
                                     valid-message="Mot de passe valide." />
                        </div>
                        <div class="col-md-6">
                            <x-input name="password_confirmation" type="password" label="Confirmer le nouveau mot de passe" icon="lock-check" required
                                     data-match="password" autocomplete="new-password"
                                     data-msg-required="Confirmez le nouveau mot de passe."
                                     data-msg-match="Les deux mots de passe ne correspondent pas."
                                     valid-message="Les mots de passe correspondent." />
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-transparent mt-auto">
                    <div class="btn-list justify-content-end">
                        <a href="{{ route('admin.parametres') }}" class="btn">Annuler</a>
                        <button type="button" class="btn btn-primary"
                                data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                data-confirm-type="primary"
                                data-confirm-title="Changer votre mot de passe ?"
                                data-confirm-message="Vous utiliserez le nouveau mot de passe à votre prochaine connexion."
                                data-confirm-button="Oui, changer">
                            Changer le mot de passe
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection