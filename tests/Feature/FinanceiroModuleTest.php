<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CashFlowService;
use App\Services\ComandaBillService;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceiroModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_comanda_creates_cash_entrada(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $order = $this->createDineInOrder(comanda: 7, total: 45.5);

        $bill = app(ComandaBillService::class)->closeComanda(7, 'pix');

        $this->assertSame(45.5, $bill['total']);
        $this->assertDatabaseHas('cash_movements', [
            'type' => 'entrada',
            'category' => 'venda_comanda',
            'comanda_number' => 7,
            'payment_method' => 'pix',
            'source' => 'comanda',
        ]);

        $movement = CashMovement::query()->first();
        $this->assertEquals(45.5, (float) $movement->amount);
        $this->assertStringContainsString('007', (string) $movement->description);
    }

    public function test_closing_comanda_is_idempotent_in_cash_flow(): void
    {
        $this->actingAs($this->admin());
        $this->createDineInOrder(comanda: 3, total: 20);

        $service = app(ComandaBillService::class);
        // First close
        $service->closeComanda(3, 'cash');

        // Simulate second attempt with empty open orders — should not duplicate.
        // Re-record same bill key via CashFlowService.
        app(CashFlowService::class)->recordComandaClose([
            'comanda_number' => 3,
            'total' => 20,
            'payment_method' => 'cash',
            'orders' => Order::query()->where('comanda_number', 3)->get(),
        ], null);

        $this->assertSame(1, CashMovement::query()->count());
    }

    public function test_delivered_delivery_order_creates_cash_entrada(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'type' => 'delivery',
            'status' => 'preparing',
            'customer_name' => 'Cliente',
            'customer_phone' => '11999999999',
            'delivery_fee' => 5,
            'total' => 35,
            'payment_method' => 'pix',
            'user_id' => $admin->id,
        ]);

        $this->patch(route('orders.status', $order), ['status' => 'delivered'])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_movements', [
            'type' => 'entrada',
            'category' => 'venda_delivery',
            'order_id' => $order->id,
            'payment_method' => 'pix',
            'source' => 'order',
        ]);
    }

    public function test_admin_can_create_manual_saida_and_see_daily_summary(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->post(route('financeiro.store'), [
            'type' => 'saida',
            'category' => 'sangria',
            'amount' => 50,
            'payment_method' => 'cash',
            'description' => 'Sangria para o cofre',
        ])->assertRedirect();

        $this->assertDatabaseHas('cash_movements', [
            'type' => 'saida',
            'category' => 'sangria',
            'source' => 'manual',
        ]);

        $this->get(route('financeiro.index'))
            ->assertOk()
            ->assertSee('Sangria para o cofre')
            ->assertSee('Financeiro');
    }

    public function test_manual_movement_can_be_deleted_but_automatic_cannot(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $manual = app(CashFlowService::class)->record([
            'type' => 'entrada',
            'category' => 'suprimento',
            'amount' => 10,
            'source' => 'manual',
            'user_id' => $admin->id,
        ]);

        $auto = app(CashFlowService::class)->record([
            'type' => 'entrada',
            'category' => 'venda_comanda',
            'amount' => 20,
            'source' => 'comanda',
            'source_key' => 'comanda:'.today()->toDateString().':1',
            'comanda_number' => 1,
            'payment_method' => 'cash',
        ]);

        $this->delete(route('financeiro.destroy', $manual))->assertRedirect();
        $this->assertDatabaseMissing('cash_movements', ['id' => $manual->id]);

        $this->delete(route('financeiro.destroy', $auto))->assertRedirect();
        $this->assertDatabaseHas('cash_movements', ['id' => $auto->id]);
    }

    public function test_delivered_dine_in_order_creates_cash_entrada(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $order = $this->createDineInOrder(comanda: 5, total: 40);
        $order->update(['status' => 'preparing']);

        $this->patch(route('orders.status', $order), ['status' => 'delivered'])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_movements', [
            'type' => 'entrada',
            'category' => 'venda_comanda',
            'order_id' => $order->id,
            'source' => 'order',
            'source_key' => 'order:'.$order->id,
        ]);
        $this->assertEquals(40.0, (float) CashMovement::query()->sum('amount'));
    }

    public function test_comanda_close_does_not_double_count_already_recorded_orders(): void
    {
        $this->actingAs($this->admin());

        $order = $this->createDineInOrder(comanda: 9, total: 30);
        $order->update(['status' => 'delivered']);

        app(CashFlowService::class)->recordOrderSale($order);

        $this->assertSame(1, CashMovement::query()->count());
        $this->assertEquals(30.0, (float) CashMovement::query()->sum('amount'));

        app(CashFlowService::class)->recordComandaClose([
            'comanda_number' => 9,
            'total' => 30,
            'payment_method' => 'pix',
            'orders' => collect([$order]),
        ], null);

        $this->assertSame(1, CashMovement::query()->count());
        $this->assertEquals(30.0, (float) CashMovement::query()->sum('amount'));
    }

    public function test_sync_sales_backfills_missing_delivered_orders(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $missing = $this->createDineInOrder(comanda: 2, total: 55);
        $missing->update(['status' => 'delivered']);

        $already = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'type' => 'delivery',
            'status' => 'delivered',
            'customer_name' => 'Já no caixa',
            'customer_phone' => '11988887777',
            'delivery_fee' => 0,
            'total' => 20,
            'payment_method' => 'cash',
            'user_id' => $admin->id,
        ]);
        app(CashFlowService::class)->recordOrderSale($already);

        $this->assertSame(1, CashMovement::query()->count());

        $this->post(route('financeiro.sync-sales'), ['date' => today()->toDateString()])
            ->assertRedirect(route('financeiro.index', ['date' => today()->toDateString()]));

        $this->assertDatabaseHas('cash_movements', [
            'order_id' => $missing->id,
            'source' => 'order',
            'source_key' => 'order:'.$missing->id,
        ]);
        $this->assertEquals(75.0, (float) CashMovement::query()->sum('amount'));

        // Idempotent
        $this->post(route('financeiro.sync-sales'), ['date' => today()->toDateString()])
            ->assertRedirect();
        $this->assertSame(2, CashMovement::query()->count());
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
        ]);
    }

    private function createDineInOrder(int $comanda, float $total): Order
    {
        $category = Category::create([
            'name' => 'Pratos',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Prato teste',
            'price' => $total,
            'is_available' => true,
        ]);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'type' => 'dine_in',
            'status' => 'served',
            'comanda_number' => $comanda,
            'customer_name' => 'Mesa',
            'total' => $total,
            'delivery_fee' => 0,
            'user_id' => User::factory()->create(['role' => UserRole::Waiter])->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $total,
            'subtotal' => $total,
        ]);

        return $order;
    }
}
