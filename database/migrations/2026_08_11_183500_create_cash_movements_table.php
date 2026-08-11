<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10); // entrada | saida
            $table->string('category', 40);
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 20)->nullable();
            $table->string('description')->nullable();
            $table->timestamp('occurred_at');
            $table->date('reference_date')->index();
            $table->string('source', 20)->default('manual'); // manual | comanda | order
            $table->string('source_key', 80)->nullable();
            $table->unsignedInteger('comanda_number')->nullable()->index();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_key']);
            $table->index(['reference_date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
