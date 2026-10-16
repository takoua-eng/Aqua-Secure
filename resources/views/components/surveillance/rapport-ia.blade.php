{{--
    Composant Blade réutilisable : bouton qui demande un texte à l'IA (en arrière-plan) et l'affiche.
    Utilisé pour le diagnostic d'un capteur ET pour l'analyse globale de tous les capteurs.

    <x-surveillance.rapport-ia :url="route(...)" bouton="Générer le diagnostic" attente="L'IA analyse…">
        Texte affiché avant la première demande.
    </x-surveillance.rapport-ia>
--}}
@props([
    'url',
    'bouton'  => "Lancer l'analyse",
    'attente' => "L'IA analyse les données… (jusqu'à une minute)",
])

<div {{ $attributes->merge(['class' => 'rapport-ia']) }} data-url="{{ $url }}" data-attente="{{ $attente }}">
    <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-sm btn-primary rapport-ia-bouton">
            <i class="ti ti-sparkles me-1"></i> {{ $bouton }}
        </button>
    </div>
    <div class="rapport-ia-resultat text-secondary">{{ $slot }}</div>
</div>

{{-- @once : le script n'est ajouté qu'une fois, même si le composant est utilisé plusieurs fois --}}
@once
    @push('scripts')
        <script>
            document.addEventListener('click', async function (e) {
                const bouton = e.target.closest('.rapport-ia-bouton');
                if (!bouton) return;

                const bloc = bouton.closest('.rapport-ia');
                const cible = bloc.querySelector('.rapport-ia-resultat');
                const echapper = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

                bouton.disabled = true;
                cible.innerHTML = `<div class="d-flex align-items-center gap-2 py-3"><span class="spinner-border spinner-border-sm"></span> ${echapper(bloc.dataset.attente)}</div>`;

                try {
                    const reponse = await fetch(bloc.dataset.url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    });
                    const d = await reponse.json().catch(() => ({}));

                    if (!reponse.ok) {
                        const message = d.erreur || (reponse.status === 429 ? 'Trop de demandes : réessayez dans une minute.' : "L'analyse a échoué.");
                        cible.innerHTML = `<div class="alert alert-warning mb-0">${echapper(message)}</div>`;
                        return;
                    }

                    // Le HTML vient du serveur (Markdown converti, HTML brut échappé)
                    const origine = d.demo
                        ? `<i class="ti ti-info-circle me-1"></i>Mode démo : texte rédigé automatiquement par des règles (aucune clé API configurée). Le ${echapper(d.genereLe)}.`
                        : `<i class="ti ti-sparkles me-1"></i>Généré par IA (${echapper(d.modele)}) le ${echapper(d.genereLe)}. À vérifier par un gestionnaire.`;
                    cible.innerHTML = `<div class="text-body">${d.html}</div><div class="small text-secondary mt-2">${origine}</div>`;
                } catch (err) {
                    cible.innerHTML = '<div class="alert alert-warning mb-0">Connexion au serveur impossible.</div>';
                } finally {
                    bouton.disabled = false;
                }
            });
        </script>
    @endpush
@endonce