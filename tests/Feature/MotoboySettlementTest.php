<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MotoboySettlement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotoboySettlementTest extends TestCase
{
    use RefreshDatabase;

    private function seedProduct(): Product
    {
        $category = Category::create([
            'name' => 'Pratos',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Pizza',
            'price' => 40,
            'is_available' => true,
        ]);
    }

    private function createDeliveryOrder(string $status, float $fee, ?string $createdAt = null): Order
    {
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'type' => 'delivery',
            'status' => $status,
            'customer_name' => 'Cliente',
            'delivery_fee' => $fee,
            'total' => 40 + $fee,
            'user_id' => User::factory()->admin()->create()->id,
        ]);

        if ($createdAt !== null) {
            $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $order;
    }

    public function test_motoboy_index_lists_delivery_orders_and_totals(): void
    {
        $admin = User::factory()->admin()->create();
        $today = now()->toDateTimeString();

        $this->createDeliveryOrder('delivered', 8, $today);
        $this->createDeliveryOrder('preparing', 6, $today);
        $this->createDeliveryOrder('cancelled', 10, $today);
        $this->createDeliveryOrder('delivered', 5, now()->subDay()->toDateTimeString());

        $response = $this->actingAs($admin)->get(route('motoboy.index', [
            'date' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertSee('Apuração motoboy')
            ->assertSee('R$ 14,00')
            ->assertSee('Entregas no dia')
            ->assertSee('2');
    }

    public function test_delivered_only_filter_excludes_pending_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $today = now()->toDateTimeString();

        $this->createDeliveryOrder('delivered', 8, $today);
        $this->createDeliveryOrder('preparing', 6, $today);

        $response = $this->actingAs($admin)->get(route('motoboy.index', [
            'date' => now()->toDateString(),
            'delivered_only' => 1,
        ]));

        $response->assertOk()
            ->assertSee('R$ 8,00')
            ->assertDontSee('R$ 14,00');
    }

    public function test_store_saves_daily_rate_and_payout_snapshot(): void
    {
        $admin = User::factory()->admin()->create();
        $today = now()->toDateString();

        $this->createDeliveryOrder('delivered', 8, now()->toDateTimeString());
        $this->createDeliveryOrder('delivered', 7, now()->toDateTimeString());

        $response = $this->actingAs($admin)->post(route('motoboy.store'), [
            'date' => $today,
            'daily_rate' => 50,
            'notes' => 'Fim do expediente',
            'mark_paid' => 1,
        ]);

        $response->assertRedirect(route('motoboy.index', ['date' => $today]));

        $settlement = MotoboySettlement::query()->first();
        $this->assertNotNull($settlement);
        $this->assertSame($today, $settlement->settlement_date->toDateString());
        $this->assertEquals(50, (float) $settlement->daily_rate);
        $this->assertEquals(15, (float) $settlement->delivery_fees_total);
        $this->assertSame(2, $settlement->deliveries_count);
        $this->assertSame('Fim do expediente', $settlement->notes);
        $this->assertNotNull($settlement->paid_at);
        $this->assertEquals(65, $settlement->totalPayout());
    }

    public function test_waiter_cannot_access_motoboy_screen(): void
    {
        $waiter = User::factory()->waiter()->create();

        $this->actingAs($waiter)
            ->get(route('motoboy.index'))
            ->assertRedirect(route('waiter.menu'));
    }
}
