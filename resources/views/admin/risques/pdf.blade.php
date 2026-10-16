<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Analyse des risques — AquaSecure</title>
    @include('admin.risques._pdf-style')
</head>
<body>
    <div class="pied">AquaSecure · Analyse des risques · générée le {{ $genereLe->format('d/m/Y à H:i') }}</div>

    <h1>Analyse des risques du réseau d'eau</h1>
    <div class="sous-titre">{{ $classement->count() }} éléments analysés · document généré le {{ $genereLe->format('d/m/Y à H:i') }}</div>

    <table class="tuiles">
        <tr>
            @foreach (\App\Services\ScoreRisque::NIVEAUX as [$cle, $libelle, $couleur])
                <td>
                    <div class="valeur">{{ $parNiveau[$cle] ?? 0 }}</div>
                    <span class="badge {{ $couleur }}">Risque {{ mb_strtolower($libelle) }}</span>
                </td>
            @endforeach
        </tr>
    </table>

    <h2>Groupes de zones similaires (K-means)</h2>
    @if (empty($groupes))
        <p class="muet">Aucune zone ne contient encore de réseau.</p>
    @else
        <table>
            <tr>
                <th>Groupe</th><th>Zones</th>
                <th class="droite">Vétusté</th><th class="droite">Mauvais état</th>
                <th class="droite">Risque moyen</th><th class="droite">Population moy.</th>
            </tr>
            @foreach ($groupes as $groupe)
                <tr>
                    <td><span class="badge {{ $groupe['couleur'] }}">{{ $groupe['libelle'] }}</span></td>
                    <td>{{ $groupe['zones']->map(fn ($z) => "{$z->nom} ({$z->commune})")->join(', ') }}</td>
                    <td class="droite">{{ round($groupe['moyenne']['vetuste'] * 100) }} %</td>
                    <td class="droite">{{ round($groupe['moyenne']['degradee'] * 100) }} %</td>
                    <td class="droite">{{ round($groupe['moyenne']['risque']) }} / 100</td>
                    <td class="droite">{{ number_format($groupe['moyenne']['population'], 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </table>
        @if ($sansReseau->isNotEmpty())
            <p class="muet">Non classées (aucun réseau) : {{ $sansReseau->pluck('nom')->join(', ') }}.</p>
        @endif
    @endif

    <h2>Classement des éléments par score de risque</h2>
    <table>
        <tr>
            <th>#</th><th>Code</th><th>Zone</th><th>Matériau · âge</th><th>État</th>
            <th class="droite">Score</th><th class="droite">Panne 1 an</th><th class="droite">Panne 5 ans</th><th class="droite">Vie restante</th>
        </tr>
        @foreach ($classement as $rang => ['reseau' => $reseau, 'risque' => $risque, 'prediction' => $prediction])
            <tr>
                <td class="muet">{{ $rang + 1 }}</td>
                <td class="mono">{{ $reseau->code }}<div class="muet">{{ $reseau->libelleType() }}</div></td>
                <td>{{ $reseau->zone->nom }}<div class="muet">{{ $reseau->zone->commune }}</div></td>
                <td>{{ $reseau->libelleMateriau() }} · {{ $reseau->age() }} ans</td>
                <td><span class="badge {{ $reseau->couleurEtat() }}">{{ $reseau->libelleEtat() }}</span></td>
                <td class="droite"><span class="badge {{ $risque['couleur'] }}">{{ $risque['score'] }} · {{ $risque['libelle'] }}</span></td>
                @if ($prediction['horsService'])
                    <td class="droite muet" colspan="3">déjà hors service</td>
                @else
                    <td class="droite">{{ number_format($prediction['un_an'], 1, ',', ' ') }} %</td>
                    <td class="droite">{{ number_format($prediction['cinq_ans'], 1, ',', ' ') }} %</td>
                    <td class="droite">≈ {{ $prediction['vie_restante'] }} ans</td>
                @endif
            </tr>
        @endforeach
    </table>

    <p class="muet" style="margin-top: 10px">
        Score sur 100 : vétusté (35), état (30), population touchée (15), durée du problème (10), matériau (10).
        Probabilités de panne : loi de Weibull selon le matériau et l'âge (risque ×3 pour un élément dégradé) — estimations à confirmer sur le terrain.
    </p>
</body>
</html>
