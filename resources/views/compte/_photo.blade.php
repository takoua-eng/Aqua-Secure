{{--
    Champ "Photo de profil" (partagé : back office Tabler et site public Bootstrap).
    À placer dans un formulaire enctype="multipart/form-data" ; l'aperçu s'affiche dans #apercu-photo.
--}}
<label class="form-label" for="champ-photo">Photo de profil</label>
<input type="file" name="photo" id="champ-photo" accept="image/jpeg,image/png,image/webp"
       class="form-control {{ $errors->has('photo') ? 'is-invalid' : '' }}">
<div class="invalid-feedback {{ $errors->has('photo') ? 'd-block' : '' }}" id="erreur-photo">{{ $errors->first('photo') }}</div>
<small class="form-text text-secondary d-block">JPG, PNG ou WEBP, 2 Mo maximum, au moins 100 × 100 pixels.</small>

@if ($user->photo)
    <label class="form-check mt-2">
        <input type="checkbox" name="supprimer_photo" value="1" class="form-check-input">
        <span class="form-check-label">Supprimer la photo (revenir aux initiales)</span>
    </label>
@endif

@push('scripts')
<script>
    // Contrôle de la photo dès qu'elle est choisie + aperçu avant l'envoi
    document.getElementById('champ-photo')?.addEventListener('change', function () {
        const fichier = this.files[0];
        const erreur = document.getElementById('erreur-photo');
        let message = '';

        if (fichier && !['image/jpeg', 'image/png', 'image/webp'].includes(fichier.type)) {
            message = 'La photo doit être au format JPG, PNG ou WEBP.';
        } else if (fichier && fichier.size > 2 * 1024 * 1024) {
            message = 'La photo ne doit pas dépasser 2 Mo (taille actuelle : ' + (fichier.size / 1024 / 1024).toFixed(1) + ' Mo).';
        }

        this.classList.toggle('is-invalid', message !== '');
        erreur.textContent = message;
        erreur.classList.toggle('d-block', message !== '');

        if (message) {
            this.value = '';   // fichier refusé : il ne sera pas envoyé
            return;
        }
        if (fichier) {
            document.getElementById('apercu-photo').innerHTML =
                `<img src="${URL.createObjectURL(fichier)}" alt="Aperçu" class="rounded-circle"
                      style="width: 96px; height: 96px; object-fit: cover">`;
        }
    });
</script>
@endpush