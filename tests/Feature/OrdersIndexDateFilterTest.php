<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersIndexDateFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createOrder(array $attributes = []): Order
    {
        static $seq = 0;
        $seq++;

        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $order = Order::create(array_merge([
            'order_number' => 'PED-DAY-'.$seq,
            'type' => 'takeaway',
            'status' => 'pending',
            'customer_name' => 'Cliente',
            'total' => 10,
        ], $attributes));

        if ($createdAt !== null) {
            $order->created_at = $createdAt;
            $order->updated_at = $createdAt;
            $order->save();
        }

        return $order->fresh();
    }

    public function test_orders_index_defaults_to_today_and_shows_daily_sales(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 14:00:00', 'America/Sao_Paulo'));
        config(['app.timezone' => 'America/Sao_Paulo']);

        $admin = User::factory()->admin()->create();

        $this->createOrder([
            'total' => 50,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->createOrder([
            'total' => 30,
            'status' => 'cancelled',
            'created_at' => now(),
        ]);

        $this->createOrder([
            'total' => 100,
            'status' => 'pending',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.index'));

        $response->assertOk()
            ->assertSee('Vendas do dia', false)
            ->assertSee('R$ 50,00', false)
            ->assertSee('value="2026-08-19"', false)
            ->assertViewHas('dailyStats', function (array $stats) {
                return $stats['orders_count'] === 2
                    && $stats['revenue'] == 50.0
                    && $stats['cancelled_count'] === 1;
            });
    }

    public function test_orders_index_filters_by_selected_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 14:00:00', 'America/Sao_Paulo'));
        config(['app.timezone' => 'America/Sao_Paulo']);

        $admin = User::factory()->admin()->create();

        $this->createOrder([
            'order_number' => 'ORD-YESTERDAY',
            'total' => 75,
            'status' => 'pending',
            'created_at' => now()->subDay(),
        ]);

        $this->createOrder([
            'order_number' => 'ORD-TODAY',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.index', [
            'date' => now()->subDay()->toDateString(),
        ]));

        $response->assertOk()
            ->assertSee('ORD-YESTERDAY', false)
            ->assertDontSee('ORD-TODAY', false)
            ->assertSee('R$ 75,00', false);
    }
}
