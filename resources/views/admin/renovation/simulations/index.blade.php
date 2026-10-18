@extends('layouts.admin')

@section('title', 'Simulations enregistrées')
@section('pretitle', 'Rénovation · What If? Engine')
@section('subtitle', 'Historique des scénarios, comparaison et cycle de validation.')

@section('actions')
    <a href="{{ route('admin.whatif.index') }}" class="btn btn-primary">
        <i class="ti ti-adjustments-alt me-1"></i> Nouvelle simulation
    </a>
@endsection

@section('content')
    @include('admin.renovation._nav')

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if (auth()->user()->isAdmin() && $enAttente)
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="ti ti-clock-exclamation"></i>
            <span><strong>{{ $enAttente }} scénario(s)</strong> attendent votre validation.</span>
            <a href="{{ route('admin.simulations.index', ['statut' => 'soumise']) }}" class="btn btn-sm btn-warning ms-auto">Voir</a>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.simulations.comparer') }}" id="form-comparer">
        <div class="card">
            <div class="card-body border-bottom d-flex flex-wrap gap-2 align-items-center">
                <div class="btn-group" role="group">
                    <a href="{{ route('admin.simulations.index') }}" class="btn btn-sm {{ request('statut') ? '' : 'active' }}">Toutes</a>
                    @foreach (\App\Models\SimulationFinancement::STATUTS as $valeur => $libelle)
                        <a href="{{ route('admin.simulations.index', ['statut' => $valeur]) }}" class="btn btn-sm {{ request('statut') === $valeur ? 'active' : '' }}">{{ $libelle }}</a>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-sm btn-outline-primary ms-auto" id="bouton-comparer" disabled>
                    <i class="ti ti-columns-2 me-1"></i> Comparer la sélection (<span id="nb-selection">0</span>)
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th class="w-1" title="Cocher 2 ou 3 simulations à comparer"><i class="ti ti-columns-2"></i></th>
                            <th>Simulation</th>
                            <th class="text-end">Budget <x-renovation.origine type="hypothese" /></th>
                            <th>Stratégie</th>
                            <th>Résultat</th>
                            <th>Statut</th>
                            <th>Auteur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($simulations as $s)
                            @php $k = $s->scenarioRetenu()['kpi'] ?? null; @endphp
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input choix-comparer" name="ids[]" id="comparer-{{ $s->id }}" value="{{ $s->id }}">
                                    <label class="visually-hidden" for="comparer-{{ $s->id }}">Comparer « {{ $s->nom }} »</label>
                                </td>
                                <td>
                                    <a href="{{ route('admin.simulations.show', $s) }}" class="fw-medium">{{ $s->nom }}</a>
                                    <div class="small text-secondary">{{ $s->created_at->format('d/m/Y à H:i') }}</div>
                                </td>
                                <td class="text-end"><x-renovation.montant :valeur="$s->budget" :decimales="0" /></td>
                                <td class="small">{{ $s->libelleStrategie() }}</td>
                                <td class="small">
                                    @if ($k)
                                        {{ $k['nb_complets'] }}/{{ $k['nb_candidats'] }} achevé(s) ·
                                        priorité {{ number_format($k['priorite_couverte_pct'], 1, ',', ' ') }} %
                                    @endif
                                </td>
                                <td><span class="badge bg-{{ $s->couleurStatut() }}-lt">{{ $s->libelleStatut() }}</span></td>
                                <td class="small">{{ $s->auteur?->nom ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty">
                                        <div class="empty-icon"><i class="ti ti-history" style="font-size: 3rem"></i></div>
                                        <p class="empty-title">Aucune simulation enregistrée</p>
                                        <p class="empty-subtitle text-secondary">Lancez le What If? Engine puis enregistrez un scénario pour le retrouver ici.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-center mt-4">
        {{ $simulations->links() }}
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const cases = document.querySelectorAll('.choix-comparer');
        const bouton = document.getElementById('bouton-comparer');
        cases.forEach(c => c.addEventListener('change', function () {
            const n = document.querySelectorAll('.choix-comparer:checked').length;
            if (n > 3) { this.checked = false; return; }
            document.getElementById('nb-selection').textContent = Math.min(n, 3);
            bouton.disabled = n < 2;
        }));
    })();
</script>
@endpush
