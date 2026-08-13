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

class AdminEditOrderTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrder(User $user, string $type = 'delivery', string $status = 'pending'): array
    {
        $category = Category::create([
            'name' => 'Pratos',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Prato',
            'price' => 25,
            'is_available' => true,
        ]);
        $customer = Customer::create([
            'name' => 'Cliente Pedido',
            'phone' => '5511999888777',
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'PED-ORD-'.uniqid(),
            'type' => $type,
            'status' => $status,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'delivery_fee' => $type === 'delivery' ? 5 : 0,
            'delivery_address' => $type === 'delivery' ? 'Rua A, 10' : null,
            'total' => 55,
            'user_id' => $user->id,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 25,
            'subtotal' => 50,
        ]);

        return compact('product', 'customer', 'order', 'item');
    }

    public function test_admin_sees_edit_order_button(): void
    {
        $admin = User::factory()->admin()->create();
        ['order' => $order] = $this->seedOrder($admin);

        $this->actingAs($admin)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Editar pedido');
    }

    public function test_admin_can_update_item_quantity(): void
    {
        $admin = User::factory()->admin()->create();
        ['order' => $order, 'item' => $item] = $this->seedOrder($admin);

        $this->actingAs($admin)
            ->patch(route('orders.items.update', [$order, $item]), [
                'quantity' => 3,
                'notes' => 'bem passado',
            ])
            ->assertRedirect(route('orders.show', ['order' => $order, 'edit' => 1]));

        $item->refresh();
        $order->refresh();

        $this->assertSame(3, (int) $item->quantity);
        $this->assertSame('bem passado', $item->notes);
        $this->assertEquals(75, (float) $item->subtotal);
        $this->assertEquals(80, (float) $order->total); // 75 + delivery 5
    }

    public function test_admin_can_update_order_details(): void
    {
        $admin = User::factory()->admin()->create();
        ['order' => $order] = $this->seedOrder($admin);
        $other = Customer::create([
            'name' => 'Outro Cliente',
            'phone' => '5511888777666',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('orders.details', $order), [
                'customer_id' => $other->id,
                'notes' => 'Entregar no portão',
                'delivery_address' => 'Rua B, 20',
                'delivery_fee' => 8,
            ])
            ->assertRedirect(route('orders.show', ['order' => $order, 'edit' => 1]));

        $order->refresh();
        $this->assertSame($other->id, $order->customer_id);
        $this->assertSame('Outro Cliente', $order->customer_name);
        $this->assertSame('Entregar no portão', $order->notes);
        $this->assertSame('Rua B, 20', $order->delivery_address);
        $this->assertEquals(8, (float) $order->delivery_fee);
        $this->assertEquals(58, (float) $order->total); // 50 + 8
    }

    public function test_admin_can_remove_item(): void
    {
        $admin = User::factory()->admin()->create();
        ['order' => $order, 'item' => $item] = $this->seedOrder($admin);

        $this->actingAs($admin)
            ->delete(route('orders.items.destroy', [$order, $item]))
            ->assertRedirect(route('orders.show', ['order' => $order, 'edit' => 1]));

        $this->assertDatabaseMissing('order_items', ['id' => $item->id]);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_cancelled_order_cannot_be_edited(): void
    {
        $admin = User::factory()->admin()->create();
        ['order' => $order, 'item' => $item] = $this->seedOrder($admin, status: 'cancelled');

        $this->actingAs($admin)
            ->patch(route('orders.items.update', [$order, $item]), [
                'quantity' => 9,
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(2, (int) $item->fresh()->quantity);
    }

    public function test_waiter_cannot_edit_order(): void
    {
        $admin = User::factory()->admin()->create();
        $waiter = User::factory()->waiter()->create();
        ['order' => $order, 'item' => $item] = $this->seedOrder($admin);

        $this->actingAs($waiter)
            ->patch(route('orders.items.update', [$order, $item]), [
                'quantity' => 9,
            ])
            ->assertRedirect(route('waiter.menu'));

        $this->assertSame(2, (int) $item->fresh()->quantity);
    }
}
