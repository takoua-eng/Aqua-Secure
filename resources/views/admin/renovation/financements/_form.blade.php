@if ($errors->any())
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle me-1"></i> Veuillez corriger les champs en rouge.
    </div>
@endif

@if (empty($projets))
    <div class="alert alert-warning">
        <i class="ti ti-info-circle me-1"></i> Aucun projet ne peut recevoir de financement.
        <a href="{{ route('admin.projets.create') }}" class="alert-link">Créez d'abord un projet</a>.
    </div>
@endif

<div class="row">
    <div class="col-md-12">
        <x-select name="projet_id" label="Projet financé" icon="building" :options="$projets"
                  :value="$financement->projet_id" required placeholder="— Choisir un projet —"
                  data-msg-required="Choisissez le projet financé."
                  valid-message="Projet sélectionné." />
        <div class="small text-secondary mt-n2 mb-3" id="aide-reste"></div>
    </div>
    <div class="col-md-6">
        <x-select name="source" label="Source" icon="building-bank" :options="\App\Models\Financement::SOURCES"
                  :value="$financement->source" required placeholder="— Choisir une source —"
                  data-msg-required="Choisissez la source du financement."
                  valid-message="Source sélectionnée." />
    </div>
    <div class="col-md-6">
        <x-input name="montant" label="Montant (DT)" icon="coin" required
                 :value="$financement->montant !== null ? (float) $financement->montant : null"
                 inputmode="decimal" placeholder="Ex : 150000 ou 150 000,50"
                 pattern="[0-9 ]+([.,][0-9]{1,2})?"
                 data-msg-required="Le montant est obligatoire."
                 data-msg-pattern="Montant positif en dinars, 2 chiffres après la virgule au plus."
                 valid-message="Montant valide." />
    </div>
</div>

@push('scripts')
<script>
    (function () {
        // Aide à la saisie : reste à financer selon le coût déclaré (devis) du projet choisi
        const restes = @json($restes);
        @php $actuelJs = $financement->exists ? ['id' => $financement->projet_id, 'montant' => (float) $financement->montant] : null; @endphp
        const actuel = @json($actuelJs);
        const projet = document.getElementById('champ-projet_id');
        const aide = document.getElementById('aide-reste');
        const format = v => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(v) + ' DT';

        function majAide() {
            const r = restes[projet.value];
            if (!r) { aide.textContent = ''; return; }
            const finance = r.finance - (actuel && String(actuel.id) === projet.value ? actuel.montant : 0);
            aide.innerHTML = r.cout === null
                ? '<i class="ti ti-info-circle me-1"></i>Déjà financé : <strong>' + format(finance) + '</strong>. Pas de coût déclaré : aucun plafond.'
                : '<i class="ti ti-info-circle me-1"></i>Déjà financé : <strong>' + format(finance) + '</strong> sur un coût déclaré de '
                    + format(r.cout) + ' — reste <strong>' + format(Math.max(0, r.cout - finance)) + '</strong>.';
        }
        projet?.addEventListener('change', majAide);
        majAide();
    })();
</script>
@endpush
