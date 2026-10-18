<?php

namespace Tests\Unit\Renovation;

use App\Services\Renovation\DetectionRisquesFinanciers;
use PHPUnit\Framework\TestCase;

// Binôme 3 — règles de détection des risques financiers (sans IA, sans base)
class DetectionRisquesFinanciersTest extends TestCase
{
    private function projet(int $id, array $valeurs = []): array
    {
        return $valeurs + [
            'id' => $id, 'titre' => "Projet {$id}", 'statut' => 'planifie', 'cout' => 100000, 'cout_source' => 'declare',
            'finance' => 50000, 'nb_financements' => 2, 'sources' => ['Etat' => 25000, 'bailleur' => 25000], 'montants_invalides' => 0,
        ];
    }

    private function codes(array $alertes): array
    {
        return array_column($alertes, 'code');
    }

    public function test_aucune_alerte_sur_des_donnees_saines(): void
    {
        $this->assertSame([], DetectionRisquesFinanciers::detecter([
            $this->projet(1), $this->projet(2, ['sources' => ['municipalite' => 25000, 'don' => 25000]]),
        ]));
    }

    public function test_projet_sans_financement(): void
    {
        $alertes = DetectionRisquesFinanciers::detecter([
            $this->projet(1, ['statut' => 'en_cours', 'finance' => 0, 'nb_financements' => 0, 'sources' => []]),
            $this->projet(2, ['finance' => 0, 'nb_financements' => 0, 'sources' => []]),
        ]);

        $f1 = array_values(array_filter($alertes, fn ($a) => $a['code'] === 'F1'));
        $this->assertCount(2, $f1);
        $this->assertSame('danger', $f1[0]['niveau']);   // le chantier en cours passe en premier
        $this->assertSame(1, $f1[0]['projet_id']);
        $this->assertSame('warning', $f1[1]['niveau']);
    }

    public function test_projet_termine_sans_financement(): void
    {
        $alertes = DetectionRisquesFinanciers::detecter([$this->projet(1, ['statut' => 'termine', 'finance' => 0, 'nb_financements' => 0, 'sources' => []])]);

        $this->assertContains('F2', $this->codes($alertes));
        $this->assertNotContains('F1', $this->codes($alertes));
    }

    public function test_surfinancement_par_rapport_au_devis_et_a_l_estimation(): void
    {
        $this->assertContains('F3', $this->codes(DetectionRisquesFinanciers::detecter([$this->projet(1, ['finance' => 120000])])));
        $this->assertNotContains('F3', $this->codes(DetectionRisquesFinanciers::detecter([$this->projet(1, ['finance' => 100000])])));

        $estime = DetectionRisquesFinanciers::detecter([$this->projet(1, ['cout_source' => 'estime', 'finance' => 160000])]);
        $this->assertSame('warning', collect($estime)->firstWhere('code', 'F3')['niveau']);
        $this->assertNotContains('F3', $this->codes(DetectionRisquesFinanciers::detecter([$this->projet(1, ['cout_source' => 'estime', 'finance' => 140000])])));
    }

    public function test_montant_invalide(): void
    {
        $this->assertContains('F4', $this->codes(DetectionRisquesFinanciers::detecter([$this->projet(1, ['montants_invalides' => 1])])));
    }

    public function test_chantier_dependant_d_une_seule_source(): void
    {
        $alertes = DetectionRisquesFinanciers::detecter([$this->projet(1, ['statut' => 'en_cours', 'sources' => ['Etat' => 50000]])]);

        $this->assertContains('F5', $this->codes($alertes));
    }

    public function test_concentration_globale_des_sources(): void
    {
        $alertes = DetectionRisquesFinanciers::detecter([
            $this->projet(1, ['sources' => ['Etat' => 50000]]),
            $this->projet(2, ['sources' => ['Etat' => 40000, 'don' => 10000]]),
        ]);

        $f6 = collect($alertes)->firstWhere('code', 'F6');
        $this->assertNotNull($f6);
        $this->assertStringContainsString('90 %', $f6['message']);
    }

    public function test_couts_non_declares_et_orphelins(): void
    {
        $alertes = DetectionRisquesFinanciers::detecter([$this->projet(1, ['cout_source' => 'estime'])], ['orphelins' => 2]);

        $this->assertContains('F7', $this->codes($alertes));
        $this->assertContains('F8', $this->codes($alertes));
    }

    public function test_projet_termine_sous_finance(): void
    {
        $this->assertContains('F9', $this->codes(DetectionRisquesFinanciers::detecter([$this->projet(1, ['statut' => 'termine'])])));
    }
    public function test_montant_atypique_detecte_par_la_methode_de_tukey(): void
    {
        $montants = array_map(fn ($m) => ['projet_id' => 1, 'titre' => "P{$m}", 'source' => 'Etat', 'montant' => $m],
            [90000, 40000, 60000, 300000, 150000, 25000, 200000, 50000, 16000000]);

        $atypiques = DetectionRisquesFinanciers::montantsAtypiques($montants);
        $this->assertCount(1, $atypiques);
        $this->assertSame(16000000, $atypiques[0][0]['montant']);
        $this->assertContains('F10', $this->codes(DetectionRisquesFinanciers::detecter([], ['montants' => $montants])));
    }

    public function test_pas_de_montant_atypique_avec_trop_peu_de_donnees_ou_des_montants_reguliers(): void
    {
        $peu = array_map(fn ($m) => ['projet_id' => 1, 'titre' => 'P', 'source' => 'don', 'montant' => $m], [1000, 2000, 900000]);
        $this->assertSame([], DetectionRisquesFinanciers::montantsAtypiques($peu));

        $reguliers = array_map(fn ($m) => ['projet_id' => 1, 'titre' => 'P', 'source' => 'don', 'montant' => $m], range(100000, 900000, 100000));
        $this->assertSame([], DetectionRisquesFinanciers::montantsAtypiques($reguliers));
    }
}
