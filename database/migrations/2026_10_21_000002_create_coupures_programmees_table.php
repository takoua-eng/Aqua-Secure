<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Coupures d'eau planifiées pour travaux (différent des incidents, qui sont imprévus)
        Schema::create('coupures_programmees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseau_eau_id')->constrained('reseaux_eau')->cascadeOnDelete();
            $table->string('motif', 150);
            $table->text('description')->nullable();
            $table->dateTime('debut');
            $table->dateTime('fin');
            $table->timestamp('annulee_le')->nullable();        // null = coupure maintenue
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // qui l'a planifiée
            $table->timestamps();

            $table->index(['debut', 'fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupures_programmees');
    }
};
