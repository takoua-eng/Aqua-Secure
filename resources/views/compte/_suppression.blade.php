{{-- Suppression définitive du compte — partagé : back office et site public --}}
<p class="text-secondary">
    La suppression est <strong>définitive</strong> : votre compte et votre photo seront effacés
    et vous serez déconnecté. Saisissez votre mot de passe pour confirmer.
</p>

<form method="POST" action="{{ route('compte.destroy') }}" class="row g-2 align-items-start" style="max-width: 520px"
      onsubmit="return confirm('Supprimer définitivement votre compte ? Cette action est irréversible.')">
    @csrf
    @method('DELETE')
    <div class="col">
        <input type="password" name="mot_de_passe" required autocomplete="current-password"
               placeholder="Votre mot de passe" aria-label="Mot de passe"
               class="form-control {{ $errors->suppression->has('mot_de_passe') ? 'is-invalid' : '' }}">
        <div class="invalid-feedback">{{ $errors->suppression->first('mot_de_passe') }}</div>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-danger">Supprimer mon compte</button>
    </div>
</form>