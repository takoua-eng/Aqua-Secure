<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capteurs', function (Blueprint $table) {
            $table->id();
            // Réseau équipé (binôme 1). Si le réseau est supprimé, ses capteurs le sont aussi.
            $table->foreignId('reseau_id')->constrained('reseaux_eau')->cascadeOnDelete();
            $table->string('code', 30)->unique();        // ex : CAP-TN-001
            $table->string('modele', 100);               // modèle ou fabricant
            $table->string('type_mesure', 20);           // pression, debit, niveau, temperature
            $table->string('unite', 10);                 // bar, m3/h, m, °C
            $table->float('seuil_min');                  // valeur minimale normale
            $table->float('seuil_max');                  // valeur maximale normale
            $table->date('date_installation');
            $table->string('statut', 20);                // actif, en_panne, hors_service
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capteurs');
    }
};