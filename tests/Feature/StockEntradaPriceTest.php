<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockEntradaPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrada_with_cost_price_updates_ingredient_cost(): void
    {
        $admin = $this->admin();
        $ingredient = Ingredient::create([
            'name' => 'Farinha',
            'unit' => 'kg',
            'package_size' => 5,
            'cost_price' => 20,
            'current_stock' => 10,
            'minimum_stock' => 2,
        ]);

        $this->actingAs($admin)->post(route('ingredients.movement.store', $ingredient), [
            'type' => 'in',
            'quantity' => 5,
            'cost_price' => 24.50,
            'notes' => 'Compra mercado',
        ])->assertRedirect(route('ingredients.movement', $ingredient));

        $ingredient->refresh();
        $this->assertEquals(15.0, (float) $ingredient->current_stock);
        $this->assertEquals(24.50, (float) $ingredient->cost_price);

        $this->assertDatabaseHas('inventory_movements', [
            'ingredient_id' => $ingredient->id,
            'type' => 'in',
            'quantity' => 5,
            'cost_price' => 24.50,
            'reason' => 'manual',
        ]);
    }

    public function test_entrada_without_cost_price_keeps_existing_cost(): void
    {
        $admin = $this->admin();
        $ingredient = Ingredient::create([
            'name' => 'Óleo',
            'unit' => 'L',
            'package_size' => 0.9,
            'cost_price' => 8.90,
            'current_stock' => 3,
            'minimum_stock' => 1,
        ]);

        $this->actingAs($admin)->post(route('ingredients.movement.store', $ingredient), [
            'type' => 'in',
            'quantity' => 2,
        ])->assertRedirect();

        $ingredient->refresh();
        $this->assertEquals(5.0, (float) $ingredient->current_stock);
        $this->assertEquals(8.90, (float) $ingredient->cost_price);
        $this->assertNull(InventoryMovement::query()->first()->cost_price);
    }

    public function test_saida_ignores_cost_price(): void
    {
        $admin = $this->admin();
        $ingredient = Ingredient::create([
            'name' => 'Sal',
            'unit' => 'kg',
            'package_size' => 1,
            'cost_price' => 3,
            'current_stock' => 5,
            'minimum_stock' => 1,
        ]);

        $this->actingAs($admin)->post(route('ingredients.movement.store', $ingredient), [
            'type' => 'out',
            'quantity' => 1,
            'cost_price' => 99,
        ])->assertRedirect();

        $ingredient->refresh();
        $this->assertEquals(4.0, (float) $ingredient->current_stock);
        $this->assertEquals(3.0, (float) $ingredient->cost_price);
        $this->assertNull(InventoryMovement::query()->first()->cost_price);
    }

    public function test_prices_page_lists_ingredients(): void
    {
        $admin = $this->admin();
        Ingredient::create([
            'name' => 'Arroz',
            'unit' => 'kg',
            'package_size' => 5,
            'cost_price' => 28.90,
            'current_stock' => 10,
            'minimum_stock' => 2,
        ]);

        $this->actingAs($admin)
            ->get(route('ingredients.prices', ['q' => 'Arroz']))
            ->assertOk()
            ->assertSee('Preços de compra')
            ->assertSee('Arroz')
            ->assertSee('28,90');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
