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
            <form method="POST" action="{{ route('admin.parametres.update') }}" class="d-flex flex-column h-100" data-validate>
                @csrf
                @method('PUT')

                <div class="card-body">
                    <h2 class="mb-4">Mon compte</h2>

                    <h3 class="card-title">Profil</h3>
                    <div class="row align-items-center mb-4">
                        <div class="col-auto">
                            <span class="avatar avatar-xl rounded-circle bg-{{ $user->couleurRole() }}-lt">{{ $user->initiales() }}</span>
                        </div>
                        <div class="col">
                            <div class="fw-bold">{{ $user->nom }}</div>
                            <span class="badge bg-{{ $user->couleurRole() }}-lt mt-1">{{ $user->libelleRole() }}</span>
                            <div class="small text-secondary mt-1">Le rôle ne peut être modifié que par un administrateur.</div>
                        </div>
                    </div>

                    <h3 class="card-title">Informations personnelles</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <x-input name="nom" label="Nom complet" icon="user" :value="$user->nom" required
                                     minlength="3" maxlength="100" pattern="[A-Za-zÀ-ÿ' \-]+"
                                     data-msg-required="Le nom est obligatoire."
                                     data-msg-minlength="Le nom doit contenir au moins 3 caractères."
                                     data-msg-pattern="Lettres, espaces, tirets et apostrophes uniquement."
                                     valid-message="Nom valide." />
                        </div>
                        <div class="col-md-6">
                            <x-input name="email" type="email" label="Email" icon="mail" :value="$user->email" required
                                     maxlength="255" hint="Cet email sert d'identifiant de connexion."
                                     data-msg-required="L'email est obligatoire."
                                     data-msg-type="L'adresse email n'est pas valide."
                                     valid-message="Cet email semble correct." />
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-transparent mt-auto">
                    <div class="btn-list justify-content-end">
                        <a href="{{ route('admin.profil') }}" class="btn">Annuler</a>
                        <button type="button" class="btn btn-primary"
                                data-bs-toggle="modal" data-bs-target="#modal-confirm"
                                data-confirm-type="primary"
                                data-confirm-title="Enregistrer vos informations ?"
                                data-confirm-message="Si vous changez d'email, utilisez le nouveau pour vous connecter."
                                data-confirm-button="Oui, enregistrer">
                            Enregistrer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection