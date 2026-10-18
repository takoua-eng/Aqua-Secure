<?php

namespace Tests\Unit;

use App\IA\Incidents\IsolationForest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

// Isolation Forest du binôme 2 : algorithme pur, sans Laravel ni base de données.
// Les données ci-dessous sont SYNTHÉTIQUES et construites pour tester l'algorithme, pas pour l'évaluer.
class IsolationForestTest extends TestCase
{
    // 100 points réguliers dans un carré 0..9 × 0..9, et un point très éloigné en dernière position
    private function donneesAvecIntrus(): array
    {
        $donnees = [];
        for ($i = 0; $i < 100; $i++) {
            $donnees[] = [$i % 10, intdiv($i, 10)];
        }
        $donnees[] = [60, 60];

        return $donnees;
    }

    public function test_c_correspond_aux_valeurs_de_reference(): void
    {
        $this->assertSame(0.0, IsolationForest::c(1));
        $this->assertSame(1.0, IsolationForest::c(2));
        // c(256) = 2(ln 255 + 0,5772) − 2·255/256 ≈ 10,24
        $this->assertEqualsWithDelta(10.24, IsolationForest::c(256), 0.01);
    }

    public function test_un_point_isole_a_le_score_le_plus_eleve(): void
    {
        $donnees = $this->donneesAvecIntrus();
        $foret = (new IsolationForest(100, 64, 2026))->entrainer($donnees);
        $scores = array_map(fn ($x) => $foret->score($x), $donnees);

        $intrus = array_pop($scores);
        $this->assertGreaterThan(max($scores), $intrus);
        $this->assertGreaterThan(0.6, $intrus);
        $this->assertLessThan(0.6, array_sum($scores) / count($scores));
    }

    public function test_meme_graine_memes_scores(): void
    {
        $donnees = $this->donneesAvecIntrus();
        $a = (new IsolationForest(50, 32, 7))->entrainer($donnees);
        $b = (new IsolationForest(50, 32, 7))->entrainer($donnees);

        foreach ([[0, 0], [5, 5], [60, 60]] as $x) {
            $this->assertSame($a->score($x), $b->score($x));
        }
    }

    public function test_des_donnees_toutes_identiques_ont_un_score_neutre(): void
    {
        $foret = (new IsolationForest(20, 16, 1))->entrainer(array_fill(0, 30, [3, 3, 3]));

        // Aucune coupure possible : chemin = c(ψ), donc score = 2^-1 = 0,5
        $this->assertEqualsWithDelta(0.5, $foret->score([3, 3, 3]), 1e-9);
    }

    public function test_export_puis_import_donne_les_memes_scores(): void
    {
        $foret = (new IsolationForest(30, 32, 5))->entrainer($this->donneesAvecIntrus());
        $copie = IsolationForest::importer(json_decode(json_encode($foret->exporter()), true));

        foreach ([[1, 2], [60, 60]] as $x) {
            $this->assertEqualsWithDelta($foret->score($x), $copie->score($x), 1e-9);
        }
    }

    public function test_il_faut_au_moins_deux_observations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new IsolationForest())->entrainer([[1, 2]]);
    }
}
