@if ($errors->any())
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle me-1"></i> Veuillez corriger les champs en rouge.
    </div>
@endif
{{-- Photo de profil (facultative) : aperçu à gauche, champ à droite --}}
<div class="row align-items-center mb-3">
    <div class="col-auto">
        <span id="apercu-photo">
            @if ($user->exists)
                <x-avatar :user="$user" taille="xl" />
            @else
                <span class="avatar avatar-xl rounded-circle"><i class="ti ti-user" style="font-size: 2rem"></i></span>
            @endif
        </span>
    </div>
    <div class="col-md-6">
        @include('compte._photo')
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <x-input name="nom" label="Nom complet" icon="user" :value="$user->nom" required
                 placeholder="Ex : Salma Ben Ali"
                 minlength="3" maxlength="100" pattern="[A-Za-zÀ-ÿ' \-]+"
                 data-msg-required="Le nom est obligatoire."
                 data-msg-minlength="Le nom doit contenir au moins 3 caractères."
                 data-msg-pattern="Lettres, espaces, tirets et apostrophes uniquement."
                 valid-message="Nom valide." />
    </div>

    <div class="col-md-6">
        <x-input name="email" type="email" label="Email" icon="mail" :value="$user->email" required
                 placeholder="nom@exemple.com" maxlength="255"
                 data-msg-required="L'email est obligatoire."
                 data-msg-type="L'adresse email n'est pas valide (ex : nom@exemple.com)."
                 valid-message="Cet email semble correct." />
    </div>

    <div class="col-md-4">
        <x-select name="role" label="Rôle" icon="shield" :options="\App\Models\User::ROLES"
                  :value="$user->role ?? 'citoyen'" required
                  data-msg-required="Choisissez un rôle."
                  valid-message="Rôle sélectionné." />
    </div>

    <div class="col-md-4">
        <x-input name="password" type="password" label="Mot de passe" icon="lock"
                 :required="! $user->exists"
                 minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).+"
                 autocomplete="new-password"
                 :hint="$user->exists ? 'Laisser vide pour ne pas le changer.' : '8 caractères minimum, avec une lettre et un chiffre.'"
                 data-msg-required="Le mot de passe est obligatoire."
                 data-msg-minlength="Au moins 8 caractères."
                 data-msg-pattern="Il faut au moins une lettre et un chiffre."
                 valid-message="Mot de passe valide." />
    </div>

    <div class="col-md-4">
        <x-input name="password_confirmation" type="password" label="Confirmer le mot de passe" icon="lock-check"
                 :required="! $user->exists"
                 data-match="password"
                 autocomplete="new-password"
                 data-msg-required="Confirmez le mot de passe."
                 data-msg-match="Les deux mots de passe ne correspondent pas."
                 valid-message="Les mots de passe correspondent." />
    </div>
</div>