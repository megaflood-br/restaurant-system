<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('package_size', 10, 3)->nullable()->after('unit');
            $table->decimal('cost_price', 10, 2)->nullable()->after('package_size');
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->text('preparation_method')->nullable();
            $table->unsignedSmallInteger('yield_portions')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('recipe_ingredient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->unique(['recipe_id', 'ingredient_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('recipe_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });

        if (Schema::hasTable('product_ingredient')) {
            $productIds = DB::table('product_ingredient')->distinct()->pluck('product_id');

            foreach ($productIds as $productId) {
                $product = DB::table('products')->where('id', $productId)->first();

                if (! $product) {
                    continue;
                }

                $recipeId = DB::table('recipes')->insertGetId([
                    'product_id' => $productId,
                    'name' => $product->name,
                    'description' => $product->description,
                    'image' => $product->image,
                    'yield_portions' => 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('products')->where('id', $productId)->update(['recipe_id' => $recipeId]);

                $lines = DB::table('product_ingredient')->where('product_id', $productId)->get();

                foreach ($lines as $line) {
                    DB::table('recipe_ingredient')->insert([
                        'recipe_id' => $recipeId,
                        'ingredient_id' => $line->ingredient_id,
                        'quantity' => $line->quantity,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipe_id');
        });

        Schema::dropIfExists('recipe_ingredient');
        Schema::dropIfExists('recipes');

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['package_size', 'cost_price']);
        });
    }
};
