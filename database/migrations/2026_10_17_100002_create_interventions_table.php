<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Exploitation (binôme 2) : interventions effectuées suite à un incident
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            // Un incident qui a des interventions ne peut pas être supprimé (traçabilité)
            $table->foreignId('incident_id')->constrained('incidents')->restrictOnDelete();
            $table->string('statut', 20);                // planifiee, en_cours, terminee
            $table->date('date_intervention');           // date prévue (planifiée) ou date d'exécution
            $table->timestamps();

            // Statut calculé des incidents et filtre "en retard"
            $table->index(['incident_id', 'statut']);
            $table->index('date_intervention');
        });

        // Historique des changements de statut, rempli automatiquement par le modèle Intervention
        Schema::create('historique_interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervention_id')->constrained('interventions')->cascadeOnDelete();
            $table->string('ancien_statut', 20)->nullable();  // null = création de l'intervention
            $table->string('nouveau_statut', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historique_interventions');
        Schema::dropIfExists('interventions');
    }
};
