<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releves', function (Blueprint $table) {
            $table->id();
            // Capteur émetteur. Si le capteur est supprimé, ses relevés le sont aussi.
            $table->foreignId('capteur_id')->constrained('capteurs')->cascadeOnDelete();
            $table->float('valeur');                         // valeur mesurée
            $table->dateTime('date_releve');                 // horodatage de la mesure
            $table->boolean('hors_seuil')->default(false);   // calculé automatiquement par le modèle
            $table->string('remarque', 255)->nullable();     // observation du technicien
            $table->timestamps();

            // Accélère les recherches "relevés d'un capteur triés par date"
            $table->index(['capteur_id', 'date_releve']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releves');
    }
};