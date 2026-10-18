<?php

namespace Tests\Unit;

use App\Models\Incident;
use App\Models\Intervention;
use PHPUnit\Framework\TestCase;

// Règles de statut du module Incidents / Interventions (binôme 2) : logique pure, sans base de données
class RegleStatutTest extends TestCase
{
    // ---------- Transitions des interventions (règle T5) ----------

    public function test_une_intervention_planifiee_peut_demarrer_ou_etre_terminee(): void
    {
        $this->assertTrue(Intervention::transitionAutorisee('planifiee', 'en_cours'));
        $this->assertTrue(Intervention::transitionAutorisee('planifiee', 'terminee'));
    }

    public function test_une_intervention_en_cours_peut_seulement_etre_terminee(): void
    {
        $this->assertTrue(Intervention::transitionAutorisee('en_cours', 'terminee'));
        $this->assertFalse(Intervention::transitionAutorisee('en_cours', 'planifiee'));
    }

    public function test_une_intervention_terminee_est_definitive(): void
    {
        foreach (array_keys(Intervention::STATUTS) as $statut) {
            $this->assertFalse(Intervention::transitionAutorisee('terminee', $statut));
        }
    }

    public function test_rester_dans_le_meme_statut_n_est_pas_une_transition(): void
    {
        foreach (array_keys(Intervention::STATUTS) as $statut) {
            $this->assertFalse(Intervention::transitionAutorisee($statut, $statut));
        }
    }

    public function test_a_la_creation_seuls_les_statuts_connus_sont_acceptes(): void
    {
        foreach (array_keys(Intervention::STATUTS) as $statut) {
            $this->assertTrue(Intervention::transitionAutorisee(null, $statut));
        }
        $this->assertFalse(Intervention::transitionAutorisee(null, 'annulee'));
        $this->assertFalse(Intervention::transitionAutorisee('inconnu', 'terminee'));
    }

    // ---------- Statut calculé d'un incident ----------

    public function test_un_incident_sans_intervention_est_non_traite(): void
    {
        $this->assertSame('non_traite', Incident::statutSelon([]));
    }

    public function test_une_intervention_planifiee_ou_en_cours_met_l_incident_en_traitement(): void
    {
        $this->assertSame('en_traitement', Incident::statutSelon(['planifiee']));
        $this->assertSame('en_traitement', Incident::statutSelon(['en_cours']));
        $this->assertSame('en_traitement', Incident::statutSelon(['terminee', 'en_cours']));
    }

    public function test_un_incident_dont_toutes_les_interventions_sont_terminees_est_resolu(): void
    {
        $this->assertSame('resolu', Incident::statutSelon(['terminee']));
        $this->assertSame('resolu', Incident::statutSelon(['terminee', 'terminee']));
    }

    // Décision R1 : une nouvelle intervention rouvre un incident résolu
    public function test_une_nouvelle_intervention_rouvre_un_incident_resolu(): void
    {
        $this->assertSame('en_traitement', Incident::statutSelon(['terminee', 'planifiee']));
    }

    public function test_chaque_statut_calcule_a_un_libelle(): void
    {
        foreach (['non_traite', 'en_traitement', 'resolu'] as $statut) {
            $this->assertArrayHasKey($statut, Incident::STATUTS);
        }
    }
}
