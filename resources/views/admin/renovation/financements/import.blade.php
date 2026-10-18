@extends('layouts.admin')

@section('title', 'Importer des financements')
@section('pretitle', 'Rénovation · Financements')

@section('actions')
    <a href="{{ route('admin.financements.import.modele') }}" class="btn"><i class="ti ti-download me-1"></i> Fichier modèle (Excel)</a>
@endsection

@section('content')
    @include('admin.renovation._nav')

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold"><i class="ti ti-alert-circle me-1"></i>{{ $errors->first() }}</div>
            @if (session('erreurs_import'))
                <ul class="mb-0 mt-2 small">
                    @foreach (array_slice(session('erreurs_import'), 0, 30) as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                    @if (count(session('erreurs_import')) > 30)
                        <li>… et {{ count(session('erreurs_import')) - 30 }} autre(s).</li>
                    @endif
                </ul>
            @endif
        </div>
    @endif

    <div class="row row-cards">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('admin.financements.import.store') }}" enctype="multipart/form-data" class="card">
                @csrf
                <div class="card-body">
                    <label class="form-label required" for="fichier">Fichier CSV (Excel)</label>
                    <input type="file" name="fichier" id="fichier" accept=".csv,text/csv" class="form-control mb-3" required>
                    <div class="small text-secondary">
                        Dans Excel : <strong>Fichier › Enregistrer sous › CSV UTF-8 (délimité par des points-virgules)</strong>.
                        500 lignes au plus. <strong>Tout ou rien</strong> : si une seule ligne est invalide, aucun financement n'est importé
                        et la liste des erreurs s'affiche.
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('admin.financements.index') }}" class="btn btn-link">Annuler</a>
                    <button type="button" class="btn btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modal-confirm" data-confirm-type="success"
                            data-confirm-title="Importer ce fichier ?" data-confirm-message="Chaque ligne sera contrôlée ; les financements ne seront ajoutés que si tout le fichier est valide."
                            data-confirm-button="Oui, importer">
                        <i class="ti ti-upload me-1"></i> Importer
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-table me-1"></i> Colonnes attendues</h3></div>
                <table class="table table-sm card-table">
                    <tbody>
                        <tr><td class="font-monospace">projet_id</td><td>Numéro du projet (visible dans son adresse : /admin/projets/<strong>12</strong>)</td></tr>
                        <tr><td class="font-monospace">projet</td><td>Titre, facultatif (aide à la relecture, non utilisé)</td></tr>
                        <tr><td class="font-monospace">source</td><td>{{ implode(', ', array_keys($sources)) }} — ou le libellé : {{ implode(', ', $sources) }}</td></tr>
                        <tr><td class="font-monospace">montant</td><td>En dinars, ex : <code>150000</code>, <code>150 000,50</code></td></tr>
                    </tbody>
                </table>
                <div class="card-footer small text-secondary">
                    Mêmes contrôles que le formulaire : projet existant, montant strictement positif, 2 décimales au plus,
                    projet terminé refusé (sauf administrateur), total plafonné au coût déclaré du projet (en tenant compte des lignes du fichier).
                </div>
            </div>
        </div>
    </div>
@endsection
