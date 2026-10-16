<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('commune', 100);
            $table->unsignedInteger('population')->default(0); // habitants desservis
            $table->timestamps();

            // Une même zone ne peut pas exister deux fois dans la même commune
            $table->unique(['nom', 'commune']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
