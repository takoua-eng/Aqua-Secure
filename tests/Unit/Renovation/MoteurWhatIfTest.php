<?php

namespace Tests\Unit\Renovation;

use App\Services\Renovation\MoteurWhatIf;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Binôme 3 — moteur What If? : calcul pur, sans base de données
class MoteurWhatIfTest extends TestCase
{
    private function candidat(int $id, array $valeurs = []): array
    {
        return $valeurs + [
            'id' => $id, 'titre' => "Projet {$id}", 'reseau' => "CAN-{$id}", 'zone' => 'Zone',
            'etat_reseau' => 'degrade', 'statut' => 'planifie', 'score' => 50,
            'cout' => 100000, 'cout_source' => 'declare', 'finance' => 0,
        ];
    }

    private function jeu(): array
    {
        return [
            $this->candidat(1, ['etat_reseau' => 'hors_service', 'score' => 80, 'cout' => 300000, 'finance' => 50000]),
            $this->candidat(2, ['etat_reseau' => 'degrade', 'score' => 60, 'cout' => 120000, 'cout_source' => 'estime']),
            $this->candidat(3, ['etat_reseau' => 'bon', 'score' => 30, 'cout' => 90000, 'finance' => 10000]),
            $this->candidat(4, ['etat_reseau' => 'degrade', 'score' => 55, 'cout' => 400000, 'cout_source' => 'estime']),
            $this->candidat(5, ['statut' => 'termine', 'score' => 10, 'cout' => 1000]),
        ];
    }

    public static function strategies(): array
    {
        return array_map(fn ($s) => [$s], array_combine(array_keys(MoteurWhatIf::STRATEGIES), array_keys(MoteurWhatIf::STRATEGIES)));
    }

    #[DataProvider('strategies')]
    public function test_aucune_strategie_ne_depasse_le_budget_ni_le_besoin(string $strategie): void
    {
        foreach ([0, 1234.56, 150000, 450000, 2000000] as $budget) {
            foreach ([false, true] as $partiel) {
                $s = MoteurWhatIf::comparer($this->jeu(), $budget, ['partiel' => $partiel])['scenarios'][$strategie];

                $this->assertLessThanOrEqual($budget + 0.001, $s['kpi']['alloue'], "budget {$budget}");
                foreach ($s['lignes'] as $l) {
                    $this->assertGreaterThanOrEqual(0, $l['propose']);
                    $this->assertLessThanOrEqual($l['besoin'] + 0.001, $l['propose']);
                }
            }
        }
    }

    public function test_les_resultats_sont_reproductibles(): void
    {
        $a = MoteurWhatIf::comparer($this->jeu(), 450000, ['partiel' => true]);
        $b = MoteurWhatIf::comparer(array_reverse($this->jeu()), 450000, ['partiel' => true]);

        $this->assertSame(json_encode($a), json_encode($b));
    }

    public function test_un_projet_termine_n_est_jamais_candidat(): void
    {
        $r = MoteurWhatIf::comparer($this->jeu(), 1000000);

        $this->assertSame(4, $r['candidats']);
        $this->assertSame(5, $r['ecartes'][0]['id']);
        $this->assertStringContainsString('terminé', $r['ecartes'][0]['motif']);
    }

    public function test_les_projets_exclus_par_le_gestionnaire_sont_ecartes(): void
    {
        $r = MoteurWhatIf::comparer($this->jeu(), 1000000, ['exclus' => [1, 3]]);

        $this->assertSame(2, $r['candidats']);
        $this->assertContains(1, array_column($r['ecartes'], 'id'));
        foreach ($r['scenarios'] as $s) {
            $this->assertNotContains(1, array_column($s['lignes'], 'id'));
        }
    }

    public function test_option_devis_uniquement_ecarte_les_couts_estimes(): void
    {
        $r = MoteurWhatIf::comparer($this->jeu(), 1000000, ['inclure_estimes' => false]);

        $ids = array_column($r['scenarios']['degrades']['lignes'], 'id');
        sort($ids);
        $this->assertSame([1, 3], $ids);
    }

    public function test_strategie_A_finance_d_abord_les_reseaux_hors_service(): void
    {
        $s = MoteurWhatIf::comparer($this->jeu(), 250000)['scenarios']['degrades'];
        $p1 = collect($s['lignes'])->firstWhere('id', 1);

        $this->assertSame('complet', $p1['etat']);
        $this->assertSame(250000.0, (float) $p1['propose']);
        $this->assertSame(1, $p1['rang']);
    }

    public function test_strategie_C_acheve_le_plus_de_projets(): void
    {
        $r = MoteurWhatIf::comparer($this->jeu(), 300000);
        $c = $r['scenarios']['max_projets']['kpi']['nb_complets'];

        foreach ($r['scenarios'] as $s) {
            $this->assertLessThanOrEqual($c, $s['kpi']['nb_complets']);
        }
        $this->assertContains('max_projets', $r['meilleurs']['nb_complets']);
    }

