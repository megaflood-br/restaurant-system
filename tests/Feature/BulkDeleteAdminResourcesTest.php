<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\StockCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDeleteAdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_delete_orders_and_customers(): void
    {
        $admin = User::factory()->admin()->create();

        $orders = collect([
            Order::create([
                'order_number' => 'PED-BULK-1',
                'type' => 'takeaway',
                'status' => 'pending',
                'customer_name' => 'A',
                'total' => 10,
                'user_id' => $admin->id,
            ]),
            Order::create([
                'order_number' => 'PED-BULK-2',
                'type' => 'takeaway',
                'status' => 'pending',
                'customer_name' => 'B',
                'total' => 20,
                'user_id' => $admin->id,
            ]),
        ]);

        $customers = collect([
            Customer::create(['name' => 'Cliente 1', 'phone' => '5511111111111', 'is_active' => true]),
            Customer::create(['name' => 'Cliente 2', 'phone' => '5511222222222', 'is_active' => true]),
        ]);

        $this->actingAs($admin)
            ->delete(route('orders.bulk-destroy'), ['ids' => $orders->pluck('id')->all()])
            ->assertRedirect(route('orders.index'));

        $this->actingAs($admin)
            ->delete(route('customers.bulk-destroy'), ['ids' => $customers->pluck('id')->all()])
            ->assertRedirect(route('customers.index'));

        foreach ($orders as $order) {
            $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        }
        foreach ($customers as $customer) {
            $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        }
    }

    public function test_admin_can_bulk_delete_menu_and_stock_resources(): void
    {
        $admin = User::factory()->admin()->create();

        $emptyCategory = Category::create(['name' => 'Vazia', 'is_active' => true]);
        $filledCategory = Category::create(['name' => 'Com produto', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $filledCategory->id,
            'name' => 'Prato',
            'price' => 25,
            'is_available' => true,
        ]);
        $orphanProduct = Product::create([
            'category_id' => $filledCategory->id,
            'name' => 'Para excluir',
            'price' => 15,
            'is_available' => true,
        ]);

        $recipe = Recipe::create([
            'name' => 'Ficha teste',
            'yield_portions' => 1,
            'is_active' => true,
        ]);

        $areaFree = DeliveryArea::create([
            'name' => '0-2km',
            'min_km' => 0,
            'max_km' => 2,
            'fee' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $areaUsed = DeliveryArea::create([
            'name' => '2-5km',
            'min_km' => 2,
            'max_km' => 5,
            'fee' => 8,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        Order::create([
            'order_number' => 'PED-AREA-1',
            'type' => 'delivery',
            'status' => 'pending',
            'customer_name' => 'Entrega',
            'total' => 30,
            'delivery_area_id' => $areaUsed->id,
            'user_id' => $admin->id,
        ]);

        $stockCat = StockCategory::create(['name' => 'Alimentos', 'sort_order' => 1, 'is_active' => true]);
        $ingredient = Ingredient::create([
            'stock_category_id' => $stockCat->id,
            'name' => 'Arroz',
            'unit' => 'kg',
            'package_size' => 5,
            'cost_price' => 20,
            'current_stock' => 10,
            'minimum_stock' => 2,
        ]);

        $this->actingAs($admin)
            ->delete(route('categories.bulk-destroy'), ['ids' => [$emptyCategory->id, $filledCategory->id]])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $emptyCategory->id]);
        $this->assertDatabaseHas('categories', ['id' => $filledCategory->id]);

        $this->actingAs($admin)
            ->delete(route('products.bulk-destroy'), ['ids' => [$orphanProduct->id]])
            ->assertRedirect(route('products.index'));
        $this->assertDatabaseMissing('products', ['id' => $orphanProduct->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        $this->actingAs($admin)
            ->delete(route('recipes.bulk-destroy'), ['ids' => [$recipe->id]])
            ->assertRedirect(route('recipes.index'));
        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);

        $this->actingAs($admin)
            ->delete(route('delivery-areas.bulk-destroy'), ['ids' => [$areaFree->id, $areaUsed->id]])
            ->assertRedirect(route('delivery-areas.index'));
        $this->assertDatabaseMissing('delivery_areas', ['id' => $areaFree->id]);
        $this->assertDatabaseHas('delivery_areas', ['id' => $areaUsed->id]);

        $this->actingAs($admin)
            ->delete(route('ingredients.bulk-destroy'), ['ids' => [$ingredient->id]])
            ->assertRedirect(route('ingredients.index'));
        $this->assertDatabaseMissing('ingredients', ['id' => $ingredient->id]);

        $this->actingAs($admin)
            ->delete(route('stock-categories.bulk-destroy'), ['ids' => [$stockCat->id]])
            ->assertRedirect(route('stock-categories.index'));
        $this->assertDatabaseMissing('stock_categories', ['id' => $stockCat->id]);
    }

    public function test_index_pages_render_bulk_controls(): void
    {
        $admin = User::factory()->admin()->create();

        Category::create(['name' => 'Cat', 'is_active' => true]);

        $this->actingAs($admin)->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Excluir selecionados', false)
            ->assertSee('data-bulk-id', false);

        $this->actingAs($admin)->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Excluir selecionados', false);
    }

    public function test_waiter_cannot_bulk_delete(): void
    {
        $waiter = User::factory()->waiter()->create();
        $customer = Customer::create(['name' => 'X', 'phone' => '5511333333333', 'is_active' => true]);

        $this->actingAs($waiter)
            ->delete(route('customers.bulk-destroy'), ['ids' => [$customer->id]])
            ->assertRedirect(route('waiter.menu'));

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }
}
