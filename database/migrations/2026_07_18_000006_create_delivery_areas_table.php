<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_area_id')->nullable()->after('table_number')->constrained()->nullOnDelete();
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('delivery_area_id');
            $table->string('delivery_address')->nullable()->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_area_id');
            $table->dropColumn(['delivery_fee', 'delivery_address']);
        });

        Schema::dropIfExists('delivery_areas');
    }
};
