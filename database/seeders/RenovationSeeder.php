<?php

namespace Database\Seeders;

use App\Models\ProjetRenovation;
use App\Models\ReseauEau;
use Illuminate\Database\Seeder;

/*
 * Binôme 3 — DONNÉES DE DÉMONSTRATION (projets de rénovation et financements fictifs).
 * Rattachées aux réseaux créés par InfrastructureSeeder ; un réseau absent est simplement ignoré.
 * Chaque description commence par « [Démonstration] » pour ne jamais être confondue avec une donnée réelle.
 * Relançable sans doublon (projets identifiés par réseau + titre ; financements ajoutés seulement si aucun n'existe).
 *
 * Lancement (avec autorisation, sur une base de développement) : php artisan db:seed --class=RenovationSeeder
 */
class RenovationSeeder extends Seeder
{
    public function run(): void
    {
        // code réseau, titre, statut, coût déclaré (null = sans devis), publié, financements [source, montant]
        $projets = [
            ['CAN-TUN-003', 'Remplacement de la conduite en amiante-ciment d\'El Khadra', 'en_cours', 165000, true,
                [['Etat', 90000], ['bailleur', 40000]]],
            ['CAN-ARI-001', 'Réhabilitation de la conduite acier d\'El Menzah 6', 'planifie', null, true, []],
            ['CAN-KAI-001', 'Renouvellement de la conduite principale de la Médina', 'en_cours', 140000, true,
                [['Etat', 60000]]],
            ['STA-KAI-001', 'Modernisation de la station de traitement de Kairouan', 'planifie', 950000, false,
                [['bailleur', 300000], ['Etat', 150000]]],
            ['CAN-HLF-001', 'Remplacement de la conduite en fonte de Hammam Lif', 'planifie', null, false, []],
            ['CAN-HLF-002', 'Désamiantage et pose PEHD — Hammam Lif Ouest', 'planifie', 170000, false,
                [['municipalite', 25000]]],
            ['RES-TUN-002', 'Étanchéité et réfection du réservoir R2 d\'El Khadra', 'en_cours', 380000, true,
                [['Etat', 200000], ['municipalite', 50000], ['don', 15000]]],
            ['STA-SOU-001', 'Mise à niveau de la station de Sahloul', 'planifie', null, false, []],
            ['CAN-TUN-001', 'Rénovation de la conduite principale du centre-ville', 'termine', 135000, true,
                [['Etat', 100000], ['municipalite', 35000]]],
        ];

        foreach ($projets as [$code, $titre, $statut, $cout, $publie, $financements]) {
            $reseau = ReseauEau::where('code', $code)->first();
            if (! $reseau) {
                continue;
            }

            $projet = ProjetRenovation::updateOrCreate(
                ['reseau_id' => $reseau->id, 'titre' => $titre],
                [
                    'statut'      => $statut,
                    'cout_estime' => $cout,
                    'publie'      => $publie,
                    'description' => "[Démonstration] Projet fictif créé pour tester le module : travaux sur l'élément {$code} "
                        . "({$reseau->libelleType()} en {$reseau->libelleMateriau()}).",
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
