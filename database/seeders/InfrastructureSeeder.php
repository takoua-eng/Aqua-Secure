<?php

namespace Database\Seeders;

use App\Models\ReseauEau;
use App\Models\User;
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

        // Le citoyen de démonstration habite le centre-ville : il reçoit les avis de coupure de cette zone
        User::where('email', 'citoyen@aquasecure.tn')
            ->update(['zone_id' => Zone::where('nom', 'Centre-ville')->where('commune', 'Tunis')->value('id')]);

        $this->coupures();
    }

    // Coupures programmées de démonstration : une à venir, une en cours, une terminée, une annulée
    private function coupures(): void
    {
        $demain = Carbon::tomorrow();
        $coupures = [
            ['RES-TUN-001', 'Nettoyage et désinfection du réservoir', 'Des citernes seront installées place Pasteur.',
                $demain->copy()->addDays(2)->setTime(8, 0), $demain->copy()->addDays(2)->setTime(16, 0), false],
            ['CAN-HLF-001', 'Remplacement d\'un tronçon de canalisation en fonte', null,
                Carbon::now()->subHours(2)->startOfHour(), Carbon::now()->addHours(6)->startOfHour(), false],
            ['CAN-MAR-001', 'Raccordement d\'un nouveau lotissement', null,
                Carbon::now()->subDays(6)->setTime(9, 0), Carbon::now()->subDays(6)->setTime(13, 0), false],
            ['CAN-TUN-002', 'Pose d\'un compteur de sectorisation', null,
                $demain->copy()->addDays(5)->setTime(9, 0), $demain->copy()->addDays(5)->setTime(12, 0), true],
        ];

        foreach ($coupures as [$code, $motif, $description, $debut, $fin, $annulee]) {
            $reseau = ReseauEau::where('code', $code)->first();
            if (! $reseau || $reseau->coupures()->where('motif', $motif)->exists()) {
                continue;
            }

            $coupure = $reseau->coupures()->create(compact('motif', 'description', 'debut', 'fin'));
            if ($annulee) {
                $coupure->forceFill(['annulee_le' => now()])->save();
            }
        }

        $this->commentaires();
    }

    // Commentaires et "j'aime" des citoyens de démonstration sur les coupures
    private function commentaires(): void
    {
        // Citoyen de démonstration + quelques habitants (mot de passe "password", email déjà vérifié)
        $demo = User::where('email', 'citoyen@aquasecure.tn')->first();
        if (! $demo) {
            return; // seeder lancé sans les comptes de démonstration (ex. dans les tests) : rien à créer
        }

        $habitants = collect([
            'Salma Ben Ali', 'Youssef Trabelsi', 'Amira Gharbi', 'Mehdi Jaziri',
            'Ines Bouazizi', 'Karim Mansouri', 'Rania Chaabane',
        ])->map(function (string $nom) {
            $email = str($nom)->lower()->ascii()->replace(' ', '.') . '@exemple.tn';
            $user = User::firstOrCreate(['email' => $email], ['nom' => $nom, 'password' => 'password', 'role' => 'citoyen']);
            $user->forceFill(['email_verified_at' => now()])->save();

            return $user;
        });

        $citoyens = collect([$demo])->merge($habitants)->values();

        // motif de la coupure => [ [n° du citoyen, texte, nombre de "j'aime", publié il y a (heures)] ]
        $donnees = [
            'Nettoyage et désinfection du réservoir' => [
                [0, "Merci pour l'avis ! Est-ce que les citernes seront aussi installées rue de Marseille ?", 3, 20],
                [1, 'Pensez à remplir vos bouteilles la veille : la dernière fois, la coupure a duré plus longtemps que prévu.', 5, 14],
                [2, "Très bonne initiative de prévenir trois jours avant, ça change tout pour s'organiser.", 2, 6],
            ],
            'Remplacement d\'un tronçon de canalisation en fonte' => [
                [3, "Plus d'eau depuis 21 h rue Ibn Khaldoun, c'est bien lié à ces travaux ?", 4, 2],
                [4, 'La pression était déjà très faible au 2e étage avant la coupure, espérons que ça règle le problème.', 1, 1],
                [5, 'Courage aux équipes qui travaillent de nuit !', 6, 1],
            ],
            'Raccordement d\'un nouveau lotissement' => [
                [6, "L'eau est revenue vers 13 h 30, merci d'avoir respecté les horaires.", 2, 140],
                [7, "Eau un peu trouble pendant dix minutes au retour : laissez couler le robinet avant de la boire.", 3, 139],
                [1, "Travaux propres, et il n'y a plus de fuite devant l'école.", 1, 130],
            ],
        ];

        foreach ($donnees as $motif => $commentaires) {
            $coupure = \App\Models\CoupureProgrammee::where('motif', $motif)->first();
            if (! $coupure || $coupure->commentaires()->exists()) {
                continue;
            }

            foreach ($commentaires as [$n, $texte, $nbLikes, $ilYa]) {
                $auteur = $citoyens[$n % $citoyens->count()];
                $commentaire = $coupure->commentaires()->create(['user_id' => $auteur->id, 'contenu' => $texte]);
                $date = now()->subHours($ilYa);
                $commentaire->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();

                // "J'aime" des autres citoyens (jamais l'auteur lui-même)
                $fans = $citoyens->where('id', '!=', $auteur->id)->take($nbLikes)->pluck('id');
                $commentaire->likes()->attach($fans);
            }
        }
    }
}
