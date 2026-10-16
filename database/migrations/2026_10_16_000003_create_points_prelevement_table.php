<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_prelevement', function (Blueprint $table) {
            $table->id();
            // Un réseau qui a encore des points de prélèvement ne peut pas être supprimé
            $table->foreignId('reseau_id')->constrained('reseaux_eau')->restrictOnDelete();
            $table->string('code', 30)->unique();           // ex : PP-TN-012
            $table->string('nom', 100);
            $table->string('adresse', 255);
            $table->decimal('latitude', 10, 7)->nullable();   // position exacte (facultative)
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('type_point', 20);               // robinet_public, reservoir, sortie_station
            $table->boolean('actif')->default(true);        // false = hors service
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_prelevement');
    }
};
