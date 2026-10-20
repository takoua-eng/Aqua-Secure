<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Commentaires des citoyens sur une coupure programmée (visibles par tous)
        Schema::create('commentaires_coupure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupure_programmee_id')->constrained('coupures_programmees')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('contenu');
            $table->timestamps();
        });

        // "J'aime" sur un commentaire : un seul par citoyen et par commentaire
        Schema::create('likes_commentaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commentaire_coupure_id')->constrained('commentaires_coupure')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['commentaire_coupure_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes_commentaire');
        Schema::dropIfExists('commentaires_coupure');
    }
};
