<?php

namespace App\Console\Commands;

use App\IA\Incidents\AnalyseAnomalies;
use Illuminate\Console\Command;

// php artisan incidents:analyser — analyse IA des incidents (binôme 2). Lecture seule de la base.
class AnalyserIncidents extends Command
{
    protected $signature = 'incidents:analyser';

    protected $description = "Analyse IA des incidents : détection d'observations inhabituelles (réseau × mois) par Isolation Forest — lecture seule de la base";

    public function handle(): int
    {
        $resultat = AnalyseAnomalies::executer();

        $this->table(['Critère', 'Valeur', 'Minimum', 'Rempli'], collect($resultat['criteres'])->map(fn ($c) => [
            $c['libelle'], $c['valeur'], $c['minimum'], $c['ok'] ? 'oui' : 'NON',
        ])->all());

        if ($resultat['statut'] === 'donnees_insuffisantes') {
            $this->warn("Données insuffisantes : le modèle n'a pas été lancé (aucun résultat inventé).");

            return self::SUCCESS;
        }

        $this->info('Analyse enregistrée dans storage/app/private/' . AnalyseAnomalies::FICHIER);
        $this->line("Période : {$resultat['periode']['debut']} → {$resultat['periode']['fin']} · observations : {$resultat['observations']}");
        $this->line("Observations signalées (score ≥ " . AnalyseAnomalies::SEUIL . ") : {$resultat['signalees']}");
        $this->line('Stabilité moyenne entre graines (Jaccard) : ' . $resultat['stabilite']['moyenne']);

        return self::SUCCESS;
    }
}
