<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Binôme 3 — simulations du What If? Engine.
 * Une simulation est une hypothèse : elle ne touche jamais aux financements réels tant qu'un administrateur
 * ne l'a pas approuvée PUIS appliquée. Le cycle : brouillon → soumise → approuvee / rejetee → appliquee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulations_financement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();   // auteur
            $table->string('nom', 120);
            $table->decimal('budget', 12, 2);                 // budget HYPOTHÉTIQUE saisi dans le simulateur
            $table->string('strategie', 30);                  // stratégie retenue par l'auteur
            $table->json('parametres');                       // projets exclus, options
            $table->json('resultats');                        // comparaison complète calculée par le moteur
            $table->string('empreinte', 64);                  // empreinte des données réelles au moment du calcul
            $table->json('analyse_ia')->nullable();           // interprétation IA (ou mode secours), validée
            $table->string('statut', 20)->default('brouillon')->index();
            $table->string('source_financement', 20)->nullable();   // choisie par l'admin à l'approbation
            $table->foreignId('decide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('soumise_le')->nullable();
            $table->timestamp('decide_le')->nullable();
            $table->text('commentaire_decision')->nullable();
            $table->timestamp('appliquee_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulations_financement');
    }
};
