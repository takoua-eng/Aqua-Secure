<?php

namespace Database\Seeders;

use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InfrastructureSeeder extends Seeder
{
    // Quelques zones et éléments de réseau pour tester les listes, les filtres et la carte
    public function run(): void
    {
        // code, type, matériau, mise en service, état actuel, dégradé depuis (mois), latitude, longitude
        $zones = [
            ['Centre-ville', 'Tunis', 42000, [
                ['CAN-TUN-001', 'canalisation', 'fonte',          '1978-05-12', 'degrade',      8,    36.8001, 10.1797],
                ['CAN-TUN-002', 'canalisation', 'pehd',           '2015-09-01', 'bon',          null, 36.7985, 10.1840],
                ['RES-TUN-001', 'reservoir',    'beton',          '1990-03-20', 'bon',          null, 36.8030, 10.1765],
            ]],
            ['Cité El Khadra', 'Tunis', 18000, [
                ['RES-TUN-002', 'reservoir',    'beton',          '1985-11-02', 'degrade',      3,    36.8335, 10.1930],
                ['CAN-TUN-003', 'canalisation', 'amiante_ciment', '1972-06-15', 'hors_service', 1,    36.8310, 10.1955],
            ]],
            ['El Menzah 6', 'Ariana', 25000, [
                ['CAN-ARI-001', 'canalisation', 'acier',          '1995-04-10', 'hors_service', 2,    36.8505, 10.1690],
                ['STA-ARI-001', 'station',      'beton',          '2008-01-15', 'bon',          null, 36.8540, 10.1720],
            ]],
            ['Sahloul', 'Sousse', 31000, [
                ['STA-SOU-001', 'station',      'acier',          '2001-07-22', 'degrade',      5,    35.8390, 10.5890],
                ['CAN-SOU-001', 'canalisation', 'pvc',            '2012-10-05', 'bon',          null, null,    null],
            ]],
            ['Route de Gremda', 'Sfax', 12000, []],
            ['La Marsa Plage', 'La Marsa', 22000, [
                ['CAN-MAR-001', 'canalisation', 'pehd',           '2018-03-01', 'bon',          null, 36.8800, 10.3270],
                ['RES-MAR-001', 'reservoir',    'beton',          '2005-06-12', 'bon',          null, 36.8760, 10.3200],
            ]],
            ['Hammam Lif Centre', 'Hammam Lif', 27000, [
                ['CAN-HLF-001', 'canalisation', 'fonte',          '1965-09-01', 'degrade',      14,   36.7290, 10.3350],
                ['CAN-HLF-002', 'canalisation', 'amiante_ciment', '1970-04-18', 'degrade',      6,    36.7320, 10.3310],
                ['RES-HLF-001', 'reservoir',    'beton',          '1975-02-10', 'bon',          null, 36.7260, 10.3290],
            ]],
            ['Kélibia Port', 'Kélibia', 9000, [
                ['CAN-KEL-001', 'canalisation', 'pvc',            '2010-05-20', 'bon',          null, 36.8460, 11.0990],
            ]],
            ['Médina', 'Kairouan', 35000, [
                ['CAN-KAI-001', 'canalisation', 'fonte',          '1960-01-15', 'hors_service', 4,    35.6790, 10.1000],
                ['STA-KAI-001', 'station',      'acier',          '1988-11-03', 'degrade',      10,   35.6820, 10.0930],
            ]],
            ['Skanes', 'Monastir', 15000, [
                ['CAN-MON-001', 'canalisation', 'pehd',           '2020-07-07', 'bon',          null, 35.7700, 10.7900],
                ['RES-MON-001', 'reservoir',    'beton',          '2012-09-15', 'bon',          null, 35.7680, 10.7950],
            ]],
        ];

        foreach ($zones as [$nom, $commune, $population, $reseaux]) {
            $zone = Zone::updateOrCreate(['nom' => $nom, 'commune' => $commune], ['population' => $population]);

            foreach ($reseaux as [$code, $type, $materiau, $miseEnService, $etat, $depuisMois, $lat, $lng]) {
                if ($zone->reseaux()->where('code', $code)->exists()) {
                    continue; // seeder relancé : on ne recrée pas le réseau
                }

                // Créé "bon" à sa mise en service, puis passé à son état actuel (=> 2 lignes d'historique)
                $reseau = $zone->reseaux()->create([
                    'code' => $code, 'type' => $type, 'materiau' => $materiau,
                    'date_mise_en_service' => $miseEnService, 'etat' => 'bon',
                    'latitude' => $lat, 'longitude' => $lng,
                ]);
                // Les colonnes timestamp de MySQL commencent en 1970 : on ne peut pas dater l'historique plus tôt
                $dateCreation = max($miseEnService, '1971-01-01');
                $reseau->historiques()->update(['created_at' => $dateCreation, 'updated_at' => $dateCreation]);

                if ($etat !== 'bon') {
                    $reseau->update(['etat' => $etat]);
                    $date = Carbon::now()->subMonths($depuisMois);
                    $reseau->historiques()->where('nouvel_etat', $etat)
                        ->update(['created_at' => $date, 'updated_at' => $date]);
                }
            }
        }
    }
}
