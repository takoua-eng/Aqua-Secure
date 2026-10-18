<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Binôme 3 — FINANCEMENT.
 * Colonnes du modèle officiel : id, projet_id, source (Etat, municipalite, bailleur, don), montant decimal(10,2).
 * Colonne ajoutée : simulation_id (facultative) = traçabilité quand le financement vient d'un scénario
 * What If? approuvé et appliqué par un administrateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financements', function (Blueprint $table) {
            $table->id();
            // Un projet qui a des financements ne peut pas être supprimé
            $table->foreignId('projet_id')->constrained('projets_renovation')->restrictOnDelete();
            $table->string('source', 20)->index();
            $table->decimal('montant', 10, 2);
            $table->foreignId('simulation_id')->nullable()->constrained('simulations_financement')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financements');
    }
};
