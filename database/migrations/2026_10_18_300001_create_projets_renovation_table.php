<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Binôme 3 — PROJET_RENOVATION.
 * Colonnes du modèle officiel : id, reseau_id, titre, statut.
 * Colonnes ajoutées (toutes facultatives) :
 *   cout_estime : coût total déclaré par le gestionnaire (devis). Sans lui, aucun taux de couverture
 *                 « fiable » n'est calculé : l'application affiche seulement une estimation signalée comme telle.
 *   description : texte libre, affiché sur la page publique.
 *   publie      : le projet est visible par les citoyens (page de transparence). Non publié par défaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projets_renovation', function (Blueprint $table) {
            $table->id();
            // Un réseau qui a des projets de rénovation ne peut pas être supprimé (les financements sont des pièces comptables)
            $table->foreignId('reseau_id')->constrained('reseaux_eau')->restrictOnDelete();
            $table->string('titre', 150);
            $table->string('statut', 20)->default('planifie')->index();   // planifie, en_cours, termine
            $table->decimal('cout_estime', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('publie')->default(false)->index();
            $table->timestamps();

            $table->unique(['reseau_id', 'titre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projets_renovation');
    }
};
