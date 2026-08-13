<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEditComandaTest extends TestCase
{
    use RefreshDatabase;

    private function seedOpenOrder(User $user, int $comanda = 5, float $price = 20, int $qty = 2): array
    {
        $category = Category::create([
            'name' => 'Pratos',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Prato',
            'price' => $price,
            'is_available' => true,
        ]);
        $order = Order::create([
            'order_number' => 'PED-EDIT-'.uniqid(),
            'type' => 'dine_in',
            'comanda_number' => $comanda,
            'status' => 'pending',
            'total' => $price * $qty,
            'user_id' => $user->id,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $qty,
            'unit_price' => $price,
            'subtotal' => $price * $qty,
        ]);

        return compact('category', 'product', 'order', 'item');
    }

    public function test_admin_can_open_edit_mode_on_comanda(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedOpenOrder($admin, 5);

        $this->actingAs($admin)
            ->get(route('comandas.show', 5))
            ->assertOk()
            ->assertSee('Editar comanda');

        $this->actingAs($admin)
            ->get(route('comandas.show', ['comanda' => 5, 'edit' => 1]))
            ->assertOk()
            ->assertSee('Editando')
            ->assertSee('Sair da edição')
            ->assertSee('Cancelar pedido')
            ->assertSee('Modo edição');
    }

    public function test_admin_can_update_item_quantity(): void
    {
        $admin = User::factory()->admin()->create();
        ['order' => $order, 'item' => $item] = $this->seedOpenOrder($admin, 5, 10, 2);

        $this->actingAs($admin)
            ->patch(route('comandas.items.update', [5, $order, $item]), [
                'quantity' => 3,
                'notes' => 'sem cebola',
            ])
            ->assertRedirect(route('comandas.show', ['comanda' => 5, 'edit' => 1]));

        $item->refresh();
        $order->refresh();

        $this->assertSame(3, (int) $item->quantity);
        $this->assertSame('sem cebola', $item->notes);
        $this->assertEquals(30, (float) $item->subtotal);
        $this->assertEquals(30, (float) $order->total);
    }

    public function test_admin_can_remove_item_and_cancel_empty_order(): void
    {
        $admin = User::factory()->admin()->create();
        ['order' => $order, 'item' => $item] = $this->seedOpenOrder($admin, 7, 15, 1);

        $this->actingAs($admin)
            ->delete(route('comandas.items.destroy', [7, $order, $item]))
            ->assertRedirect(route('comandas.show', ['comanda' => 7, 'edit' => 1]));

        $this->assertDatabaseMissing('order_items', ['id' => $item->id]);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_admin_can_cancel_order_on_comanda(): void
    {
        $admin = User::factory()->admin()->create();
        ['order' => $order] = $this->seedOpenOrder($admin, 8);

        $this->actingAs($admin)
            ->post(route('comandas.orders.cancel', [8, $order]))
            ->assertRedirect(route('comandas.show', ['comanda' => 8, 'edit' => 1]));

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_admin_can_change_comanda_customer(): void
    {
        $admin = User::factory()->admin()->create();
        ['order' => $order] = $this->seedOpenOrder($admin, 9);
        $customer = Customer::create([
            'name' => 'Cliente Edit',
            'phone' => '5511999000111',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('comandas.customer', 9), [
                'customer_id' => $customer->id,
            ])
            ->assertRedirect(route('comandas.show', ['comanda' => 9, 'edit' => 1]));

        $order->refresh();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame('Cliente Edit', $order->customer_name);
    }

    public function test_waiter_cannot_edit_comanda(): void
    {
        $admin = User::factory()->admin()->create();
        $waiter = User::factory()->waiter()->create();
        ['order' => $order, 'item' => $item] = $this->seedOpenOrder($admin, 6);

        $this->actingAs($waiter)
            ->patch(route('comandas.items.update', [6, $order, $item]), [
                'quantity' => 9,
            ])
            ->assertRedirect(route('waiter.menu'));

        $this->assertSame(2, (int) $item->fresh()->quantity);
    }
}
