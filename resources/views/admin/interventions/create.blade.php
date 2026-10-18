@extends('layouts.admin')

@section('title', 'Nouvelle intervention')
@section('pretitle', 'Exploitation · Incident n° ' . $incident->id)

@section('actions')
    <a href="{{ route('admin.incidents.show', $incident) }}" class="btn btn-link">
        <i class="ti ti-arrow-left me-1"></i> Retour à l'incident
    </a>
@endsection

@section('content')
    @include('admin.incidents._navigation')
    @include('admin.interventions._incident')

    @if ($incident->estResolu())
        <div class="alert alert-info">
            <i class="ti ti-info-circle me-1"></i>
            Cet incident est résolu. Une nouvelle intervention le fera repasser « En traitement » (nouvelle réparation nécessaire).
        </div>
    @endif

    @php
        $statutChoisi = old('statut', $intervention->statut);
        $aujourdhui = today()->format('Y-m-d');
        $signalement = $incident->date_signalement->format('Y-m-d');
    @endphp

    <form method="POST" action="{{ route('admin.incidents.interventions.store', $incident) }}" class="card" data-validate>
        @csrf

        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger"><i class="ti ti-alert-circle me-1"></i> Veuillez corriger les champs en rouge.</div>
            @endif

            <div class="mb-3">
                <label class="form-label required">Statut</label>
                <div class="form-selectgroup">
                    @foreach (\App\Models\Intervention::STATUTS as $valeur => $libelle)
                        <label class="form-selectgroup-item">
                            <input type="radio" name="statut" value="{{ $valeur }}" class="form-selectgroup-input" @checked($statutChoisi === $valeur)>
                            <span class="form-selectgroup-label">
                                <i class="ti ti-{{ ['planifiee' => 'calendar-event', 'en_cours' => 'tool', 'terminee' => 'circle-check'][$valeur] }} me-1"></i>{{ $libelle }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <small class="form-hint d-block mt-1">
                    « En cours » ou « Terminée » : pour enregistrer une intervention déjà réalisée sur le terrain.
                </small>
                @error('statut') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="row">
                <div class="col-md-6">
                    <x-input name="date_intervention" type="date" label="Date d'intervention" icon="calendar"
                             :value="optional($intervention->date_intervention)->format('Y-m-d')" required
                             min="{{ max($aujourdhui, $signalement) }}"
                             data-msg-required="La date d'intervention est obligatoire."
                             valid-message="Date valide." />
                </div>
                <div class="col-md-6">
                    <div class="alert alert-secondary mb-3 small" id="aide-date">
                        <i class="ti ti-info-circle me-1"></i>
                        <span data-aide="planifiee">Planifiée : date prévue, aujourd'hui ou plus tard.</span>
                        <span data-aide="en_cours" class="d-none">En cours : date de début, entre le signalement ({{ $incident->date_signalement->format('d/m/Y') }}) et aujourd'hui.</span>
                        <span data-aide="terminee" class="d-none">Terminée : date d'exécution, entre le signalement ({{ $incident->date_signalement->format('d/m/Y') }}) et aujourd'hui.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('admin.incidents.show', $incident) }}" class="btn btn-link">Annuler</a>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modal-confirm"
                    data-confirm-type="success"
                    data-confirm-title="Enregistrer cette intervention ?"
                    data-confirm-message="L'intervention sera rattachée à l'incident n° {{ $incident->id }}. Son statut sera enregistré dans l'historique."
                    data-confirm-button="Oui, enregistrer">
                <i class="ti ti-device-floppy me-1"></i> Enregistrer
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        // Bornes de la date selon le statut (règles T2, T3, T4) ; le serveur vérifie de toute façon
        (function () {
            const date = document.querySelector('[name="date_intervention"]');
            const aujourdhui = @json($aujourdhui);
            const signalement = @json($signalement);

            function appliquer() {
                const statut = document.querySelector('[name="statut"]:checked')?.value || 'planifiee';
                if (statut === 'planifiee') {
                    date.min = aujourdhui > signalement ? aujourdhui : signalement;
                    date.removeAttribute('max');
                    date.dataset.msgMin = "Une intervention planifiée doit avoir une date à venir.";
                } else {
                    date.min = signalement;
                    date.max = aujourdhui;
                    date.dataset.msgMin = "La date ne peut pas précéder le signalement de l'incident.";
                    date.dataset.msgMax = "Une intervention en cours ou terminée ne peut pas être dans le futur.";
                }
                document.querySelectorAll('[data-aide]').forEach(el => el.classList.toggle('d-none', el.dataset.aide !== statut));
            }

            document.querySelectorAll('[name="statut"]').forEach(r => r.addEventListener('change', appliquer));
            appliquer();
        })();
    </script>
@endpush
