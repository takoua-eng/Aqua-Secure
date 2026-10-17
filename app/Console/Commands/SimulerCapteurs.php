<?php

namespace App\Console\Commands;

use App\Models\Capteur;
use App\Services\EnregistrementReleve;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/*
 * Simulateur de capteurs : chaque capteur actif produit une nouvelle mesure.
 *
 *   php artisan capteurs:simuler                         1 mesure par capteur, enregistrée directement
 *   php artisan capteurs:simuler --cycles=10 --pause=5   10 tours, 5 secondes entre chaque
 *   php artisan capteurs:simuler --api                   envoi par l'API HTTP, comme un vrai capteur
 *   php artisan capteurs:simuler --fuite=CAP-TN-002      ce capteur perd de la pression à chaque tour
 */
class SimulerCapteurs extends Command
{
    protected $signature = 'capteurs:simuler
        {--cycles=1 : nombre de tours de mesures}
        {--pause=10 : secondes d\'attente entre deux tours}
        {--api : envoyer les mesures par l\'API de télémétrie (le serveur doit être lancé)}
        {--fuite= : code d\'un capteur dont la mesure chute à chaque tour (simulation d\'une fuite)}';

    protected $description = 'Simule des capteurs qui envoient leurs mesures (télémétrie)';

    public function handle(): int
    {
        $cycles = max(1, (int) $this->option('cycles'));
        $pause = max(0, (int) $this->option('pause'));

        if ($this->option('api') && blank(config('services.telemetrie.cle'))) {
            $this->error('TELEMETRIE_API_KEY est vide dans le .env : impossible d\'utiliser --api.');

            return self::FAILURE;
        }

        for ($tour = 1; $tour <= $cycles; $tour++) {
            $this->info("Tour {$tour}/{$cycles} — " . now()->format('H:i:s'));
            $lignes = [];

            foreach (Capteur::where('statut', 'actif')->with('dernierReleve')->orderBy('code')->get() as $capteur) {
                $valeur = $this->prochaineValeur($capteur);
                $lignes[] = $this->option('api')
                    ? $this->envoyerParApi($capteur, $valeur)
                    : $this->enregistrerDirectement($capteur, $valeur);
            }

            $this->table(['Capteur', 'Valeur', 'État', 'Alerte email'], $lignes);

            if ($tour < $cycles && $pause > 0) {
                sleep($pause);
            }
        }

        return self::SUCCESS;
    }

    // Marche aléatoire qui revient vers le milieu de la plage normale, avec de rares pics
    private function prochaineValeur(Capteur $capteur): float
    {
        $milieu = ($capteur->seuil_min + $capteur->seuil_max) / 2;
        $amplitude = ($capteur->seuil_max - $capteur->seuil_min) / 2;
        $precedente = $capteur->dernierReleve?->valeur ?? $milieu;

        if ($this->option('fuite') === $capteur->code) {
            $valeur = $precedente - $amplitude * 0.3;                       // chute régulière
        } else {
            $valeur = $precedente
                + ($milieu - $precedente) * 0.3                              // retour vers la normale
                + $amplitude * mt_rand(-10, 10) / 100;                       // bruit de mesure
            if (mt_rand(1, 100) <= 3) {
                $valeur += $amplitude * (mt_rand(0, 1) ? 1.2 : -1.2);        // pic rare (3 %)
            }
        }

        $plancher = $capteur->type_mesure === 'temperature' ? -50 : 0;

        return round(max($valeur, $plancher), 2);
    }

    private function enregistrerDirectement(Capteur $capteur, float $valeur): array
    {
        $r = EnregistrementReleve::enregistrer($capteur, $valeur, now(), 'Simulation');

        return [
            $capteur->code,
            $capteur->avecUnite($valeur),
            $r['releve']->hors_seuil ? 'HORS SEUIL' : ($r['anomalie'] ? 'Anomalie' : 'Normal'),
            $r['alerte'] ? 'envoyée' : '—',
        ];
    }

    private function envoyerParApi(Capteur $capteur, float $valeur): array
    {
        try {
            $reponse = Http::withHeaders(['X-Api-Key' => config('services.telemetrie.cle')])
                ->acceptJson()
                ->timeout(10)
                ->post(url('/api/telemetrie/releves'), ['code' => $capteur->code, 'valeur' => $valeur]);
        } catch (Throwable $e) {
            return [$capteur->code, $capteur->avecUnite($valeur), 'Serveur injoignable (php artisan serve ?)', '—'];
        }

        if ($reponse->failed()) {
            return [$capteur->code, $capteur->avecUnite($valeur), 'Erreur ' . $reponse->status(), '—'];
        }

        $d = $reponse->json();

        return [
            $capteur->code,
            $capteur->avecUnite($valeur),
            $d['hors_seuil'] ? 'HORS SEUIL' : ($d['anomalie'] ? 'Anomalie' : 'Normal'),
            $d['alerte_envoyee'] ? 'envoyée' : '—',
        ];
    }
}