@extends('layouts.admin')

@section('title', 'Importer des réseaux')
@section('pretitle', 'Infrastructure')

@section('actions')
    <a href="{{ route('admin.reseaux.import.modele') }}" class="btn">
        <i class="ti ti-download me-1"></i> Télécharger le fichier modèle
    </a>
@endsection

@section('content')
    @if (session('erreursImport'))
        <div class="alert alert-danger">
            <h4 class="alert-title"><i class="ti ti-alert-circle me-1"></i> Rien n'a été importé : corrigez ces lignes puis renvoyez le fichier.</h4>
            <ul class="mb-0 mt-2">
                @foreach (array_slice(session('erreursImport'), 0, 50) as $erreur)
                    <li>{{ $erreur }}</li>
                @endforeach
            </ul>
            @if (count(session('erreursImport')) > 50)
                <div class="mt-2">… et {{ count(session('erreursImport')) - 50 }} autre(s) erreur(s).</div>
            @endif
        </div>
    @endif

    <div class="row row-cards">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('admin.reseaux.import.store') }}" enctype="multipart/form-data" class="card" data-validate>
                @csrf
                <div class="card-body">
                    <x-input name="fichier" type="file" label="Fichier CSV" icon="file-spreadsheet" required
                             accept=".csv,text/csv"
                             hint="Excel : Fichier → Enregistrer sous → CSV (séparateur point-virgule). 2 Mo et 1 000 lignes au plus."
                             data-msg-required="Choisissez un fichier CSV."
                             valid-message="Fichier sélectionné." />
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('admin.reseaux.index') }}" class="btn btn-link">Annuler</a>
                    <button type="button" class="btn btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modal-confirm"
                            data-confirm-type="success"
                            data-confirm-title="Importer ce fichier ?"
                            data-confirm-message="Chaque ligne sera vérifiée. Si une seule est invalide, rien ne sera importé."
                            data-confirm-button="Oui, importer">
                        <i class="ti ti-upload me-1"></i> Importer
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Format attendu</h3></div>
                <div class="card-body small">
                    <p>Une ligne d'en-tête, puis un élément par ligne :</p>
                    <table class="table table-sm">
                        <tr><td class="font-monospace">code</td><td>Obligatoire, unique (ex : CAN-TUN-010)</td></tr>
                        <tr><td class="font-monospace">zone</td><td>Nom d'une zone existante</td></tr>
                        <tr><td class="font-monospace">commune</td><td>Commune de cette zone</td></tr>
                        <tr><td class="font-monospace">type</td><td>{{ implode(', ', \App\Models\ReseauEau::TYPES) }}</td></tr>
                        <tr><td class="font-monospace">materiau</td><td>{{ implode(', ', \App\Models\ReseauEau::MATERIAUX) }}</td></tr>
                        <tr><td class="font-monospace">date_mise_en_service</td><td>2004-06-01 ou 01/06/2004</td></tr>
                        <tr><td class="font-monospace">etat</td><td>{{ implode(', ', \App\Models\ReseauEau::ETATS) }}</td></tr>
                        <tr><td class="font-monospace">latitude, longitude</td><td>Facultatives (ex : 36,8001)</td></tr>
                    </table>
                    <p class="text-secondary mb-0">Majuscules et accents sont acceptés : « Réservoir » et « reservoir » donnent le même résultat.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
