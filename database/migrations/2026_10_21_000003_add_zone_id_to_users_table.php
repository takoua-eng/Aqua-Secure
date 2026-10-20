<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Zone d'habitation du citoyen : il est prévenu des coupures programmées dans cette zone
            $table->foreignId('zone_id')->nullable()->after('role')->constrained('zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
        });
    }
};
