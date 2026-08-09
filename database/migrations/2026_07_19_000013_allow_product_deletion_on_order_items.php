<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'product_name')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('product_name')->nullable();
            });
        }

        DB::table('order_items')
            ->whereNotNull('product_id')
            ->whereNull('product_name')
            ->orderBy('id')
            ->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    $name = DB::table('products')->where('id', $item->product_id)->value('name');

                    if ($name) {
                        DB::table('order_items')->where('id', $item->id)->update(['product_name' => $name]);
                    }
                }
            });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            Schema::rename('order_items', 'order_items_old');

            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('product_name')->nullable();
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 10, 2);
                $table->decimal('subtotal', 10, 2);
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            DB::statement('
                INSERT INTO order_items (id, order_id, product_id, product_name, quantity, unit_price, subtotal, notes, created_at, updated_at)
                SELECT id, order_id, product_id, product_name, quantity, unit_price, subtotal, notes, created_at, updated_at
                FROM order_items_old
            ');

            Schema::drop('order_items_old');
            Schema::enableForeignKeyConstraints();

            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            Schema::rename('order_items', 'order_items_old');

            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 10, 2);
                $table->decimal('subtotal', 10, 2);
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            DB::statement('
                INSERT INTO order_items (id, order_id, product_id, quantity, unit_price, subtotal, notes, created_at, updated_at)
                SELECT id, order_id, product_id, quantity, unit_price, subtotal, notes, created_at, updated_at
                FROM order_items_old
                WHERE product_id IS NOT NULL
            ');

            Schema::drop('order_items_old');
            Schema::enableForeignKeyConstraints();

            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_name');
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }
};
