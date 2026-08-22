<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderDiscountTest extends TestCase
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

    public function test_manual_delivery_order_accepts_discount_against_delivery_fee(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->seedCatalog();
        $customer = Customer::create([
            'name' => 'Carlos',
            'phone' => '5511999000700',
            'address' => 'Rua Machado de Assis, 465',
            'city' => 'Atibaia',
            'state' => 'SP',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('orders.store'), [
            'type' => 'delivery',
            'customer_id' => $customer->id,
            'delivery_fee' => 4,
            'discount' => 4,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $order = $customer->orders()->latest('id')->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('orders.show', $order));

        $this->assertEquals(4.0, (float) $order->delivery_fee);
        $this->assertEquals(4.0, (float) $order->discount);
        $this->assertEquals(25.0, (float) $order->total);
    }

    public function test_update_details_can_set_discount_on_existing_order(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->seedCatalog();

        $this->actingAs($admin)->post(route('orders.store'), [
            'type' => 'delivery',
            'customer_name' => 'Ana',
            'customer_phone' => '11999990000',
            'delivery_fee' => 8,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $order = \App\Models\Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals(33.0, (float) $order->total);

        $this->actingAs($admin)->patch(route('orders.details', $order), [
            'delivery_fee' => 8,
            'discount' => 8,
            'customer_name' => 'Ana',
            'customer_phone' => '11999990000',
        ])->assertRedirect();

        $order->refresh();
        $this->assertEquals(8.0, (float) $order->discount);
        $this->assertEquals(25.0, (float) $order->total);
    }

    public function test_create_order_form_shows_discount_field(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('orders.create'))
            ->assertOk()
            ->assertSee('name="discount"', false)
            ->assertSee('Desconto (R$)');
    }
}
