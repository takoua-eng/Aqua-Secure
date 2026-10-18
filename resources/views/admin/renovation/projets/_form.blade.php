@if ($errors->any())
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle me-1"></i> Veuillez corriger les champs en rouge.
    </div>
@endif

@if (empty($reseaux))
    <div class="alert alert-warning">
        <i class="ti ti-info-circle me-1"></i> Aucun réseau n'existe encore.
        <a href="{{ route('admin.reseaux.create') }}" class="alert-link">Créez d'abord un réseau</a>.
    </div>
@endif

<h3 class="card-title">Projet</h3>
<div class="row">
    <div class="col-md-7">
        <x-input name="titre" label="Titre" icon="writing" :value="$projet->titre" required
                 placeholder="Ex : Remplacement de la conduite en fonte de Bab Saadoun"
                 minlength="5" maxlength="150"
                 data-msg-required="Le titre est obligatoire."
                 data-msg-minlength="Le titre doit contenir au moins 5 caractères."
                 valid-message="Titre valide." />
    </div>
    <div class="col-md-5">
        <x-select name="statut" label="Statut" icon="progress" :options="\App\Models\ProjetRenovation::STATUTS"
                  :value="$projet->statut" required placeholder="— Choisir un statut —"
                  data-msg-required="Choisissez le statut."
                  valid-message="Statut sélectionné." />
    </div>
    <div class="col-md-7">
        <x-select name="reseau_id" label="Réseau concerné" icon="share" :options="$reseaux"
                  :value="$projet->reseau_id" required placeholder="— Choisir un réseau —"
                  data-msg-required="Choisissez le réseau concerné."
                  valid-message="Réseau sélectionné." />
    </div>
    <div class="col-md-5">
        <x-input name="cout_estime" label="Coût total déclaré (DT)" icon="file-certificate"
                 :value="$projet->cout_estime !== null ? (float) $projet->cout_estime : null"
                 inputmode="decimal" placeholder="Facultatif — montant du devis"
                 pattern="[0-9 ]+([.,][0-9]{1,2})?"
                 data-msg-pattern="Montant en dinars, 2 chiffres après la virgule au plus (ex : 450000 ou 450 000,50)."
                 hint="Sans devis, l'application utilise une estimation affichée comme telle."
                 valid-message="Montant valide." />
        <div class="small text-secondary mt-n2 mb-3" id="aide-estimation"></div>
    </div>
</div>

<h3 class="card-title mt-2">Information des citoyens</h3>
<div class="row">
    <div class="col-12">
        <div class="mb-3">
            <label class="form-label" for="champ-description">Description</label>
            <textarea name="description" id="champ-description" rows="3" maxlength="2000"
                      class="form-control @error('description') is-invalid @enderror"
                      placeholder="Travaux prévus, bénéfices attendus pour les habitants…">{{ old('description', $projet->description) }}</textarea>
            <div class="invalid-feedback {{ $errors->has('description') ? 'd-block' : '' }}" data-feedback-for="description">{{ $errors->first('description') }}</div>
            <small class="form-hint">Obligatoire pour publier le projet sur la page publique.</small>
        </div>
    </div>
    <div class="col-12">
        <label class="form-check form-switch">
            <input type="hidden" name="publie" value="0">
            <input class="form-check-input" type="checkbox" name="publie" value="1" @checked(old('publie', $projet->publie))>
            <span class="form-check-label">Publier ce projet sur la page « Rénovation » des citoyens</span>
        </label>
        <small class="form-hint">Les citoyens voient le titre, la description, le statut et les montants agrégés par source — jamais les simulations ni les alertes.</small>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        // Estimation du coût selon le réseau choisi (barème du module Analyse des risques), à titre indicatif
        const estimations = @json($estimations);
        const reseau = document.getElementById('champ-reseau_id');
        const aide = document.getElementById('aide-estimation');
        const format = v => new Intl.NumberFormat('fr-FR').format(v) + ' DT';

        function majAide() {
            const e = estimations[reseau.value];
            aide.innerHTML = e
                ? '<i class="ti ti-ruler-measure me-1"></i>Estimation indicative pour ce réseau : <strong>' + format(e) + '</strong> (barème, pas un devis).'
                : '';
        }
        reseau?.addEventListener('change', majAide);
        majAide();
    })();
</script>
@endpush
