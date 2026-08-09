<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->decimal('min_km', 8, 2)->default(0)->after('name');
            $table->decimal('max_km', 8, 2)->nullable()->after('min_km');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->dropColumn(['min_km', 'max_km']);
        });
    }
};
