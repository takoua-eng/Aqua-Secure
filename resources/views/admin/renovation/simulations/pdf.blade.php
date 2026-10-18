@php
    $r = $simulation->resultats;
    $s = $simulation->scenarioRetenu();
    $k = $s['kpi'];
    $noms = collect($r['scenarios'])->map(fn ($x) => $x['lettre'] . ' · ' . $x['libelle'])->all();
    $dt = fn ($v, $d = 0) => number_format((float) $v, $d, ',', ' ') . ' DT';
    $a = $simulation->analyse_ia['analyse'] ?? null;
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport de décision — {{ $simulation->nom }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1d273b; }
        h1 { font-size: 17px; margin: 0 0 4px; color: #0a3d62; }
        h2 { font-size: 12px; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #dce1e7; color: #0a3d62; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 5px; border-bottom: 1px solid #e6e9ee; text-align: left; vertical-align: top; }
        th { background: #f3f5f8; font-size: 9px; }
        .droite { text-align: right; }
        .gris { color: #6b7685; }
        .etiquette { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 8px; }
        .hyp { background: #f3e8fd; color: #7b2cbf; } .calc { background: #e6f7f2; color: #087f5b; }
        .reel { background: #e7f0fb; color: #1864ab; } .ia { background: #fdeaf1; color: #a61e4d; }
        .encadre { border: 1px solid #dce1e7; border-radius: 4px; padding: 8px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <h1>Rapport de décision — {{ $simulation->nom }}</h1>
    <div class="gris">AquaSecure · Financement des rénovations · généré le {{ $genereLe->format('d/m/Y à H:i') }}</div>
    <div class="gris">Statut : <strong>{{ $simulation->libelleStatut() }}</strong> · auteur : {{ $simulation->auteur?->nom ?? '—' }}
        · enregistré le {{ $simulation->created_at->format('d/m/Y H:i') }}
        @if ($simulation->decide_le) · décision : {{ $simulation->decideur?->nom }} le {{ $simulation->decide_le->format('d/m/Y') }} @endif</div>

    <p>
        <span class="etiquette hyp">Hypothèse</span> valeur saisie dans le simulateur ·
        <span class="etiquette reel">Enregistré</span> donnée réelle ·
        <span class="etiquette calc">Calculé</span> résultat du moteur déterministe ·
        <span class="etiquette ia">IA</span> interprétation à vérifier
    </p>

    <h2>1. Hypothèses <span class="etiquette hyp">Hypothèse</span></h2>
    <ul>
        @foreach ($r['hypotheses'] as $h)<li>{{ $h }}</li>@endforeach
        @foreach ($r['diagnostic']['messages'] as $m)<li>{{ $m }}</li>@endforeach
    </ul>

    <h2>2. Stratégie retenue : {{ $noms[$simulation->strategie] }} <span class="etiquette calc">Calculé</span></h2>
    <p>{{ $s['criteres'] }}</p>
    <table>
        <tr>
            <td>Budget hypothétique<br><strong>{{ $dt($k['budget']) }}</strong></td>
            <td>Alloué<br><strong>{{ $dt($k['alloue']) }}</strong></td>
            <td>Projets achevés<br><strong>{{ $k['nb_complets'] }} / {{ $k['nb_candidats'] }}</strong></td>
            <td>Financements partiels<br><strong>{{ $k['nb_partiels'] }}</strong></td>
            <td>Priorité couverte<br><strong>{{ number_format($k['priorite_couverte_pct'], 1, ',', ' ') }} %</strong></td>
            <td>Réseaux dégradés traités<br><strong>{{ $k['critiques_couverts'] }} / {{ $k['critiques_total'] }}</strong></td>
        </tr>
    </table>

    <h2>3. Répartition proposée <span class="etiquette calc">Calculé</span></h2>
    <table>
        <thead>
            <tr><th>Projet</th><th>Réseau</th><th>Priorité</th><th class="droite">Coût</th><th class="droite">Déjà financé</th><th class="droite">Proposé</th><th>Résultat</th></tr>
        </thead>
        <tbody>
            @foreach ($s['lignes'] as $l)
                <tr>
                    <td>{{ $l['titre'] }}</td>
                    <td>{{ $l['reseau'] }}</td>
                    <td>{{ $l['score'] }}/100</td>
                    <td class="droite">{{ $dt($l['cout']) }}<br><span class="gris">{{ \App\Services\Renovation\ModeleCout::COURTS[$l['cout_source']] ?? 'estimé' }}</span></td>
                    <td class="droite">{{ $dt($l['finance']) }}</td>
                    <td class="droite"><strong>{{ $dt($l['propose'], 2) }}</strong></td>
                    <td>{{ ['complet' => 'Financé en entier', 'partiel' => 'Partiel', 'aucun' => 'Non financé'][$l['etat']] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>4. Comparaison des stratégies <span class="etiquette calc">Calculé</span></h2>
    <table>
        <thead><tr><th>Stratégie</th><th class="droite">Priorité couverte</th><th class="droite">Achevés</th><th class="droite">Partiels</th><th class="droite">Dégradés traités</th><th class="droite">Non utilisé</th></tr></thead>
        <tbody>
            @foreach ($r['scenarios'] as $cle => $sc)
                <tr>
                    <td>{{ $noms[$cle] }} @if ($cle === $r['recommande']) (recommandée par la règle) @endif</td>
                    <td class="droite">{{ number_format($sc['kpi']['priorite_couverte_pct'], 1, ',', ' ') }} %</td>
                    <td class="droite">{{ $sc['kpi']['nb_complets'] }}</td>
                    <td class="droite">{{ $sc['kpi']['nb_partiels'] }}</td>
                    <td class="droite">{{ $sc['kpi']['critiques_couverts'] }}</td>
                    <td class="droite">{{ $dt($sc['kpi']['reste']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="gris">{{ $r['raison'] }}</p>

    <h2>5. Risques et compromis <span class="etiquette calc">Calculé</span></h2>
    <ul>
        @forelse ($s['risques'] as $risque)<li>{{ $risque['texte'] }}</li>@empty<li>Aucun risque particulier détecté.</li>@endforelse
        <li>Compromis : {{ $s['compromis'] }}</li>
    </ul>

    <h2>6. Interprétation <span class="etiquette ia">{{ ($simulation->analyse_ia['origine'] ?? null) === 'ia' ? 'IA' : 'Mode secours' }}</span></h2>
    @if ($a)
        <div class="encadre">
            <div class="gris">{{ $simulation->analyse_ia['modele'] }} — {{ $simulation->analyse_ia['genereLe'] }}</div>
            <p>{{ $a['synthese'] }}</p>
            <p><strong>Recommandation : {{ $noms[$a['recommandation']['strategie']] ?? '' }}.</strong> {{ $a['recommandation']['justification'] }}</p>
            @if ($a['ameliorations'])<p><strong>Améliorations :</strong> {{ implode(' ', $a['ameliorations']) }}</p>@endif
            @if ($a['donnees_manquantes'])<p><strong>Données manquantes :</strong> {{ implode(' ', $a['donnees_manquantes']) }}</p>@endif
        </div>
    @else
        <p class="gris">Aucune analyse n'a été générée pour ce scénario.</p>
    @endif

    <h2>7. Décision</h2>
    <p>
        @switch($simulation->statut)
            @case('brouillon') Brouillon : aucune décision. @break
            @case('soumise') En attente de validation par un administrateur. @break
            @case('rejetee') Rejeté. {{ $simulation->commentaire_decision }} @break
            @default
                Approuvé (source des fonds : {{ \App\Models\Financement::libelleSourceDe($simulation->source_financement) }}).
                {{ $simulation->commentaire_decision }}
                @if ($simulation->appliquee_le) Appliqué le {{ $simulation->appliquee_le->format('d/m/Y à H:i') }} : financements réels créés. @endif
        @endswitch
    </p>
</body>
</html>
