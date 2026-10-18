<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Incident;
use App\Models\Intervention;
use App\IA\Incidents\AnalyseAnomalies;
use Barryvdh\DomPDF\Facade\Pdf;

echo "Calcul des données réelles du système...\n";

$stats = [
    'total_incidents' => Incident::count(),
    'critiques'       => Incident::critiquesSansIntervention()->count(),
    'en_traitement'   => Incident::enTraitement()->count(),
    'resolus'         => Incident::resolus()->count(),
    'interventions'   => Intervention::where('statut', 'terminee')->count(),
];

$analyseIA = AnalyseAnomalies::charger();
$stabilite = $analyseIA['stabilite']['moyenne'] ?? '0.76';

$data = [
    'dateGeneration' => now()->locale('fr')->isoFormat('D MMMM YYYY [à] HH:mm'),
    'stats'          => $stats,
    'iaInfo'         => [
        'stabilite' => $stabilite,
    ],
];

echo "Rendu du PDF avec DomPDF...\n";

$pdf = Pdf::loadView('pdf.rapport-incidents', $data)
    ->setPaper('a4', 'portrait')
    ->setOption(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

$publicPath = public_path('Rapport_Incidents_Interventions_AquaSecure.pdf');
$rootPath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'Rapport_Incidents_Interventions_AquaSecure.pdf';

$pdf->save($publicPath);
copy($publicPath, $rootPath);

echo "PDF généré avec succès :\n";
echo "1. Dans public/ : " . $publicPath . "\n";
echo "2. Dans dossier racine : " . $rootPath . "\n";
