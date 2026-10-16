<?php

namespace App\Console\Commands;

use App\Services\DetectionContamination;
use Illuminate\Console\Command;
use RuntimeException;

// php artisan qualite:entrainer — entraîne le modèle de détection de contamination sur les analyses en base
class EntrainerModeleQualite extends Command
{
    protected $signature = 'qualite:entrainer';

    protected $description = "Entraîne le modèle IA de détection précoce de contamination (arbre de décision) sur les analyses de qualité de l'eau";

    public function handle(): int
    {
        try {
            $modele = DetectionContamination::entrainer();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $d = $modele['donnees'];
        $m = $modele['metriques'];
        $pourcent = fn (?float $v) => $v === null ? '—' : number_format($v * 100, 1, ',', ' ') . ' %';

        $this->info('Modèle entraîné et enregistré dans storage/app/private/' . DetectionContamination::FICHIER);
        $this->line("Analyses : {$d['total']} (dont {$d['positifs']} contaminées) · entraînement {$d['entrainement']} · test {$d['test']}");
        $this->table(['Mesure (sur les analyses de test)', 'Valeur'], [
            ['Exactitude', $pourcent($m['exactitude'])],
            ['Précision (alertes justes)', $pourcent($m['precision'])],
            ['Rappel (contaminations détectées)', $pourcent($m['rappel'])],
            ['F1', $pourcent($m['f1'])],
            ['Référence « jamais contaminé »', $pourcent($m['reference'])],
        ]);
        $this->table(['', 'Prédit contaminé', 'Prédit sain'], [
            ['Vraiment contaminé', $m['matrice']['vp'], $m['matrice']['fn']],
            ['Vraiment sain', $m['matrice']['fp'], $m['matrice']['vn']],
        ]);

        return self::SUCCESS;
    }
}
