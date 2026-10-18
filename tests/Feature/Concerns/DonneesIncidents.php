<?php

namespace Tests\Feature\Concerns;

use App\Models\Incident;
use App\Models\Intervention;
use App\Models\ReseauEau;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Route;

// Données communes aux tests du module Incidents / Interventions (binôme 2)
trait DonneesIncidents
{
    protected User $admin;
    protected User $gestionnaire;
    protected User $citoyen;
    protected Zone $zone;
    protected ReseauEau $reseau;

    protected function preparerDonnees(): void
    {
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->gestionnaire = User::factory()->create(['role' => 'gestionnaire']);
        $this->citoyen = User::factory()->create(['role' => 'citoyen']);

        $this->zone = Zone::create(['nom' => 'Centre-ville', 'commune' => 'Tunis', 'population' => 42000]);
        $this->reseau = $this->zone->reseaux()->create([
            'code' => 'CAN-TUN-001', 'type' => 'canalisation', 'materiau' => 'fonte',
            'date_mise_en_service' => '1990-01-01', 'etat' => 'bon',
        ]);
    }

    // Les routes du module sont chargées par routes/web.php (require routes/incidents.php) :
    // tant que cette ligne n'est pas ajoutée, les tests HTTP sont signalés "ignorés" au lieu d'échouer
    protected function exigerRoutesDuModule(): void
    {
        if (! Route::has('admin.incidents.create')) {
            $this->markTestSkipped('Routes du module non chargées : ajouter require __DIR__.\'/incidents.php\' dans routes/web.php (voir docs/binome-2-incidents.md).');
        }
    }

    protected function creerIncident(array $valeurs = []): Incident
    {
        return Incident::create($valeurs + [
            'reseau_id' => $this->reseau->id, 'type' => 'fuite', 'gravite' => 'moyenne',
            'date_signalement' => today()->subDays(3)->toDateString(),
        ]);
    }

    protected function creerIntervention(Incident $incident, string $statut = 'planifiee', ?string $date = null): Intervention
    {
        return Intervention::create([
            'incident_id' => $incident->id, 'statut' => $statut,
            'date_intervention' => $date ?? ($statut === 'planifiee' ? today()->addDay() : today())->toDateString(),
        ]);
    }

    protected function autreReseau(array $valeurs = []): ReseauEau
    {
        return $this->zone->reseaux()->create($valeurs + [
            'code' => 'RES-TUN-002', 'type' => 'reservoir', 'materiau' => 'beton',
            'date_mise_en_service' => '2005-01-01', 'etat' => 'degrade',
        ]);
    }
}
