<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses_qualite', function (Blueprint $table) {
            $table->id();
            // Un point qui a des analyses ne peut pas être supprimé (on le met hors service)
            $table->foreignId('point_id')->constrained('points_prelevement')->restrictOnDelete();
            $table->date('date_prelevement');
            $table->decimal('ph', 4, 2);
            $table->decimal('chlore_residuel', 5, 2);           // mg/L
            $table->decimal('turbidite', 6, 2);                 // NTU
            $table->decimal('nitrates', 6, 2);                  // mg/L
            $table->unsignedInteger('bacteries_coliformes');    // UFC/100 mL
            $table->boolean('conforme')->default(false);        // calculé par le modèle AnalyseQualite
            $table->string('laboratoire', 150);
            $table->timestamps();

            $table->index(['point_id', 'date_prelevement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses_qualite');
    }
};
