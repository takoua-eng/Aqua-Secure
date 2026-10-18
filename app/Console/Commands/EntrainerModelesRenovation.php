<?php

namespace App\Console\Commands;

use App\Services\Renovation\ModeleCoutAppris;
use App\Services\Renovation\ModeleSourceAppris;
use Illuminate\Console\Command;

// Binôme 3 — php artisan renovation:entrainer : entraîne les modèles IA de la page « Modèles IA »
class EntrainerModelesRenovation extends Command
{
    protected $signature = 'renovation:entrainer';

    protected $description = 'Entraîne les modèles IA du financement des rénovations (prédiction du coût, recommandation de la source de financement)';

    public function handle(): int
    {
        $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', ' ') . ' %';

        $cout = ModeleCoutAppris::entrainer();
        $this->info('Modèle de coût enregistré dans storage/app/private/' . ModeleCoutAppris::FICHIER);
        $this->line("Exemples : {$cout['n']} ({$cout['composition']['base']} devis de la base, {$cout['composition']['synthetique']} historiques synthétiques)");
        if (isset($cout['retenu'])) {
            $this->table(['Méthode (validation croisée « un contre tous »)', 'Erreur relative moyenne', 'Erreur moyenne (DT)'], [
                ...array_map(fn ($c) => [ModeleCoutAppris::ALGORITHMES[$c['algorithme']] . " ({$c['parametre']})", $pct($c['mape']),
                    number_format($c['mae'], 0, ',', ' ')], $cout['candidats']),
                ['Barème actuel (référence)', $pct($cout['bareme']['mape']), number_format($cout['bareme']['mae'], 0, ',', ' ')],
            ]);
        }
        $cout['actif'] ? $this->info('Modèle de coût ACTIF (meilleur que le barème).') : $this->warn('Modèle de coût inactif : ' . $cout['raison']);

        $source = ModeleSourceAppris::entrainer();
        $this->newLine();
        $this->info('Modèle de source enregistré dans storage/app/private/' . ModeleSourceAppris::FICHIER);
        if (isset($source['exactitude'])) {
            $this->table(['Mesure (validation croisée)', 'Valeur'], [
                ['Bonne source en 1er choix', $pct($source['exactitude'])],
                ['Bonne source parmi les 2 premiers choix', $pct($source['exactitude_top2'])],
                ['Référence « toujours ' . $source['reference']['source'] . ' »', $pct($source['reference']['exactitude'])],
            ]);
        }
        $source['actif'] ? $this->info('Modèle de source ACTIF.') : $this->warn('Modèle de source inactif : ' . $source['raison']);

        return self::SUCCESS;
    }
}
