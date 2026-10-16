<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport de zone — {{ $zone->nom }}</title>
    @include('admin.risques._pdf-style')
    <style>
        .rapport h2 { border: none; margin: 12px 0 4px; font-size: 11.5px; color: #0b3d91; }
        .rapport ul, .rapport ol { margin: 0 0 6px 16px; padding: 0; }
        .rapport p { margin: 0 0 6px; }
    </style>
</head>
<body>
    <div class="pied">AquaSecure · Rapport de zone · {{ $zone->nom }} · {{ $genereLe->format('d/m/Y à H:i') }}</div>

    <h1>Rapport de zone : {{ $zone->nom }}</h1>
    <div class="sous-titre">
        {{ $zone->commune }} · {{ number_format($zone->population, 0, ',', ' ') }} habitants ·
        {{ $reseaux->count() }} élément(s) ·
        risque <span class="badge {{ $risque['couleur'] }}">{{ $risque['score'] }}/100 · {{ $risque['libelle'] }}</span>
    </div>

    <h2>Éléments du réseau</h2>
    <table>
        <tr>
            <th>Code</th><th>Type</th><th>Matériau · âge</th><th>État</th>
            <th class="droite">Score</th><th class="droite">Panne 5 ans</th><th class="droite">Vie restante</th>
        </tr>
        @forelse ($reseaux as ['reseau' => $reseau, 'risque' => $r, 'prediction' => $p])
            <tr>
                <td class="mono">{{ $reseau->code }}</td>
                <td>{{ $reseau->libelleType() }}</td>
                <td>{{ $reseau->libelleMateriau() }} · {{ $reseau->age() }} ans</td>
                <td><span class="badge {{ $reseau->couleurEtat() }}">{{ $reseau->libelleEtat() }}</span></td>
                <td class="droite"><span class="badge {{ $r['couleur'] }}">{{ $r['score'] }}</span></td>
                <td class="droite">{{ $p['horsService'] ? '—' : number_format($p['cinq_ans'], 1, ',', ' ') . ' %' }}</td>
                <td class="droite">{{ $p['horsService'] ? 'hors service' : '≈ ' . $p['vie_restante'] . ' ans' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muet">Aucun élément enregistré dans cette zone.</td></tr>
        @endforelse
    </table>

    <h2>Analyse et recommandations</h2>
    <div class="rapport">{!! $rapport['html'] !!}</div>
    <p class="muet" style="margin-top: 8px">
        @if (! empty($rapport['demo']))
            Mode démo : rapport rédigé automatiquement à partir du score de risque, sans IA.
        @else
            Rapport généré par IA ({{ $rapport['modele'] }}) le {{ $rapport['genereLe'] }} — à vérifier par un gestionnaire.
        @endif
    </p>
</body>
</html>
