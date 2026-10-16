<footer class="py-5 mt-0">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-5">
                <h5 class="text-white d-flex align-items-center gap-2">
                    <img src="{{ asset('favicon.svg') }}" alt="" width="26" height="26"> AquaSecure
                </h5>
                <p class="small mb-0">
                    Plateforme citoyenne de suivi des réseaux d'eau potable : signalement des incidents
                    et transparence sur le financement des projets.
                </p>
            </div>
            <div class="col-md-3">
                <h6 class="text-white">Liens</h6>
                <ul class="list-unstyled small">
                    <li><a href="{{ route('home') }}">Accueil</a></li>
                    @auth
                        <li><a href="{{ route('qualite.index') }}">Qualité de l'eau</a></li>
                    @endauth
@auth
    @if (auth()->user()->isCitoyen())
        <li><a href="{{ route('signalement.create') }}">Signaler un incident</a></li>
    @endif
@endauth                    <li><a href="{{ route('login') }}">Espace gestionnaires</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="text-white">Urgence</h6>
                <p class="small mb-0">En cas de contamination suspectée, ne consommez pas l'eau et contactez votre régie des eaux.</p>
            </div>
        </div>
        <hr class="border-secondary">
        <p class="small mb-0 text-center">© {{ date('Y') }} AquaSecure</p>
    </div>
</footer>
