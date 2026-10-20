<?php

namespace Tests\Feature\Infrastructure;

use App\Models\ReseauEau;
use App\Models\Zone;
use App\Services\GroupesZones;
use App\Services\PlanRenovation;
use App\Services\ProbabiliteDefaillance;
use App\Services\ScoreRisque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Algorithmes du module : score de risque, loi de Weibull, K-means et plan de renouvellement (sac à dos)
class AlgorithmesRisqueTest extends TestCase
{
    use RefreshDatabase;

    private function element(Zone $zone, string $code, string $materiau, int $age, string $etat, string $type = 'canalisation'): ReseauEau
    {
        $reseau = $zone->reseaux()->create([
            'code' => $code, 'type' => $type, 'materiau' => $materiau,
            'date_mise_en_service' => now()->subYears($age)->subDay(), 'etat' => $etat,
        ]);

        return $reseau->setRelation('zone', $zone);
    }

    // ---------- Score de risque ----------

    public function test_score_de_risque_explique_facteur_par_facteur(): void
    {
        $zone = Zone::create(['nom' => 'Médina', 'commune' => 'Kairouan', 'population' => 35000]);
        $risque = ScoreRisque::pourReseau($this->element($zone, 'CAN-KAI-001', 'fonte', 66, 'hors_service'));

        // vétusté 35 (66 ans > 50 ans) + état 30 + durée 0 (panne d'aujourd'hui) + fonte 5 + population 11
        $this->assertSame([35, 30, 0, 5, 11], array_column($risque['facteurs'], 'points'));
        $this->assertSame(81, $risque['score']);
        $this->assertSame('critique', $risque['niveau']);
    }

    public function test_un_element_neuf_en_bon_etat_a_un_risque_faible(): void
    {
        $zone = Zone::create(['nom' => 'Skanes', 'commune' => 'Monastir', 'population' => 15000]);
        $risque = ScoreRisque::pourReseau($this->element($zone, 'CAN-MON-001', 'pehd', 3, 'bon'));

        $this->assertLessThan(30, $risque['score']);
        $this->assertSame('faible', $risque['niveau']);
    }

    // ---------- Loi de Weibull ----------

    public function test_probabilite_de_panne_weibull(): void
    {
        $zone = Zone::create(['nom' => 'A', 'commune' => 'Tunis', 'population' => 1000]);
        $vieux = ProbabiliteDefaillance::pourReseau($this->element($zone, 'C-1', 'fonte', 61, 'bon'));
        $vieuxDegrade = ProbabiliteDefaillance::pourReseau($this->element($zone, 'C-2', 'fonte', 61, 'degrade'));
        $neuf = ProbabiliteDefaillance::pourReseau($this->element($zone, 'C-3', 'pehd', 5, 'bon'));
        $enPanne = ProbabiliteDefaillance::pourReseau($this->element($zone, 'C-4', 'fonte', 61, 'hors_service'));

        $this->assertLessThan(1, $neuf['cinq_ans']);                                // PEHD récent : < 1 %
        $this->assertGreaterThan($neuf['cinq_ans'], $vieux['cinq_ans']);           // plus vieux = plus de risque
        $this->assertGreaterThan($vieux['cinq_ans'], $vieuxDegrade['cinq_ans']);   // dégradé = risque ×3
        $this->assertGreaterThan($vieuxDegrade['un_an'], $vieuxDegrade['cinq_ans']);
        $this->assertLessThan($neuf['vie_restante'], $vieuxDegrade['vie_restante']);
        $this->assertTrue($enPanne['horsService']);
        $this->assertNull($enPanne['cinq_ans']);
    }

    // ---------- K-means ----------

    public function test_kmeans_separe_les_zones_a_risque_des_zones_saines(): void
    {
        foreach (['Vieille ville 1', 'Vieille ville 2'] as $i => $nom) {
            $zone = Zone::create(['nom' => $nom, 'commune' => 'Tunis', 'population' => 40000]);
            $this->element($zone, "V-$i-1", 'fonte', 65, 'hors_service');
            $this->element($zone, "V-$i-2", 'amiante_ciment', 55, 'degrade');
        }
        foreach (['Quartier neuf 1', 'Quartier neuf 2'] as $i => $nom) {
            $zone = Zone::create(['nom' => $nom, 'commune' => 'Ariana', 'population' => 8000]);
            $this->element($zone, "N-$i-1", 'pehd', 4, 'bon');
        }
        Zone::create(['nom' => 'Sans réseau', 'commune' => 'Sfax', 'population' => 5000]);

        $resultat = GroupesZones::calculer();
        $groupe = fn (string $nom) => $resultat['parZone'][Zone::where('nom', $nom)->value('id')]['libelle'];

        $this->assertSame('Prioritaire', $groupe('Vieille ville 1'));
        $this->assertSame('Prioritaire', $groupe('Vieille ville 2'));
        $this->assertSame('Sain', $groupe('Quartier neuf 1'));
        $this->assertSame('Sain', $groupe('Quartier neuf 2'));
        $this->assertSame(['Sans réseau'], $resultat['sansReseau']->pluck('nom')->all());

        // Graine fixe : le même calcul donne toujours les mêmes groupes
        $this->assertSame($resultat['parZone'], GroupesZones::calculer()['parZone']);
    }

    // ---------- Plan de renouvellement (sac à dos) ----------

    public function test_le_plan_respecte_le_budget_et_fait_au_moins_aussi_bien_que_la_methode_simple(): void
    {
        $zone = Zone::create(['nom' => 'Hammam Lif Centre', 'commune' => 'Hammam Lif', 'population' => 27000]);
        $this->element($zone, 'CAN-HLF-001', 'fonte', 61, 'degrade');
        $this->element($zone, 'CAN-HLF-002', 'amiante_ciment', 56, 'degrade');
        $this->element($zone, 'RES-HLF-001', 'beton', 51, 'degrade', 'reservoir');
        $this->element($zone, 'STA-HLF-001', 'acier', 37, 'hors_service', 'station');

        foreach ([100000, 300000, 600000, 2000000] as $budget) {
            $plan = PlanRenovation::calculer($budget);

            $this->assertLessThanOrEqual($budget, $plan['cout'], "Budget dépassé pour $budget TND");
            $this->assertGreaterThanOrEqual($plan['naif']['gain'], $plan['gain'], "Moins bien que la méthode simple pour $budget TND");
        }

        // Budget plus petit que l'élément le moins cher : rien à rénover
        $this->assertTrue(PlanRenovation::calculer(50000)['choisis']->isEmpty());
    }
}
