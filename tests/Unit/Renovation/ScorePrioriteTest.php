<?php

namespace Tests\Unit\Renovation;

use App\Services\Renovation\ScorePriorite;
use PHPUnit\Framework\TestCase;

// Binôme 3 — score de priorité : règles explicables (risque 40, avancement 20, reste 25, capteurs 8, qualité 7)
class ScorePrioriteTest extends TestCase
{
    public function test_le_score_est_la_somme_des_facteurs_expliques(): void
    {
        $r = ScorePriorite::calculer(80, 'en_cours', 200000, 50000, 'declare');

        // 40 × 0,8 = 32 ; en cours 20 ; reste 75 % × 25 = 18,75 → 19 ; aucun signal terrain → 0
        $this->assertSame(71, $r['score']);
        $this->assertSame(array_sum(array_column($r['facteurs'], 'points')), $r['score']);
        $this->assertSame('prioritaire', $r['niveau']);
        $this->assertCount(5, $r['facteurs']);
    }

    public function test_les_signaux_des_capteurs_et_de_la_qualite_comptent(): void
    {
        $sans = ScorePriorite::calculer(50, 'planifie', 100000, 0, 'declare');
        $avec = ScorePriorite::calculer(50, 'planifie', 100000, 0, 'declare',
            ['capteurs' => 3, 'alertes' => 6, 'analyses' => 10, 'non_conformes' => 5]);

        // + 8 (6 alertes) + round(7 × 5/10) = 4
        $this->assertSame($sans['score'] + 12, $avec['score']);
        $this->assertStringContainsString('6 relevé(s) hors seuil', implode(' ', array_column($avec['facteurs'], 'libelle')));

        $peu = ScorePriorite::calculer(50, 'planifie', 100000, 0, 'declare', ['capteurs' => 1, 'alertes' => 2]);
        $this->assertSame($sans['score'] + 4, $peu['score']);
    }

    public function test_absence_de_capteur_est_signalee_comme_limite(): void
    {
        $r = ScorePriorite::calculer(50, 'planifie', 100000, 0, 'declare');

        $this->assertStringContainsString('Pas de capteur', implode(' ', $r['limites']));
    }

    public function test_un_projet_termine_et_finance_a_une_priorite_basse(): void
    {
        $r = ScorePriorite::calculer(10, 'termine', 100000, 100000, 'declare');

        $this->assertSame(4, $r['score']);
        $this->assertSame('basse', $r['niveau']);
    }

    public function test_cout_inconnu_ne_donne_aucun_point_et_le_signale(): void
    {
        $r = ScorePriorite::calculer(60, 'planifie', null, 0, 'inconnu');

        $this->assertSame(34, $r['score']);
        $this->assertStringContainsString('inconnu', implode(' ', $r['limites']));
    }

    public function test_cout_estime_est_signale_comme_limite(): void
    {
        $r = ScorePriorite::calculer(60, 'planifie', 100000, 0, 'estime');

        $this->assertStringContainsString('estimé', implode(' ', $r['limites']));
    }

    public function test_le_score_reste_entre_0_et_100(): void
    {
        $max = ['capteurs' => 1, 'alertes' => 50, 'analyses' => 4, 'non_conformes' => 4];
        $this->assertSame(100, ScorePriorite::calculer(500, 'en_cours', 100, 0, 'declare', $max)['score']);
        $this->assertSame(0, ScorePriorite::calculer(-20, 'termine', 100, 500, 'declare')['score']);
    }

    public function test_le_score_ne_pretend_pas_mesurer_une_probabilite_de_panne(): void
    {
        $limites = implode(' ', ScorePriorite::calculer(50, 'planifie', 1000, 0, 'declare')['limites']);

        $this->assertStringContainsString('probabilité de panne', $limites);
    }
}
