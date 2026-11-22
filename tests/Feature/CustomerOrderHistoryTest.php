<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_lists_orders_from_previous_months(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::create([
            'name' => 'Cliente Histórico',
            'phone' => '5511999000111',
            'is_active' => true,
        ]);

        $recent = $this->createOrderForCustomer($customer, $admin, now()->subDays(2), 'PED-RECENT-0001');
        $older = $this->createOrderForCustomer($customer, $admin, now()->subMonths(2), 'PED-OLDER-0001');
        $muchOlder = $this->createOrderForCustomer($customer, $admin, now()->subYear(), 'PED-OLDYEAR-0001');

        $response = $this->actingAs($admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Histórico de pedidos', false)
            ->assertSee($recent->order_number)
            ->assertSee($older->order_number)
            ->assertSee($muchOlder->order_number)
            ->assertSee('3 pedido(s)', false);

        $this->assertGreaterThan(
            strpos($response->getContent(), $recent->order_number),
            strpos($response->getContent(), $older->order_number)
        );
    }

    public function test_customer_order_history_is_paginated_beyond_ten_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::create([
            'name' => 'Cliente Muitos Pedidos',
            'phone' => '5511999000222',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 12; $i++) {
            $this->createOrderForCustomer(
                $customer,
                $admin,
                now()->subDays($i),
                sprintf('PED-MANY-%04d', $i)
            );
        }

        $this->actingAs($admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('12 pedido(s)', false)
            ->assertSee('PED-MANY-0001')
            ->assertSee('PED-MANY-0012');
    }

    private function createOrderForCustomer(Customer $customer, User $user, $createdAt, string $orderNumber): Order
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Pratos',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => $orderNumber,
            'type' => 'delivery',
            'status' => 'delivered',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'delivery_fee' => 5,
            'delivery_address' => 'Rua Teste, 1',
            'total' => 40,
            'user_id' => $user->id,
        ]);

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $order->fresh();
    }
}
