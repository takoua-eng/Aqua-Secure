<?php

namespace Database\Seeders;

use App\Models\ProjetRenovation;
use App\Models\ReseauEau;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/*
 * Binôme 3 — DONNÉES DE DÉMONSTRATION : un projet de rénovation publié dans chacun des 24 gouvernorats.
 *
 * Pour chaque gouvernorat (config/communes.php) : une zone du chef-lieu (la zone existante est réutilisée si elle
 * existe déjà), un élément de réseau dédié (code « XXX-GOV-901 », position = centre de la commune), un projet publié
 * et ses financements. Populations, coûts et montants sont FICTIFS ; chaque description commence par « [Démonstration] ».
 * Relançable sans doublon. Ne modifie aucune donnée existante.
 *
 * Lancement : php artisan db:seed --class=RenovationTunisieSeeder
 */
class RenovationTunisieSeeder extends Seeder
{
    public function run(): void
    {
        // gouvernorat, commune, abréviation, zone, population, type, matériau, mise en service, état, état depuis (mois),
        // titre, statut, coût déclaré (null = sans devis), financements [source, montant], travaux
        $projets = [
            ['Tunis', 'Tunis', 'TUN', 'Bab Souika', 38000, 'canalisation', 'fonte', '1968-04-10', 'degrade', 9,
                'Renouvellement de la conduite principale de Bab Souika', 'en_cours', 620000, [['Etat', 300000], ['municipalite', 120000]],
                'remplacement de 2,4 km de conduite en fonte par du PEHD et reprise des branchements'],
            ['Ariana', 'Ariana', 'ARI', 'Ennasr', 45000, 'reservoir', 'beton', '1992-06-01', 'degrade', 5,
                'Réhabilitation du réservoir surélevé d\'Ennasr', 'planifie', 480000, [['bailleur', 200000]],
                'traitement des fissures, étanchéité intérieure et remplacement des équipements hydrauliques'],
            ['Ben Arous', 'Ben Arous', 'BEA', 'El Mourouj 1', 52000, 'canalisation', 'amiante_ciment', '1979-09-15', 'hors_service', 2,
                'Remplacement des conduites en amiante-ciment d\'El Mourouj', 'en_cours', 950000, [['Etat', 500000], ['bailleur', 250000]],
                'dépose sécurisée des conduites en amiante-ciment et pose de conduites PEHD'],
            ['Manouba', 'Manouba', 'MAN', 'Manouba Ville', 26000, 'canalisation', 'pvc', '1996-03-20', 'degrade', 7,
                'Renforcement du réseau de distribution de Manouba', 'planifie', 310000, [],
                'doublement des conduites saturées et installation de vannes de sectionnement'],
            ['Nabeul', 'Nabeul', 'NAB', 'Nabeul Centre', 33000, 'station', 'acier', '1990-05-12', 'degrade', 11,
                'Modernisation de la station de pompage de Nabeul', 'en_cours', 1450000, [['bailleur', 700000], ['Etat', 300000]],
                'remplacement des pompes, variateurs de vitesse et télégestion de la station'],
            ['Zaghouan', 'Zaghouan', 'ZAG', 'Zaghouan Centre', 14000, 'canalisation', 'fonte', '1955-01-01', 'hors_service', 3,
                'Réhabilitation de l\'adduction de Zaghouan', 'planifie', 780000, [['Etat', 150000], ['don', 30000]],
                'remplacement de l\'adduction ancienne qui alimente le centre-ville'],
            ['Bizerte', 'Bizerte', 'BIZ', 'Zarzouna', 21000, 'canalisation', 'acier', '1984-11-02', 'degrade', 6,
                'Réduction des fuites du réseau de Zarzouna', 'termine', 260000, [['Etat', 180000], ['municipalite', 80000]],
                'recherche des fuites, réparation des conduites et pose de compteurs de sectorisation'],
            ['Béja', 'Béja', 'BEJ', 'Béja Nord', 19000, 'reservoir', 'beton', '1976-07-08', 'degrade', 14,
                'Étanchéité du réservoir de Béja Nord', 'en_cours', 410000, [['Etat', 250000]],
                'reprise de l\'étanchéité de la cuve et de la toiture du réservoir'],
            ['Jendouba', 'Jendouba', 'JEN', 'Jendouba Centre', 17000, 'canalisation', 'amiante_ciment', '1981-02-14', 'degrade', 8,
                'Renouvellement des conduites de Jendouba Centre', 'planifie', 520000, [['bailleur', 260000]],
                'remplacement des conduites en amiante-ciment du centre-ville'],
            ['Le Kef', 'Le Kef', 'KEF', 'Le Kef Médina', 15000, 'canalisation', 'fonte', '1962-10-01', 'hors_service', 4,
                'Remplacement de la conduite de la Médina du Kef', 'planifie', null, [['don', 20000]],
                'remplacement de la conduite qui dessert les ruelles de la Médina'],
            ['Siliana', 'Siliana', 'SIL', 'Siliana Centre', 12000, 'station', 'acier', '1993-08-30', 'degrade', 10,
                'Mise à niveau de la station de traitement de Siliana', 'planifie', 1200000, [],
                'rénovation de la filière de filtration et du poste de chloration'],
            ['Sousse', 'Sousse', 'SOU', 'Sahloul', 31000, 'canalisation', 'pehd', '2004-04-04', 'bon', null,
                'Extension du réseau vers Sahloul 4', 'termine', 340000, [['municipalite', 190000], ['Etat', 150000]],
                'raccordement des nouveaux lotissements de Sahloul 4 au réseau'],
            ['Monastir', 'Monastir', 'MON', 'Skanes', 15000, 'reservoir', 'beton', '1988-12-01', 'degrade', 4,
                'Rénovation du réservoir de Skanes', 'en_cours', 450000, [['Etat', 200000], ['bailleur', 100000]],
                'réparation du génie civil et remplacement de la tuyauterie du réservoir'],
            ['Mahdia', 'Mahdia', 'MAH', 'Hiboun', 23000, 'canalisation', 'pvc', '1999-06-18', 'degrade', 3,
                'Renforcement de l\'alimentation de Hiboun', 'planifie', 290000, [['municipalite', 60000]],
                'pose d\'une conduite de renfort pour l\'été, quand la demande double'],
            ['Kairouan', 'Kairouan', 'KAI', 'Médina', 35000, 'canalisation', 'fonte', '1970-03-03', 'hors_service', 6,
                'Remplacement de la conduite Bab Tunis – Médina', 'en_cours', 560000, [['Etat', 260000], ['don', 40000]],
                'remplacement de la conduite entre Bab Tunis et la Médina, travaux de nuit'],
            ['Kasserine', 'Kasserine', 'KAS', 'Cité Ennour', 28000, 'canalisation', 'amiante_ciment', '1983-05-22', 'degrade', 12,
                'Renouvellement du réseau de la cité Ennour', 'planifie', 640000, [['bailleur', 320000]],
                'remplacement complet du réseau de distribution de la cité'],
            ['Sidi Bouzid', 'Sidi Bouzid', 'SBZ', 'Sidi Bouzid Ouest', 24000, 'station', 'acier', '1991-09-09', 'hors_service', 2,
                'Remise en service de la station de pompage Ouest', 'en_cours', 880000, [['Etat', 600000]],
                'remplacement des groupes de pompage et de l\'armoire électrique'],
            ['Sfax', 'Sfax', 'SFX', 'Route de Gremda', 12000, 'canalisation', 'fonte', '1972-01-20', 'degrade', 15,
                'Renouvellement de la conduite de la route de Gremda', 'planifie', 720000, [['municipalite', 150000], ['Etat', 200000]],
                'remplacement de la conduite principale le long de la route de Gremda'],
            ['Gafsa', 'Gafsa', 'GAF', 'Gafsa Centre', 30000, 'reservoir', 'beton', '1980-10-10', 'degrade', 9,
                'Réfection du réservoir principal de Gafsa', 'planifie', 530000, [],
                'réparation des parois et mise en place d\'un suivi de niveau à distance'],
            ['Tozeur', 'Tozeur', 'TOZ', 'Tozeur Oasis', 11000, 'canalisation', 'pvc', '1994-02-25', 'degrade', 5,
                'Réduction des pertes du réseau de l\'oasis de Tozeur', 'en_cours', 230000, [['bailleur', 120000], ['municipalite', 30000]],
                'remplacement des tronçons fuyards et pose de compteurs de quartier'],
            ['Kébili', 'Kébili', 'KEB', 'Kébili Centre', 10000, 'station', 'acier', '1995-07-07', 'degrade', 7,
                'Rénovation de l\'unité de refroidissement de Kébili', 'planifie', 980000, [['bailleur', 400000]],
                'remise à neuf de l\'unité qui refroidit l\'eau des forages avant distribution'],
            ['Gabès', 'Gabès', 'GAB', 'Jara', 27000, 'canalisation', 'fonte', '1966-11-11', 'hors_service', 1,
                'Remplacement de la conduite de Jara', 'en_cours', 610000, [['Etat', 350000]],
                'remplacement de la conduite principale du quartier de Jara'],
            ['Médenine', 'Médenine', 'MED', 'Médenine Nord', 22000, 'reservoir', 'beton', '1986-04-16', 'degrade', 6,
                'Réhabilitation du réservoir de Médenine Nord', 'termine', 390000, [['Etat', 250000], ['municipalite', 140000]],
                'réfection complète du réservoir et de sa chambre des vannes'],
            ['Tataouine', 'Tataouine', 'TAT', 'Tataouine Centre', 16000, 'canalisation', 'pvc', '1997-05-05', 'bon', null,
                'Extension du réseau vers les nouveaux quartiers de Tataouine', 'planifie', null, [['don', 15000]],
                'pose de nouvelles conduites vers les quartiers en extension'],
        ];

        $prefixes = ['canalisation' => 'CAN', 'reservoir' => 'RES', 'station' => 'STA'];

        foreach ($projets as $i => [$gouvernorat, $commune, $abr, $nomZone, $population, $type, $materiau, $miseEnService,
                                    $etat, $depuisMois, $titre, $statut, $cout, $financements, $travaux]) {
            $coords = Zone::coordonneesCommune($commune);
            if (! $coords) {
                continue; // commune absente de config/communes.php
            }

            // Zone existante réutilisée telle quelle (population non modifiée)
            $zone = Zone::firstOrCreate(['nom' => $nomZone, 'commune' => $commune], ['population' => $population]);

            $code = $prefixes[$type] . "-{$abr}-901";
            $reseau = ReseauEau::where('code', $code)->first();
            if (! $reseau) {
                // Petit décalage autour du centre de la commune pour ne pas superposer les points sur la carte
                $reseau = $zone->reseaux()->create([
                    'code' => $code, 'type' => $type, 'materiau' => $materiau, 'date_mise_en_service' => $miseEnService,
                    'etat' => 'bon', 'latitude' => round($coords[0] + 0.004 * ($i % 3 - 1), 7),
                    'longitude' => round($coords[1] + 0.004 * (($i + 1) % 3 - 1), 7),
                ]);
                $creation = max($miseEnService, '1971-01-01');
                $reseau->historiques()->update(['created_at' => $creation, 'updated_at' => $creation]);

                if ($etat !== 'bon') {
                    $reseau->update(['etat' => $etat]);
                    $date = Carbon::now()->subMonths($depuisMois);
                    $reseau->historiques()->where('nouvel_etat', $etat)->update(['created_at' => $date, 'updated_at' => $date]);
                }
            }

            $projet = ProjetRenovation::firstOrCreate(
                ['reseau_id' => $reseau->id, 'titre' => $titre],
                [
                    'statut'      => $statut,
                    'cout_estime' => $cout,
                    'publie'      => true,
                    'description' => "[Démonstration] Projet fictif du gouvernorat de {$gouvernorat} : {$travaux}.",
                ],
            );

            if (! $projet->financements()->exists()) {
                foreach ($financements as [$source, $montant]) {
                    $projet->financements()->create(['source' => $source, 'montant' => $montant]);
                }
            }
        }
    }
}
