<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->foreignId('stock_category_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('ingredient_id')->constrained()->nullOnDelete();
            $table->string('reason', 30)->default('manual')->after('type');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('inventory_deducted_at')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('inventory_deducted_at');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('reason');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_category_id');
        });

        Schema::dropIfExists('stock_categories');
    }
};