    public function test_strategie_B_repartit_proportionnellement_et_plafonne_au_besoin(): void
    {
        $candidats = [
            $this->candidat(1, ['score' => 75, 'cout' => 1000000]),
            $this->candidat(2, ['score' => 25, 'cout' => 1000000]),
        ];
        $s = MoteurWhatIf::comparer($candidats, 100000)['scenarios']['equilibre'];
        $parts = array_column($s['lignes'], 'propose', 'id');

        $this->assertEqualsWithDelta(75000, $parts[1], 0.01);
        $this->assertEqualsWithDelta(25000, $parts[2], 0.01);
        $this->assertSame(2, $s['kpi']['nb_partiels']);

        // Un petit besoin est plafonné ; son surplus est redistribué à l'autre projet
        $candidats[1]['cout'] = 5000;
        $s = MoteurWhatIf::comparer($candidats, 100000)['scenarios']['equilibre'];
        $parts = array_column($s['lignes'], 'propose', 'id');
        $this->assertEqualsWithDelta(5000, $parts[2], 0.01);
        $this->assertEqualsWithDelta(95000, $parts[1], 0.01);
        $this->assertEqualsWithDelta(100000, $s['kpi']['alloue'], 0.01);
    }

    public function test_strategie_D_est_au_moins_aussi_bonne_que_les_methodes_gloutonnes_en_priorite_achevee(): void
    {
        // Le glouton « plus haut score d'abord » prend 1 (score 90) et bloque ; l'optimum prend 2 + 3 (70 + 60)
        $candidats = [
            $this->candidat(1, ['score' => 90, 'cout' => 100000, 'etat_reseau' => 'hors_service']),
            $this->candidat(2, ['score' => 70, 'cout' => 50000]),
            $this->candidat(3, ['score' => 60, 'cout' => 50000]),
        ];
        $r = MoteurWhatIf::comparer($candidats, 100000);
        $somme = fn ($s) => collect($s['lignes'])->where('etat', 'complet')->sum('score');

        $this->assertSame(130, $somme($r['scenarios']['optimal']));
        foreach ($r['scenarios'] as $s) {
            $this->assertLessThanOrEqual($somme($r['scenarios']['optimal']), $somme($s));
        }
    }

    public function test_le_financement_partiel_n_est_jamais_presente_comme_complet(): void
    {
        $s = MoteurWhatIf::comparer($this->jeu(), 260000, ['partiel' => true])['scenarios']['degrades'];
        $partiels = collect($s['lignes'])->where('etat', 'partiel');

        $this->assertNotEmpty($partiels);
        foreach ($partiels as $l) {
            $this->assertLessThan($l['besoin'], $l['propose']);
            $this->assertStringContainsString('PARTIEL', $l['raison']);
        }
        $this->assertContains('warning', array_column($s['risques'], 'niveau'));
    }

    public function test_detecte_les_donnees_insuffisantes(): void
    {
        $r = MoteurWhatIf::comparer([$this->candidat(1, ['statut' => 'termine'])], 100000);
        $this->assertSame('insuffisant', $r['diagnostic']['niveau']);

        $r = MoteurWhatIf::comparer($this->jeu(), 0);
        $this->assertSame('insuffisant', $r['diagnostic']['niveau']);

        $r = MoteurWhatIf::comparer([$this->candidat(1, ['cout' => null, 'cout_source' => 'inconnu'])], 100000);
        $this->assertStringContainsString('Coût inconnu', $r['ecartes'][0]['motif']);

        $r = MoteurWhatIf::comparer($this->jeu(), 1000);
        $this->assertSame('attention', $r['diagnostic']['niveau']);
        $this->assertStringContainsString('plus petit reste à financer', implode(' ', $r['diagnostic']['messages']));
    }

    public function test_les_hypotheses_distinguent_le_budget_hypothetique(): void
    {
        $r = MoteurWhatIf::comparer($this->jeu(), 450000);

        $this->assertStringContainsString('hypothétique', $r['hypotheses'][0]);
        $this->assertStringContainsString('450 000 DT', $r['hypotheses'][0]);
    }

    public function test_budget_invalide_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MoteurWhatIf::comparer($this->jeu(), -1);
    }

    public function test_candidat_incomplet_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MoteurWhatIf::comparer([['id' => 1, 'titre' => 'X']], 1000);
    }

    public function test_recommandation_calculee_coherente_avec_les_indicateurs(): void
    {
        $r = MoteurWhatIf::comparer($this->jeu(), 450000);
        $meilleure = max(array_map(fn ($s) => $s['kpi']['priorite_couverte_pct'], $r['scenarios']));

        $this->assertSame($meilleure, $r['scenarios'][$r['recommande']]['kpi']['priorite_couverte_pct']);
        $this->assertStringContainsString('Règle de décision calculée', $r['raison']);
    }

    public function test_performance_sur_un_grand_portefeuille(): void
    {
        $candidats = [];
        for ($i = 1; $i <= 200; $i++) {
            $candidats[] = $this->candidat($i, ['score' => $i % 100, 'cout' => 1000 * (($i * 37) % 900 + 50)]);
        }

        $debut = microtime(true);
        $r = MoteurWhatIf::comparer($candidats, MoteurWhatIf::BUDGET_MAX);
        $this->assertLessThan(5, microtime(true) - $debut);
        $this->assertSame(200, $r['scenarios']['optimal']['kpi']['nb_complets']);
    }
}
