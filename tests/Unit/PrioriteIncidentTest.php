<?php

namespace Tests\Unit;

use App\Models\Incident;
use App\Models\Intervention;
use App\Models\ReseauEau;
use App\Services\PrioriteIncident;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

// Moteur de priorisation (binôme 2) : modèles construits en mémoire, aucune base de données utilisée
class PrioriteIncidentTest extends TestCase
{
    private function incident(array $valeurs, array $interventions = [], string $etatReseau = 'bon'): Incident
    {
        $incident = new Incident($valeurs + [
            'type' => 'fuite', 'gravite' => 'faible', 'date_signalement' => today()->toDateString(),
        ]);
        $incident->setRelation('reseau', new ReseauEau(['etat' => $etatReseau]));
        $incident->setRelation('interventions', new Collection(array_map(fn ($i) => new Intervention($i), $interventions)));

        return $incident;
    }

    private function points(array $priorite, string $cle): int
    {
        return collect($priorite['facteurs'])->firstWhere('cle', $cle)['points'];
    }

    public function test_les_maximums_du_bareme_font_100(): void
    {
        $priorite = PrioriteIncident::calculer($this->incident([]));

        $this->assertSame(100, array_sum(array_column($priorite['facteurs'], 'max')));
    }

    public function test_cas_le_plus_grave_atteint_100_et_est_urgent(): void
    {
        $priorite = PrioriteIncident::calculer($this->incident([
            'type' => 'contamination', 'gravite' => 'critique', 'date_signalement' => today()->subDays(40)->toDateString(),
        ], [], 'hors_service'));

        $this->assertSame(100, $priorite['score']);
        $this->assertSame('urgente', $priorite['niveau']['cle']);
        $this->assertStringContainsString('immédiatement', $priorite['action']);
        $this->assertStringContainsString("analyse de contrôle", $priorite['action']);
    }

    public function test_detail_d_un_cas_simple(): void
    {
        // fuite (10) + faible (10) + signalé il y a 6 jours (3) + planifiée à venir (8) + réseau dégradé (3) = 34
        $priorite = PrioriteIncident::calculer($this->incident(
            ['date_signalement' => today()->subDays(6)->toDateString()],
            [['statut' => 'planifiee', 'date_intervention' => today()->addDays(2)->toDateString()]],
            'degrade',
        ));

        $this->assertSame(10, $this->points($priorite, 'gravite'));
        $this->assertSame(10, $this->points($priorite, 'type'));
        $this->assertSame(3, $this->points($priorite, 'anciennete'));
        $this->assertSame(8, $this->points($priorite, 'prise_en_charge'));
        $this->assertSame(3, $this->points($priorite, 'etat_reseau'));
        $this->assertSame(34, $priorite['score']);
        $this->assertSame('normale', $priorite['niveau']['cle']);
    }

    public function test_l_anciennete_est_plafonnee_a_15_points(): void
    {
        $priorite = PrioriteIncident::calculer($this->incident(['date_signalement' => today()->subDays(365)->toDateString()]));

        $this->assertSame(15, $this->points($priorite, 'anciennete'));
    }

    public function test_la_prise_en_charge_fait_baisser_la_priorite(): void
    {
        $sans = PrioriteIncident::calculer($this->incident([]));
        $enRetard = PrioriteIncident::calculer($this->incident([], [
            ['statut' => 'planifiee', 'date_intervention' => today()->subDays(3)->toDateString()],
        ]));
        $enCours = PrioriteIncident::calculer($this->incident([], [
            ['statut' => 'en_cours', 'date_intervention' => today()->toDateString()],
        ]));

        $this->assertSame(15, $this->points($sans, 'prise_en_charge'));
        $this->assertSame(12, $this->points($enRetard, 'prise_en_charge'));
        $this->assertStringContainsString('retard', collect($enRetard['facteurs'])->firstWhere('cle', 'prise_en_charge')['explication']);
        $this->assertSame(3, $this->points($enCours, 'prise_en_charge'));
    }

    public function test_un_incident_resolu_sort_du_classement(): void
    {
        $priorite = PrioriteIncident::calculer($this->incident(['gravite' => 'critique'], [
            ['statut' => 'terminee', 'date_intervention' => today()->toDateString()],
        ]));

        $this->assertTrue($priorite['resolu']);
        $this->assertSame(0, $priorite['score']);
        $this->assertNull($priorite['niveau']);
    }

    public function test_les_seuils_des_niveaux(): void
    {
        $this->assertSame('urgente', PrioriteIncident::niveau(100)['cle']);
        $this->assertSame('urgente', PrioriteIncident::niveau(70)['cle']);
        $this->assertSame('haute', PrioriteIncident::niveau(69)['cle']);
        $this->assertSame('haute', PrioriteIncident::niveau(45)['cle']);
        $this->assertSame('normale', PrioriteIncident::niveau(44)['cle']);
        $this->assertSame('normale', PrioriteIncident::niveau(25)['cle']);
        $this->assertSame('basse', PrioriteIncident::niveau(24)['cle']);
        $this->assertSame('basse', PrioriteIncident::niveau(0)['cle']);
    }

    public function test_chaque_facteur_est_explique(): void
    {
        $priorite = PrioriteIncident::calculer($this->incident(['type' => 'coupure', 'gravite' => 'moyenne']));

        foreach ($priorite['facteurs'] as $facteur) {
            $this->assertNotEmpty($facteur['explication']);
            $this->assertLessThanOrEqual($facteur['max'], $facteur['points']);
            $this->assertGreaterThanOrEqual(0, $facteur['points']);
        }
    }
}
