<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintOnPreparingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('printing', 'enabled', true);
        Setting::set('printing', 'driver', 'agent');
        Setting::set('printing', 'auto_print_on_create', false);
        Setting::set('printing', 'print_on_preparing', true);
        Setting::set('printing', 'restaurant_name', 'Bella Bistro');
        AppSettings::loadIntoConfig();
    }

    public function test_creating_order_does_not_enqueue_print_by_default(): void
    {
        $order = $this->makeOrder();

        app(\App\Services\OrderPrinterService::class)->maybePrintOnCreate($order);

        $this->assertDatabaseCount('print_jobs', 0);
    }

    public function test_status_change_to_preparing_enqueues_print(): void
    {
        $admin = User::factory()->create();
        $order = $this->makeOrder();

        $this->actingAs($admin)
            ->patch(route('orders.status', $order), ['status' => 'preparing'])
            ->assertRedirect();

        $this->assertDatabaseCount('print_jobs', 1);
        $this->assertSame('kitchen', PrintJob::first()->type);
        $this->assertSame($order->id, PrintJob::first()->order_id);
    }

    public function test_status_change_to_ready_does_not_print(): void
    {
        $admin = User::factory()->create();
        $order = $this->makeOrder(['status' => 'preparing']);

        $this->actingAs($admin)
            ->patch(route('orders.status', $order), ['status' => 'ready'])
            ->assertRedirect();

        $this->assertDatabaseCount('print_jobs', 0);
    }

    /** @param  array<string, mixed>  $overrides */
    private function makeOrder(array $overrides = []): Order
    {
        $category = Category::create([
            'name' => 'Pratos',
            'description' => 'Teste',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Strogonoff',
            'description' => 'Teste',
            'price' => 25,
            'is_available' => true,
        ]);

        $order = Order::create(array_merge([
            'order_number' => 'PED-PREP-'.uniqid(),
            'type' => 'takeaway',
            'status' => 'pending',
            'customer_name' => 'Carlos',
            'customer_phone' => '11999999999',
            'delivery_fee' => 0,
            'total' => 25,
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Strogonoff',
            'quantity' => 1,
            'unit_price' => 25,
            'subtotal' => 25,
        ]);

        return $order->fresh('items');
    }
}
