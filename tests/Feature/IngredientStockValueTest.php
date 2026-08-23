<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\StockCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientStockValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_total_stock_value_using_unit_cost(): void
    {
        $admin = User::factory()->admin()->create();

        // Pacote 5 kg a R$ 50 → R$ 10 / kg × 2 kg = R$ 20
        Ingredient::create([
            'name' => 'Farinha',
            'unit' => 'kg',
            'package_size' => 5,
            'cost_price' => 50,
            'current_stock' => 2,
            'minimum_stock' => 1,
        ]);

        // Pacote 1 L a R$ 12 → R$ 12 / L × 3 L = R$ 36
        Ingredient::create([
            'name' => 'Óleo',
            'unit' => 'L',
            'package_size' => 1,
            'cost_price' => 12,
            'current_stock' => 3,
            'minimum_stock' => 1,
        ]);

        // Sem preço — não entra no total
        Ingredient::create([
            'name' => 'Sal',
            'unit' => 'kg',
            'current_stock' => 10,
            'minimum_stock' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('ingredients.index'))
            ->assertOk()
            ->assertSee('Valor total em estoque')
            ->assertSee('R$ 56,00')
            ->assertSee('R$ 20,00')
            ->assertSee('R$ 36,00');
    }

    public function test_total_stock_value_respects_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $category = StockCategory::create(['name' => 'Secos', 'is_active' => true]);

        Ingredient::create([
            'stock_category_id' => $category->id,
            'name' => 'Açúcar',
            'unit' => 'kg',
            'package_size' => 1,
            'cost_price' => 4,
            'current_stock' => 5,
            'minimum_stock' => 1,
        ]);
        Ingredient::create([
            'name' => 'Leite',
            'unit' => 'L',
            'package_size' => 1,
            'cost_price' => 6,
            'current_stock' => 10,
            'minimum_stock' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('ingredients.index', ['stock_category' => $category->id]))
            ->assertOk()
            ->assertSee('R$ 20,00')
            ->assertDontSee('R$ 60,00');
    }
}
