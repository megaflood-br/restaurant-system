<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): Product
    {
        $category = Category::create([
            'name' => 'Pratos',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'X-Burger',
            'price' => 25,
            'is_available' => true,
        ]);
    }

    public function test_create_order_with_manual_payment_method(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->seedCatalog();

        $this->actingAs($admin)->post(route('orders.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Cliente',
            'payment_method' => 'pix',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $order = Order::query()->latest('id')->first();
        $this->assertSame('pix', $order->payment_method);
    }

    public function test_create_order_without_payment_method_stays_null(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->seedCatalog();

        $this->actingAs($admin)->post(route('orders.store'), [
            'type' => 'dine_in',
            'comanda_number' => 12,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $order = Order::query()->latest('id')->first();
        $this->assertNull($order->payment_method);
    }

    public function test_update_order_payment_method(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->seedCatalog();

        $this->actingAs($admin)->post(route('orders.store'), [
            'type' => 'delivery',
            'customer_name' => 'Maria',
            'delivery_fee' => 10,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $order = Order::query()->latest('id')->first();
        $this->assertNull($order->payment_method);

        $this->actingAs($admin)->patch(route('orders.details', $order), [
            'payment_method' => 'cash',
        ]);

        $this->assertSame('cash', $order->fresh()->payment_method);
    }
}
