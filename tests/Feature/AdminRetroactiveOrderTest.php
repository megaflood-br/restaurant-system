<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRetroactiveOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_order_can_be_created_with_past_datetime(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::create(['name' => 'Pratos', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cupim',
            'price' => 30,
            'is_available' => true,
        ]);

        $past = Carbon::parse('2026-08-20 14:30:00', config('app.timezone'));

        $response = $this->actingAs($admin)->post(route('orders.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Maria',
            'ordered_at' => $past->format('Y-m-d\TH:i'),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame('PED-20260820-0001', $order->order_number);
        $this->assertTrue($order->created_at->equalTo($past));
        $this->assertEquals(60.0, (float) $order->total);

        $this->actingAs($admin)
            ->get(route('orders.index', ['date' => '2026-08-20']))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_ordered_at_cannot_be_in_the_future(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::create(['name' => 'Pratos', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cupim',
            'price' => 30,
            'is_available' => true,
        ]);

        $this->actingAs($admin)->post(route('orders.store'), [
            'type' => 'takeaway',
            'ordered_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('ordered_at');

        $this->assertSame(0, Order::query()->count());
    }
}
