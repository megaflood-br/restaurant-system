<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_customers_for_autocomplete(): void
    {
        $admin = User::factory()->admin()->create();

        Customer::create([
            'name' => 'Maria Silva',
            'phone' => '11988887777',
            'is_active' => true,
        ]);

        Customer::create([
            'name' => 'João Pedro',
            'phone' => '11977776666',
            'is_active' => true,
        ]);

        Customer::create([
            'name' => 'Inativo Teste',
            'phone' => '11966665555',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->getJson(route('customers.search', ['search' => 'maria']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Maria Silva')
            ->assertJsonPath('data.0.label', 'Maria Silva — 11988887777');
    }

    public function test_customer_search_requires_at_least_two_characters(): void
    {
        $admin = User::factory()->admin()->create();

        Customer::create(['name' => 'Ana', 'is_active' => true]);

        $this->actingAs($admin)
            ->getJson(route('customers.search', ['search' => 'a']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_orders_form_has_customer_autocomplete_field(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('orders.index'));

        $response->assertOk();
        $this->assertStringContainsString('Buscar por nome, telefone ou e-mail', $response->getContent());
        $this->assertStringContainsString('customer_search', $response->getContent());
    }
}
