<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderModalNewCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_quick_create_customer_via_json(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson(route('customers.quick-store'), [
            'name' => 'Maria Delivery',
            'phone' => '11999990000',
            'address' => 'Rua A, 100',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'sp',
            'zip_code' => '01000-000',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Maria Delivery')
            ->assertJsonPath('data.phone', '11999990000');

        $this->assertDatabaseHas('customers', [
            'name' => 'Maria Delivery',
            'phone' => '11999990000',
            'state' => 'SP',
            'is_active' => true,
        ]);
    }

    public function test_quick_create_requires_name(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('customers.quick-store'), [
                'phone' => '11999990000',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_orders_page_shows_new_customer_button(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('+ Novo cliente', false)
            ->assertSee('Cadastrar novo cliente', false);
    }

    public function test_waiter_cannot_quick_create_customer(): void
    {
        $waiter = User::factory()->waiter()->create();

        $this->actingAs($waiter)
            ->post(route('customers.quick-store'), [
                'name' => 'Não deve criar',
            ])
            ->assertRedirect(route('waiter.menu'));

        $this->assertDatabaseMissing('customers', ['name' => 'Não deve criar']);
    }
}
