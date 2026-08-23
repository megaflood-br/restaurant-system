<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\StockCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_low_stock_items(): void
    {
        $admin = User::factory()->admin()->create();

        Ingredient::create([
            'name' => 'Farinha',
            'unit' => 'kg',
            'current_stock' => 2,
            'minimum_stock' => 5,
        ]);
        Ingredient::create([
            'name' => 'Óleo',
            'unit' => 'L',
            'current_stock' => 10,
            'minimum_stock' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('ingredients.index', ['stock' => 'low']))
            ->assertOk()
            ->assertSee('Farinha')
            ->assertDontSee('Óleo');
    }

    public function test_index_searches_by_name_and_filters_zero_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $category = StockCategory::create(['name' => 'Secos', 'is_active' => true]);

        Ingredient::create([
            'stock_category_id' => $category->id,
            'name' => 'Açúcar refinado',
            'unit' => 'kg',
            'current_stock' => 0,
            'minimum_stock' => 2,
            'cost_price' => 12.5,
        ]);
        Ingredient::create([
            'name' => 'Sal',
            'unit' => 'kg',
            'current_stock' => 4,
            'minimum_stock' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('ingredients.index', [
                'q' => 'açúcar',
                'stock' => 'zero',
                'stock_category' => $category->id,
            ]))
            ->assertOk()
            ->assertSee('Açúcar refinado')
            ->assertDontSee('Sal');
    }

    public function test_index_filters_items_without_price(): void
    {
        $admin = User::factory()->admin()->create();

        Ingredient::create([
            'name' => 'Arroz premium',
            'unit' => 'un',
            'current_stock' => 5,
            'minimum_stock' => 1,
            'cost_price' => 8,
        ]);
        Ingredient::create([
            'name' => 'Feijão básico',
            'unit' => 'un',
            'current_stock' => 5,
            'minimum_stock' => 1,
            'cost_price' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('ingredients.index', ['price' => 'without']))
            ->assertOk()
            ->assertSee('Feijão básico')
            ->assertDontSee('Arroz premium');
    }
}
