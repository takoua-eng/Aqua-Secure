<?php

namespace Tests\Unit\Renovation;

use App\Services\Renovation\ReponseIAInvalide;
use App\Services\Renovation\ValidationReponseIA;
use PHPUnit\Framework\TestCase;

// Binôme 3 — garde-fous appliqués aux réponses de l'IA
class ValidationReponseIATest extends TestCase
{
    private const STRATEGIES = ['degrades', 'equilibre', 'max_projets', 'optimal'];

    private function reponse(array $valeurs = []): array
    {
        return $valeurs + [
            'synthese'           => 'La stratégie A finance 3 projets et couvre 75,6 % de la priorité.',
            'recommandation'     => ['strategie' => 'degrades', 'justification' => 'Elle traite les réseaux hors service en premier.'],
            'strategies'         => [['strategie' => 'equilibre', 'points_forts' => ['Avance tous les projets.'], 'compromis' => [], 'risques' => ['Beaucoup de partiels.']]],
            'donnees_manquantes' => ['Devis du projet 2.'],
            'ameliorations'      => [],
            'projets_cites'      => [1, 2],
        ];
    }

    private function valider(array $reponse): array
    {
        return ValidationReponseIA::scenarios($reponse, self::STRATEGIES, [1, 2, 3], [450000.0, 75.6, 250000.0]);
    }

    public function test_une_reponse_conforme_est_acceptee(): void
    {
        $r = $this->valider($this->reponse());

        $this->assertSame('degrades', $r['recommandation']['strategie']);
        $this->assertSame([1, 2], $r['projets_cites']);
    }

    public function test_un_chiffre_invente_est_refuse(): void
    {
        $this->expectException(ReponseIAInvalide::class);
        $this->expectExceptionMessage('chiffres absents');

        $this->valider($this->reponse(['synthese' => 'Il faudrait 180 000 DT de plus pour tout financer.']));
    }

    public function test_les_chiffres_presents_dans_les_donnees_sont_acceptes_quel_que_soit_le_format(): void
    {
        $r = $this->valider($this->reponse(['synthese' => 'Budget de 450 000 DT dont 250 000,00 DT au projet 1 (75,6 %).']));

        $this->assertStringContainsString('450 000', $r['synthese']);
    }

    public function test_une_strategie_inconnue_est_refusee(): void
    {
        $this->expectException(ReponseIAInvalide::class);
        $this->valider($this->reponse(['recommandation' => ['strategie' => 'tout_donner', 'justification' => 'x']]));
    }

    public function test_un_projet_inexistant_est_refuse(): void
    {
        $this->expectException(ReponseIAInvalide::class);
        $this->valider($this->reponse(['projets_cites' => [99]]));
    }

    public function test_une_reponse_incomplete_est_refusee(): void
    {
        $reponse = $this->reponse();
        unset($reponse['ameliorations']);

        $this->expectException(ReponseIAInvalide::class);
        $this->valider($reponse);
    }

    public function test_un_texte_trop_long_est_refuse(): void
    {
        $this->expectException(ReponseIAInvalide::class);
        $this->valider($this->reponse(['synthese' => str_repeat('a', ValidationReponseIA::LONGUEUR_MAX + 1)]));
    }

    public function test_une_reponse_qui_n_est_pas_un_objet_est_refusee(): void
    {
        $this->expectException(ReponseIAInvalide::class);
        ValidationReponseIA::scenarios('Je recommande la stratégie A.', self::STRATEGIES, [1], []);
    }

    public function test_les_alertes_citees_doivent_avoir_ete_detectees(): void
    {
        $ok = ValidationReponseIA::alertes(['synthese' => 'Deux alertes.', 'actions' => [['code' => 'F1', 'action' => 'Chercher un bailleur.']], 'donnees_manquantes' => []], ['F1'], []);
        $this->assertSame('F1', $ok['actions'][0]['code']);

        $this->expectException(ReponseIAInvalide::class);
        ValidationReponseIA::alertes(['synthese' => 'x', 'actions' => [['code' => 'F42', 'action' => 'y']], 'donnees_manquantes' => []], ['F1'], []);
    }

    public function test_extraction_des_nombres_autorises(): void
    {
        $nombres = ValidationReponseIA::nombres(['budget' => 450000, 'texte' => 'Budget non utilisé : 12 500,50 DT']);

        $this->assertContains(450000.0, $nombres);
        $this->assertContains(12500.5, $nombres);
        $this->assertSame([], ValidationReponseIA::chiffresNonVerifies('Il reste 12 500,50 DT et 3 projets.', $nombres));
    }
}
