<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin d'analyse — {{ $analyse->point->code }} — {{ $analyse->date_prelevement->format('d/m/Y') }}</title>
    @include('admin.risques._pdf-style')
    <style>
        .infos td { border: none; padding: 2px 6px 2px 0; }
        .infos .libelle { color: #667382; width: 30%; }
        .verdict { margin-top: 12px; padding: 8px 10px; border-radius: 4px; font-size: 11px; }
        .verdict ul { margin: 4px 0 0 16px; padding: 0; }
        .hors-norme { color: #9b1c1c; font-weight: bold; }
    </style>
</head>
<body>
@php
    $point = $analyse->point;
    $horsNorme = $analyse->parametresHorsNorme();
@endphp
    <div class="pied">AquaSecure · Bulletin d'analyse n° {{ $analyse->id }} · {{ $point->code }} · généré le {{ $genereLe->format('d/m/Y à H:i') }}</div>

    <h1>Bulletin d'analyse de l'eau</h1>
    <div class="sous-titre">
        Analyse n° {{ $analyse->id }} · prélevée le {{ $analyse->date_prelevement->format('d/m/Y') }} ·
        résultat <span class="badge {{ $analyse->couleurConformite() }}">{{ $analyse->libelleConformite() }}</span>
    </div>

    <h2>Prélèvement</h2>
    <table class="infos">
        <tr><td class="libelle">Point de prélèvement</td><td><span class="mono">{{ $point->code }}</span> — {{ $point->nom }}</td></tr>
        <tr><td class="libelle">Type de point</td><td>{{ $point->libelleType() }}</td></tr>
        <tr><td class="libelle">Adresse</td><td>{{ $point->adresse }}</td></tr>
        <tr><td class="libelle">Réseau</td><td><span class="mono">{{ $point->reseau->code }}</span> ({{ $point->reseau->libelleType() }})</td></tr>
        <tr><td class="libelle">Zone</td><td>{{ $point->reseau->zone->libelle() }}</td></tr>
        <tr><td class="libelle">Laboratoire</td><td>{{ $analyse->laboratoire }}</td></tr>
    </table>

    <h2>Résultats</h2>
    <table>
        <tr>
            <th>Paramètre</th><th class="droite">Valeur mesurée</th><th>Norme (OMS / NT 09.14)</th><th>Résultat</th>
        </tr>
        @foreach (\App\Models\AnalyseQualite::PARAMETRES as $cle => $infos)
            @php $hors = isset($horsNorme[$cle]); @endphp
            <tr>
                <td>{{ $infos['libelle'] }}</td>
                <td class="droite {{ $hors ? 'hors-norme' : '' }}">{{ \App\Models\AnalyseQualite::formater($cle, $analyse->{$cle}) }}</td>
                <td class="muet">{{ \App\Models\AnalyseQualite::norme($cle) }}</td>
                <td><span class="badge {{ $hors ? 'red' : 'green' }}">{{ $hors ? 'Hors norme' : 'Conforme' }}</span></td>
            </tr>
        @endforeach
    </table>

    <div class="verdict {{ $analyse->couleurConformite() }}">
        @if ($analyse->conforme)
            <strong>Eau conforme</strong> : les 5 paramètres respectent les seuils de potabilité.
        @else
            <strong>Eau non conforme</strong> : {{ count($horsNorme) }} paramètre{{ count($horsNorme) > 1 ? 's' : '' }} hors norme.
            <ul>
                @foreach ($horsNorme as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <p class="muet" style="margin-top: 14px">
        Conformité calculée automatiquement par AquaSecure à partir des seuils : pH entre 6,5 et 8,5 ;
        chlore résiduel entre 0,1 et 1 mg/L ; turbidité ≤ 5 NTU ; nitrates ≤ 50 mg/L ; aucune bactérie coliforme.
    </p>
</body>
</html>
