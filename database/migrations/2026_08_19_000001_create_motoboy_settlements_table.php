<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motoboy_settlements', function (Blueprint $table) {
            $table->id();
            $table->date('settlement_date')->unique();
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->decimal('delivery_fees_total', 10, 2)->default(0);
            $table->unsignedInteger('deliveries_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motoboy_settlements');
    }
};
