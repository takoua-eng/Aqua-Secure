@php
    $dt = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' DT';
    $a = $synthese['analyse'];
    $f = $d['financements_du_mois'];
    $s = $d['situation_actuelle'];
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport mensuel — {{ $d['mois'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1d273b; }
        h1 { font-size: 18px; margin: 0; color: #0a3d62; }
        h2 { font-size: 12px; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #dce1e7; color: #0a3d62; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #e6e9ee; text-align: left; vertical-align: top; }
        th { background: #f3f5f8; font-size: 9px; }
        .droite { text-align: right; }
        .gris { color: #6b7685; }
        .kpi td { border: 1px solid #dce1e7; text-align: center; width: 25%; }
        .kpi .valeur { font-size: 15px; font-weight: bold; color: #0a3d62; }
        .encadre { border-radius: 4px; padding: 8px 10px; margin-bottom: 6px; }
        .ia { background: #fdeaf1; } .secours { background: #f1f3f5; }
        .etiquette { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 8px; background: #e6f7f2; color: #087f5b; }
    </style>
</head>
<body>
    <h1>Financement des rénovations — rapport de {{ $d['mois'] }}</h1>
    <div class="gris">AquaSecure · généré le {{ $genereLe->format('d/m/Y à H:i') }} · chiffres calculés à partir des données enregistrées</div>

    <h2>1. Chiffres du mois <span class="etiquette">Calculé</span></h2>
    <table class="kpi">
        <tr>
            <td><div class="valeur">{{ $f['nombre'] }}</div>financement(s) enregistré(s)</td>
            <td><div class="valeur">{{ $dt($f['montant']) }}</div>montant du mois</td>
            <td><div class="valeur">{{ $dt($d['montant_mois_precedent']) }}</div>mois précédent</td>
            <td><div class="valeur">{{ $d['projets_crees_du_mois'] }}</div>projet(s) créé(s)</td>
        </tr>
    </table>
    @if ($f['par_source'])
        <table style="margin-top: 6px">
            <thead><tr><th>Source</th><th class="droite">Nombre</th><th class="droite">Montant</th></tr></thead>
            <tbody>
                @foreach ($f['par_source'] as $src)
                    <tr><td>{{ $src['source'] }}</td><td class="droite">{{ $src['nombre'] }}</td><td class="droite">{{ $dt($src['montant']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>2. Situation à la date du rapport <span class="etiquette">Calculé</span></h2>
    <table>
        <tr><td>Projets suivis</td><td class="droite">{{ $s['projets'] }} ({{ collect($s['par_statut'])->map(fn ($n, $l) => "$l : $n")->implode(', ') }})</td></tr>
        <tr><td>Total des financements enregistrés</td><td class="droite">{{ $dt($s['total_finance']) }}</td></tr>
        <tr><td>Projets planifiés ou en cours sans financement</td><td class="droite">{{ $s['projets_sans_financement'] }}</td></tr>
        <tr><td>Taux de couverture (projets avec devis)</td><td class="droite">{{ $s['taux_couverture_devis_pct'] !== null ? number_format($s['taux_couverture_devis_pct'], 1, ',', ' ') . ' %' : 'non calculable' }}</td></tr>
        <tr><td>Indice de Gini territorial (0 = égalité)</td><td class="droite">{{ $s['indice_gini_territorial'] !== null ? number_format($s['indice_gini_territorial'], 2, ',', ' ') : '—' }}</td></tr>
    </table>

    <h2>3. Synthèse {{ $synthese['origine'] === 'ia' ? 'rédigée par l\'IA' : '(mode secours, règles fixes)' }}</h2>
    <div class="encadre {{ $synthese['origine'] === 'ia' ? 'ia' : 'secours' }}">
        <div class="gris">{{ $synthese['modele'] }} — {{ $synthese['genereLe'] }}{{ $synthese['avertissement'] ? ' — ' . $synthese['avertissement'] : '' }}</div>
        <p>{{ $a['synthese'] }}</p>
        <strong>Faits marquants</strong>
        <ul>@foreach ($a['faits_marquants'] as $t)<li>{{ $t }}</li>@endforeach</ul>
        <strong>Points d'attention</strong>
        <ul>@foreach ($a['points_attention'] as $t)<li>{{ $t }}</li>@endforeach</ul>
        <strong>Recommandations</strong>
        <ul>@foreach ($a['recommandations'] as $t)<li>{{ $t }}</li>@endforeach</ul>
    </div>
    <div class="gris">L'interprétation ne contient que des chiffres présents dans les données calculées (contrôle automatique) et doit être vérifiée.</div>

    <h2>4. Projets prioritaires <span class="etiquette">Calculé</span></h2>
    <table>
        <thead><tr><th>Projet</th><th>Statut</th><th class="droite">Score</th></tr></thead>
        <tbody>
            @forelse ($d['projets_prioritaires'] as $p)
                <tr><td>{{ $p['titre'] }}</td><td>{{ $p['statut'] }}</td><td class="droite">{{ $p['score'] }}/100</td></tr>
            @empty
                <tr><td colspan="3" class="gris">Aucun projet en cours ou planifié.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>5. Alertes <span class="etiquette">Calculé</span></h2>
    <table>
        <tbody>
            @forelse ($d['alertes'] as $al)
                <tr><td>{{ $al['code'] }}</td><td>{{ $al['titre'] }}</td><td>{{ ['danger' => 'Critique', 'warning' => 'À surveiller', 'info' => 'Information'][$al['niveau']] }}</td></tr>
            @empty
                <tr><td class="gris">Aucune alerte.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
