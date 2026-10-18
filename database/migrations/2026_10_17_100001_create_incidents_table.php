<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Exploitation (binôme 2) : incidents techniques confirmés sur un réseau d'eau
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            // Un réseau qui a des incidents ne peut pas être supprimé : on garde l'historique des incidents
            $table->foreignId('reseau_id')->constrained('reseaux_eau')->restrictOnDelete();
            $table->string('type', 20);                  // fuite, contamination, coupure
            $table->string('gravite', 20);               // faible, moyenne, critique
            $table->date('date_signalement');            // date de constat de l'incident
            $table->timestamps();

            // Filtres de la liste des incidents
            $table->index(['type', 'gravite']);
            $table->index('date_signalement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
