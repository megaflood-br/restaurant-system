<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Support\ComandaCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComandaLinkCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_comanda_can_link_a_customer(): void
    {
        config(['restaurant.total_comandas' => 20]);

        $admin = User::factory()->admin()->create();
        $customer = Customer::create([
            'name' => 'Ana Souza',
            'phone' => '5511999991111',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('comandas.open', 5), [
            'customer_id' => $customer->id,
        ]);

        $response->assertRedirect(route('comandas.show', ['comanda' => 5, 'add' => 1]));
        $this->assertSame($customer->id, ComandaCustomer::id(5));
        $this->assertSame('Ana Souza', ComandaCustomer::name(5));
    }

    public function test_opening_comanda_without_customer_clears_binding(): void
    {
        config(['restaurant.total_comandas' => 20]);

        $admin = User::factory()->admin()->create();
        $customer = Customer::create([
            'name' => 'Ana Souza',
            'phone' => '5511999991112',
            'is_active' => true,
        ]);

        ComandaCustomer::bind(8, $customer);

        $this->actingAs($admin)->post(route('comandas.open', 8), [
            'customer_id' => '',
        ])->assertRedirect();

        $this->assertNull(ComandaCustomer::get(8));
    }

    public function test_waiter_order_uses_customer_bound_to_comanda(): void
    {
        config(['restaurant.total_comandas' => 20]);

        $waiter = User::factory()->waiter()->create();
        $customer = Customer::create([
            'name' => 'Bruno Lima',
            'phone' => '5511888777666',
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Pratos',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Feijoada',
            'price' => 32,
            'is_available' => true,
        ]);

        $this->actingAs($waiter)
            ->withSession([
                'comanda_customers' => [
                    4 => ['id' => $customer->id, 'name' => $customer->name],
                ],
                'waiter_cart' => [
                    'comanda_number' => 4,
                    'type' => 'dine_in',
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'quantity' => 1,
                            'notes' => null,
                        ],
                    ],
                ],
            ])
            ->post(route('waiter.store'), [
                'comanda_number' => 4,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'type' => 'dine_in',
            'comanda_number' => 4,
            'customer_id' => $customer->id,
            'customer_name' => 'Bruno Lima',
        ]);
    }

    public function test_manual_open_accepts_customer(): void
    {
        config(['restaurant.total_comandas' => 20]);

        $admin = User::factory()->admin()->create();
        $customer = Customer::create([
            'name' => 'Carla',
            'phone' => '5511777666555',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('comandas.open.manual'), [
            'comanda_number' => 12,
            'customer_id' => $customer->id,
        ])->assertRedirect(route('comandas.show', ['comanda' => 12, 'add' => 1]));

        $this->assertSame($customer->id, ComandaCustomer::id(12));
    }

    public function test_comanda_show_displays_linked_customer(): void
    {
        config(['restaurant.total_comandas' => 20]);

        $admin = User::factory()->admin()->create();
        $customer = Customer::create([
            'name' => 'Diana',
            'phone' => '5511666555444',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->withSession([
                'comanda_customers' => [
                    3 => ['id' => $customer->id, 'name' => $customer->name],
                ],
            ])
            ->get(route('comandas.show', 3))
            ->assertOk()
            ->assertSee('Diana');
    }
}
