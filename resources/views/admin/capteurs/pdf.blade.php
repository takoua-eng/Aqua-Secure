<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport du capteur {{ $capteur->code }}</title>
    {{-- Styles PDF communs (fichier du binôme 1) --}}
    @include('admin.risques._pdf-style')
    <style>
        .rapport h2 { border: none; margin: 10px 0 4px; font-size: 11.5px; color: #0b3d91; }
        .rapport ul, .rapport ol { margin: 0 0 6px 16px; padding: 0; }
        .rapport p { margin: 0 0 6px; }
        .fiche td { border: none; padding: 2px 6px 2px 0; }
        .fiche .titre { color: #667382; width: 120px; }
    </style>
</head>
<body>
    <div class="pied">AquaSecure · Rapport du capteur {{ $capteur->code }} · {{ $genereLe->format('d/m/Y à H:i') }}</div>

    <h1>Rapport du capteur {{ $capteur->code }}</h1>
    <div class="sous-titre">
        {{ $capteur->libelleTypeMesure() }} · {{ $capteur->modele }} ·
        <span class="badge {{ $capteur->couleurStatut() }}">{{ $capteur->libelleStatut() }}</span>
    </div>

    <h2>Fiche</h2>
    <table class="fiche">
        <tr><td class="titre">Réseau</td><td>{{ $capteur->reseau->code }} — {{ $capteur->reseau->libelleType() }} ({{ $capteur->reseau->libelleEtat() }})</td></tr>
        <tr><td class="titre">Zone</td><td>{{ $capteur->reseau->zone->nom }} · {{ $capteur->reseau->zone->commune }}</td></tr>
        <tr><td class="titre">Seuils normaux</td><td>{{ $capteur->plageSeuils() }}</td></tr>
        <tr><td class="titre">Installé le</td><td>{{ $capteur->date_installation->format('d/m/Y') }}</td></tr>
    </table>

    <h2>Statistiques des {{ $resume['nombre'] }} derniers relevés</h2>
    @if ($resume['nombre'])
        <table class="tuiles">
            <tr>
                <td><div class="muet">Minimum</div><div class="valeur">{{ $capteur->avecUnite($resume['min']) }}</div></td>
                <td><div class="muet">Moyenne</div><div class="valeur">{{ $capteur->avecUnite($resume['moyenne']) }}</div></td>
                <td><div class="muet">Maximum</div><div class="valeur">{{ $capteur->avecUnite($resume['max']) }}</div></td>
                <td><div class="muet">Hors seuil</div><div class="valeur">{{ $resume['hors_seuil'] }}</div></td>
                <td><div class="muet">Anomalies IA</div><div class="valeur">{{ count($anomalies) }}</div></td>
            </tr>
        </table>

        @if ($graphique)
            {{-- Image SVG produite par App\Services\GraphiqueSvg (dompdf n'exécute pas JavaScript) --}}
            <div style="margin-top: 10px"><img src="{{ $graphique }}" style="width: 100%"></div>
            <div class="muet">Zone verte : plage normale · points rouges : hors seuil · points orange : anomalies IA · pointillés violets : prévision 24 h</div>
        @endif
    @else
        <p class="muet">Aucun relevé enregistré.</p>
    @endif

    <h2>Prévision (régression linéaire)</h2>
    @if ($prevision)
        <p>
            Tendance : <strong>{{ $prevision['tendance'] }}</strong>
            ({{ $prevision['pente_jour'] >= 0 ? '+' : '' }}{{ $capteur->avecUnite($prevision['pente_jour']) }} / jour) ·
            valeur attendue dans 24 h : <strong>{{ $capteur->avecUnite($prevision['valeur_24h']) }}</strong> ·
            confiance {{ $prevision['confiance'] }} (R² = {{ $prevision['r2'] }}).
        </p>
        <p>{{ \App\Services\AnalyseCapteur::phraseFranchissement($prevision['franchissement']) ?? 'Aucun franchissement de seuil prévu dans les 7 jours.' }}</p>
    @else
        <p class="muet">Il faut au moins 4 relevés pour calculer une tendance.</p>
    @endif

    <h2>Anomalies détectées par l'IA</h2>
    @if (count($anomalies))
        <table>
            <tr><th>Date</th><th class="droite">Valeur</th><th class="droite">Attendue</th><th class="droite">Score z</th></tr>
            @foreach ($derniers->whereIn('id', array_keys($anomalies))->reverse() as $r)
                <tr>
                    <td>{{ $r->date_releve->format('d/m/Y H:i') }}</td>
                    <td class="droite">{{ $capteur->avecUnite($r->valeur) }}</td>
                    <td class="droite">{{ $capteur->avecUnite($anomalies[$r->id]['attendu']) }}</td>
                    <td class="droite">{{ $anomalies[$r->id]['score'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="muet">Aucune anomalie dans les derniers relevés.</p>
    @endif

    <h2>Derniers relevés</h2>
    <table>
        <tr><th>Date</th><th class="droite">Valeur</th><th>État</th><th>Remarque</th></tr>
        @forelse ($derniers->reverse()->take(15) as $r)
            <tr>
                <td>{{ $r->date_releve->format('d/m/Y H:i') }}</td>
                <td class="droite">{{ $capteur->avecUnite($r->valeur) }}</td>
                <td>
                    @if ($r->hors_seuil)
                        <span class="badge red">Hors seuil ({{ $r->setRelation('capteur', $capteur)->sens() }})</span>
                    @else
                        <span class="badge green">Normal</span>
                    @endif
                </td>
                <td class="muet">{{ $r->remarque ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muet">Aucun relevé.</td></tr>
        @endforelse
    </table>

    <h2>Diagnostic de l'IA</h2>
    @if (isset($diagnostic['erreur']))
        <p class="muet">Diagnostic indisponible : {{ $diagnostic['erreur'] }}</p>
    @else
        <div class="rapport">{!! $diagnostic['html'] !!}</div>
        <p class="muet" style="margin-top: 8px">
            @if (! empty($diagnostic['demo']))
                Mode démo : diagnostic rédigé automatiquement par des règles, sans IA.
            @else
                Diagnostic généré par IA ({{ $diagnostic['modele'] }}) le {{ $diagnostic['genereLe'] }} — à vérifier par un gestionnaire.
            @endif
        </p>
    @endif
</body>
</html>