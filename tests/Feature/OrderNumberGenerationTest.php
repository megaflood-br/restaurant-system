<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_number_uses_max_sequence_not_count_after_deletes(): void
    {
        $admin = User::factory()->admin()->create();
        $date = now()->format('Ymd');

        Order::create([
            'order_number' => "PED-{$date}-0001",
            'type' => 'takeaway',
            'status' => 'pending',
            'total' => 10,
            'user_id' => $admin->id,
        ]);
        $second = Order::create([
            'order_number' => "PED-{$date}-0002",
            'type' => 'takeaway',
            'status' => 'pending',
            'total' => 10,
            'user_id' => $admin->id,
        ]);
        Order::create([
            'order_number' => "PED-{$date}-0003",
            'type' => 'takeaway',
            'status' => 'pending',
            'total' => 10,
            'user_id' => $admin->id,
        ]);

        // Simula exclusão no meio do dia (count cairia para 2 e geraria 0003 de novo).
        $second->delete();

        $this->assertSame("PED-{$date}-0004", Order::generateOrderNumber());
    }

    public function test_creating_order_after_delete_does_not_collide(): void
    {
        $admin = User::factory()->admin()->create();
        $date = now()->format('Ymd');

        Order::create([
            'order_number' => "PED-{$date}-0004",
            'type' => 'delivery',
            'status' => 'pending',
            'customer_name' => 'Valéria',
            'total' => 20,
            'user_id' => $admin->id,
        ]);

        // Count do dia = 1 → antigo geraria PED-...-0002, mas o problema real
        // era reutilizar 0004 após deletes. Garantimos próximo após o máximo.
        Order::create([
            'order_number' => "PED-{$date}-0001",
            'type' => 'takeaway',
            'status' => 'pending',
            'total' => 10,
            'user_id' => $admin->id,
        ]);

        $this->assertSame("PED-{$date}-0005", Order::generateOrderNumber());

        $created = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'type' => 'delivery',
            'status' => 'pending',
            'customer_name' => 'Valéria',
            'total' => 30,
            'user_id' => $admin->id,
        ]);

        $this->assertSame("PED-{$date}-0005", $created->order_number);
        $this->assertDatabaseHas('orders', ['order_number' => "PED-{$date}-0005"]);
    }
}
