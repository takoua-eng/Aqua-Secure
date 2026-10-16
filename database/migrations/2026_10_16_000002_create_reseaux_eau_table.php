<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseaux_eau', function (Blueprint $table) {
            $table->id();
            // Une zone qui contient encore des réseaux ne peut pas être supprimée
            $table->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $table->string('code', 30)->unique();          // ex : CAN-TUN-001
            $table->string('type', 20);                    // canalisation, reservoir, station
            $table->string('materiau', 20);                // fonte, acier, pvc, pehd, beton, amiante_ciment
            $table->date('date_mise_en_service');
            $table->string('etat', 20);                    // bon, degrade, hors_service
            $table->decimal('latitude', 10, 7)->nullable();  // position exacte (facultative)
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        // Historique des changements d'état, rempli automatiquement par le modèle ReseauEau
        Schema::create('historique_etats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseau_eau_id')->constrained('reseaux_eau')->cascadeOnDelete();
            $table->string('ancien_etat', 20)->nullable();  // null = création du réseau
            $table->string('nouvel_etat', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historique_etats');
        Schema::dropIfExists('reseaux_eau');
    }
};
