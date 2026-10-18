<?php

namespace Tests\Unit\Renovation;

use App\Services\Renovation\Apprentissage\BayesNaif;
use App\Services\Renovation\Apprentissage\PlusProchesVoisins;
use App\Services\Renovation\Apprentissage\RegressionRidge;
use PHPUnit\Framework\TestCase;

// Binôme 3 — algorithmes d'apprentissage codés sans bibliothèque
class ApprentissageTest extends TestCase
{
    public function test_ridge_retrouve_une_relation_lineaire(): void
    {
        // y = 3 + 2·x1 − 1·x2 (variables déjà centrées)
        $x = [[-1, 0], [0, 1], [1, -1], [2, 1], [-2, -1]];
        $y = array_map(fn ($v) => 3 + 2 * $v[0] - $v[1], $x);

        $m = RegressionRidge::entrainer($x, $y, 1e-9);

        $this->assertEqualsWithDelta(2, $m['poids'][0], 1e-6);
        $this->assertEqualsWithDelta(-1, $m['poids'][1], 1e-6);
        $this->assertEqualsWithDelta(3 + 2 * 0.5 - 0.5, RegressionRidge::predire($m, [0.5, 0.5]), 1e-6);
    }

    public function test_la_penalite_ridge_reduit_les_poids(): void
    {
        $x = [[-1], [0], [1]];
        $y = [-2.0, 0.0, 2.0];

        $faible = RegressionRidge::entrainer($x, $y, 0.001)['poids'][0];
        $forte = RegressionRidge::entrainer($x, $y, 100)['poids'][0];

        $this->assertLessThan(abs($faible), abs($forte));
        $this->assertEqualsWithDelta(2, $faible, 0.01);
    }

    public function test_plus_proches_voisins(): void
    {
        $x = [[0, 0], [0, 1], [10, 10], [11, 10]];
        $y = [1.0, 3.0, 100.0, 200.0];

        $r = PlusProchesVoisins::predire($x, $y, [0.2, 0.4], 2);

        $this->assertEqualsWithDelta(2.0, $r['valeur'], 1e-9);
        $this->assertSame([0, 1], $r['voisins']);
    }

    public function test_bayes_naif_apprend_une_regularite(): void
    {
        $exemples = [];
        $classes = [];
        foreach (range(1, 20) as $i) {
            $exemples[] = ['taille' => 'grand', 'region' => $i % 2 ? 'Sud' : 'Nord'];
            $classes[] = $i % 5 === 0 ? 'Etat' : 'bailleur';      // les gros projets : surtout des bailleurs
            $exemples[] = ['taille' => 'petit', 'region' => 'Nord'];
            $classes[] = $i % 5 === 0 ? 'Etat' : 'municipalite';  // les petits : surtout la municipalité
        }

        $m = BayesNaif::entrainer($exemples, $classes);
        $grand = BayesNaif::probabilites($m, ['taille' => 'grand', 'region' => 'Sud']);
        $petit = BayesNaif::probabilites($m, ['taille' => 'petit', 'region' => 'Nord']);

        $this->assertSame('bailleur', array_key_first($grand));
        $this->assertSame('municipalite', array_key_first($petit));
        $this->assertEqualsWithDelta(1.0, array_sum($grand), 1e-9);
        $this->assertGreaterThan(0, $grand['municipalite']);   // lissage de Laplace : jamais 0
        $this->assertEqualsWithDelta(0.8, BayesNaif::part($m, 'taille', 'grand', 'bailleur'), 1e-9);
    }

    public function test_bayes_naif_tolere_une_valeur_jamais_vue(): void
    {
        $m = BayesNaif::entrainer([['type' => 'station'], ['type' => 'canalisation']], ['bailleur', 'Etat']);

        $p = BayesNaif::probabilites($m, ['type' => 'barrage', 'inconnue' => 'x']);

        $this->assertEqualsWithDelta(1.0, array_sum($p), 1e-9);
    }
}
