{{-- Binôme 3 — panneau de chantier A4 à imprimer : le passant scanne le QR code et arrive sur la fiche publique --}}
@php
    [$statut, , , $couleur] = \App\Services\Renovation\ObservatoireCitoyen::statut($projet->statut);
    $zone = $projet->reseau?->zone;
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panneau de chantier — {{ $projet->titre }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Poppins', sans-serif; background: #e9eef3; color: #0a3d62; }
        .barre { max-width: 210mm; margin: 16px auto; display: flex; gap: 8px; align-items: center; font-size: 14px; }
        .barre button, .barre a { border: 0; border-radius: 8px; padding: 10px 16px; background: #0b6fb8; color: #fff; text-decoration: none; cursor: pointer; font: inherit; }
        .barre a { background: #fff; color: #0a3d62; }
        .feuille { width: 210mm; min-height: 297mm; margin: 0 auto 24px; background: #fff; padding: 16mm; display: flex; flex-direction: column; }
        .entete { display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 20px; }
        .bandeau { margin: 10mm -16mm 8mm; padding: 10mm 16mm; background: linear-gradient(135deg, #0a3d62, #0b6fb8); color: #fff; }
        .statut { display: inline-block; padding: 4px 14px; border-radius: 30px; background: #fff; font-weight: 600; font-size: 14px; }
        h1 { font-size: 30px; line-height: 1.2; margin: 10px 0 6px; }
        .lieu { font-size: 16px; opacity: .85; }
        .grille { display: flex; gap: 10mm; align-items: center; flex: 1; }
        .infos { flex: 1; font-size: 15px; }
        .montant { font-size: 34px; font-weight: 700; }
        .source { display: flex; justify-content: space-between; border-bottom: 1px solid #e6e9ee; padding: 6px 0; }
        .qr { text-align: center; }
        .qr #qr { width: 75mm; height: 75mm; margin: 0 auto; }
        .qr #qr img, .qr #qr svg { width: 100%; height: 100%; }
        .pied { font-size: 12px; color: #6b7685; border-top: 2px solid #e9eef3; padding-top: 6mm; margin-top: 8mm; }
        .avertissement { max-width: 210mm; margin: 0 auto 12px; background: #fff3cd; padding: 10px 14px; border-radius: 8px; color: #664d03; }
        @media print { body { background: #fff; } .barre, .avertissement { display: none; } .feuille { margin: 0; } @page { size: A4; margin: 0; } }
    </style>
</head>
<body>
    <div class="barre">
        <a href="{{ route('admin.projets.show', $projet) }}">← Retour au projet</a>
        <button type="button" onclick="window.print()">Imprimer le panneau</button>
        <span style="color:#6b7685">Format A4 portrait</span>
    </div>

    @unless ($projet->publie)
        <div class="avertissement">
            Ce projet n'est pas publié : le QR code mènera à une page introuvable tant que la case « Publier ce projet » n'est pas cochée.
        </div>
    @endunless

    <div class="feuille">
        <div class="entete"><img src="{{ asset('favicon.svg') }}" alt="" width="40" height="40"> AquaSecure · Rénovation du réseau d'eau potable</div>

        <div class="bandeau">
            <span class="statut" style="color: {{ $couleur }}">{{ $statut }}</span>
            <h1>{{ $projet->titre }}</h1>
            <div class="lieu">{{ $zone?->nom }}, {{ $zone?->commune }} · {{ $projet->reseau?->libelleType() }} {{ $projet->reseau?->code }}</div>
        </div>

        <div class="grille">
            <div class="infos">
                <div>Financement obtenu</div>
                <div class="montant">{{ number_format($finance, 0, ',', ' ') }} DT</div>
                @if ($projet->cout_estime)
                    <div style="margin-bottom: 10px">sur un coût prévu de {{ number_format((float) $projet->cout_estime, 0, ',', ' ') }} DT</div>
                @endif
                @foreach ($sources as $source => $montant)
                    <div class="source"><span>{{ \App\Models\Financement::libelleSourceDe($source) }}</span><strong>{{ number_format((float) $montant, 0, ',', ' ') }} DT</strong></div>
                @endforeach
            </div>
            <div class="qr">
                <div id="qr"></div>
                <div style="font-weight: 600; font-size: 18px; margin-top: 4mm">Scannez pour suivre ce chantier</div>
                <div style="font-size: 11px; color: #6b7685; word-break: break-all">{{ $url }}</div>
            </div>
        </div>

        <div class="pied">
            Avancement, financements, questions des habitants : toutes les informations publiées par le gestionnaire du réseau sont sur la page du projet.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js" integrity="sha384-lQXOAyZwHXE55JFyrOMB7nY2Wv+m5ZWNtJcHrd1rceRQXAYNLak8ukN5TjBTcIwz" crossorigin="anonymous"></script>
    <script>
        (function () {
            const zone = document.getElementById('qr');
            if (typeof qrcode !== 'function') {
                zone.textContent = 'QR code indisponible (connexion Internet requise).';
                return;
            }
            const qr = qrcode(0, 'M');          // taille automatique, correction d'erreur moyenne
            qr.addData(@json($url));
            qr.make();
            zone.innerHTML = qr.createSvgTag({ cellSize: 8, margin: 2, scalable: true });
        })();
    </script>
</body>
</html>
