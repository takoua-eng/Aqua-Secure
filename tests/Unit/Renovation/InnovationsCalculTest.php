<?php

namespace Tests\Unit\Renovation;

use App\Services\Renovation\EquiteTerritoriale;
use App\Services\Renovation\MoteurWhatIf;
use App\Services\Renovation\PlanPluriannuel;
use App\Services\Renovation\RobustesseScenario;
use PHPUnit\Framework\TestCase;

// Binôme 3 — calculs purs des innovations : équité (stratégie E, Gini), robustesse, plan pluriannuel
class InnovationsCalculTest extends TestCase
{
    private function candidat(int $id, array $valeurs = []): array
    {
        return $valeurs + [
            'id' => $id, 'titre' => "Projet {$id}", 'reseau' => "R{$id}", 'zone' => 'Z', 'etat_reseau' => 'degrade',
            'statut' => 'planifie', 'score' => 50, 'cout' => 100000, 'cout_source' => 'declare', 'finance' => 0,
        ];
    }

    public function test_strategie_equite_finance_d_abord_le_gouvernorat_le_moins_dote(): void
    {
        $candidats = [
            // Gouvernorat déjà bien doté (50 DT/hab) mais projet très prioritaire
            $this->candidat(1, ['score' => 95, 'territoire' => 'Tunis', 'territoire_population' => 10000, 'territoire_finance' => 500000]),
            // Gouvernorat sans aucun financement
            $this->candidat(2, ['score' => 30, 'territoire' => 'Tataouine', 'territoire_population' => 10000, 'territoire_finance' => 0]),
        ];

        $r = MoteurWhatIf::comparer($candidats, 100000);

        $equite = array_column($r['scenarios']['equite']['lignes'], 'propose', 'id');
        $this->assertEquals(100000, $equite[2]);
        $this->assertEquals(0, $equite[1]);
        $this->assertSame(1, $r['scenarios']['equite']['kpi']['territoires_finances']);
        // La stratégie « optimal » fait l'inverse : c'est bien un compromis différent
        $this->assertEquals(100000, array_column($r['scenarios']['optimal']['lignes'], 'propose', 'id')[1]);
    }

    public function test_strategie_equite_respecte_le_budget(): void
    {
        $candidats = [];
        foreach (range(1, 12) as $i) {
            $candidats[] = $this->candidat($i, ['cout' => 30000 * $i, 'territoire' => 'G' . ($i % 4), 'territoire_population' => 1000 * ($i % 4 + 1)]);
        }
        foreach ([0, 50000, 333333.33, 5000000] as $budget) {
            $this->assertLessThanOrEqual($budget + 0.001, MoteurWhatIf::comparer($candidats, $budget, ['partiel' => true])['scenarios']['equite']['kpi']['alloue']);
        }
    }

    public function test_indice_de_gini(): void
    {
        $egal = EquiteTerritoriale::lorenz([['population' => 100, 'finance' => 1000], ['population' => 100, 'finance' => 1000]]);
        $this->assertEqualsWithDelta(0, $egal['gini'], 0.001);

        // La moitié de la population reçoit tout : Gini = 0,5 avec 2 territoires égaux en population
        $inegal = EquiteTerritoriale::lorenz([['population' => 100, 'finance' => 0], ['population' => 100, 'finance' => 1000]]);
        $this->assertEqualsWithDelta(0.5, $inegal['gini'], 0.001);
        $this->assertSame([[0.0, 0.0], [50.0, 0.0], [100.0, 100.0]], $inegal['points']);

        $this->assertNull(EquiteTerritoriale::lorenz([['population' => 100, 'finance' => 0]])['gini']);
        $this->assertStringContainsString('équilibrée', EquiteTerritoriale::lecture(0.1));
    }

    public function test_robustesse_sans_incertitude_et_reproductible(): void
    {
        $r = MoteurWhatIf::comparer([$this->candidat(1, ['cout' => 90000, 'cout_source' => 'estime']), $this->candidat(2, ['cout' => 10000])], 100000);
        $s = $r['scenarios']['degrades'];

        $sans = RobustesseScenario::evaluer($s, 100000, 0, 0);
        $this->assertSame(100.0, $sans['tient_pct']);
        $this->assertEquals(0, $sans['surcout_p95']);

        $a = RobustesseScenario::evaluer($s, 100000, 0.3, 0.1);
        $b = RobustesseScenario::evaluer($s, 100000, 0.3, 0.1);
        $this->assertSame($a, $b);                           // même graine => même résultat
        $this->assertLessThan(100, $a['tient_pct']);         // budget utilisé à 100 % : un surcoût le fait déborder
        $this->assertGreaterThan(0, $a['surcout_p95']);
    }

    public function test_robustesse_avec_marge_de_budget(): void
    {
        $r = MoteurWhatIf::comparer([$this->candidat(1, ['cout' => 50000])], 100000);

        // Le projet coûte 50 000 ± 10 % : le budget de 100 000 tient toujours
        $this->assertSame(100.0, RobustesseScenario::evaluer($r['scenarios']['degrades'], 100000, 0.3, 0.1)['tient_pct']);
    }

    public function test_plan_pluriannuel_un_projet_par_an(): void
    {
        $candidats = [$this->candidat(1, ['score' => 90]), $this->candidat(2, ['score' => 60]), $this->candidat(3, ['score' => 30])];

        $plan = PlanPluriannuel::calculer($candidats, 100000, 3, 'optimal', true, 2027);

        $this->assertSame(3, $plan['acheves']);
        $this->assertSame([2027, 2028, 2029], array_column($plan['annees'], 'annee'));
        $this->assertSame([1, 2, 3], array_map(fn ($a) => $a['projets'][0]['id'], $plan['annees']));   // ordre de priorité
        $this->assertSame([], $plan['restants']);
    }

    public function test_plan_pluriannuel_report_du_reliquat(): void
    {
        $candidats = [$this->candidat(1, ['cout' => 150000])];

        $sansReport = PlanPluriannuel::calculer($candidats, 100000, 2, 'degrades', false, 2027);
        $avecReport = PlanPluriannuel::calculer($candidats, 100000, 2, 'degrades', true, 2027);

        $this->assertSame(0, $sansReport['acheves']);
        $this->assertSame(1, $avecReport['acheves']);          // 100 000 + 100 000 reportés couvrent 150 000
        $this->assertEquals(200000, $avecReport['annees'][1]['budget']);
    }
}
