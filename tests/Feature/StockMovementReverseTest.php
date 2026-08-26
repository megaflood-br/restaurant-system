<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementReverseTest extends TestCase
{
    use RefreshDatabase;

    public function test_reversing_manual_entrada_restores_stock_and_previous_cost(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $ingredient = Ingredient::create([
            'name' => 'Farinha',
            'unit' => 'kg',
            'package_size' => 5,
            'cost_price' => null,
            'current_stock' => 10,
            'minimum_stock' => 2,
        ]);

        $this->actingAs($admin)->post(route('ingredients.movement.store', $ingredient), [
            'type' => 'in',
            'quantity' => 1,
            'cost_price' => 20,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('ingredients.movement.store', $ingredient), [
            'type' => 'in',
            'quantity' => 5,
            'cost_price' => 30,
        ])->assertRedirect();

        $ingredient->refresh();
        $this->assertEquals(16.0, (float) $ingredient->current_stock);
        $this->assertEquals(30.0, (float) $ingredient->cost_price);

        $movement = InventoryMovement::query()->where('ingredient_id', $ingredient->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('ingredients.movement.destroy', [$ingredient, $movement]))
            ->assertRedirect(route('ingredients.movement', $ingredient));

        $ingredient->refresh();
        $this->assertEquals(11.0, (float) $ingredient->current_stock);
        $this->assertEquals(20.0, (float) $ingredient->cost_price);
        $this->assertDatabaseMissing('inventory_movements', ['id' => $movement->id]);
    }

    public function test_reversing_manual_saida_restores_stock(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $ingredient = Ingredient::create([
            'name' => 'Óleo',
            'unit' => 'L',
            'package_size' => 1,
            'cost_price' => 12,
            'current_stock' => 8,
            'minimum_stock' => 1,
        ]);

        $this->actingAs($admin)->post(route('ingredients.movement.store', $ingredient), [
            'type' => 'out',
            'quantity' => 3,
        ])->assertRedirect();

        $movement = InventoryMovement::query()->where('ingredient_id', $ingredient->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('ingredients.movement.destroy', [$ingredient, $movement]))
            ->assertRedirect();

        $ingredient->refresh();
        $this->assertEquals(8.0, (float) $ingredient->current_stock);
        $this->assertDatabaseMissing('inventory_movements', ['id' => $movement->id]);
    }

    public function test_cannot_reverse_sale_movement(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $ingredient = Ingredient::create([
            'name' => 'Sal',
            'unit' => 'kg',
            'package_size' => 1,
            'cost_price' => 3,
            'current_stock' => 5,
            'minimum_stock' => 1,
        ]);

        $movement = InventoryMovement::create([
            'ingredient_id' => $ingredient->id,
            'type' => 'out',
            'reason' => 'sale',
            'quantity' => 1,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('ingredients.movement', $ingredient))
            ->delete(route('ingredients.movement.destroy', [$ingredient, $movement]))
            ->assertRedirect(route('ingredients.movement', $ingredient))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('inventory_movements', ['id' => $movement->id]);
        $ingredient->refresh();
        $this->assertEquals(5.0, (float) $ingredient->current_stock);
    }
}
