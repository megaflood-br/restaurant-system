<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeleteOrdersAndCustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_an_order(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::create([
            'order_number' => 'PED-TEST-0001',
            'type' => 'takeaway',
            'status' => 'pending',
            'customer_name' => 'Carlos',
            'total' => 40,
            'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('orders.destroy', $order));

        $response->assertRedirect(route('orders.index'));
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_admin_can_delete_a_customer_with_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::create([
            'name' => 'Carlos',
            'phone' => '5511999999999',
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'PED-TEST-0002',
            'type' => 'delivery',
            'status' => 'pending',
            'customer_id' => $customer->id,
            'customer_name' => 'Carlos',
            'total' => 24,
            'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => null,
            'customer_name' => 'Carlos',
        ]);
    }

    public function test_waiter_cannot_delete_orders_or_customers(): void
    {
        $waiter = User::factory()->waiter()->create();
        $customer = Customer::create([
            'name' => 'Carlos',
            'phone' => '5511888777666',
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'PED-TEST-0003',
            'type' => 'takeaway',
            'status' => 'pending',
            'customer_name' => 'Carlos',
            'total' => 10,
            'user_id' => $waiter->id,
        ]);

        $this->actingAs($waiter)->delete(route('orders.destroy', $order))->assertRedirect(route('waiter.menu'));
        $this->actingAs($waiter)->delete(route('customers.destroy', $customer))->assertRedirect(route('waiter.menu'));

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }
}
