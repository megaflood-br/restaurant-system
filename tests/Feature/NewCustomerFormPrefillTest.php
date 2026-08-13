<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewCustomerFormPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_customer_modal_does_not_prefill_last_listed_customer(): void
    {
        $admin = User::factory()->admin()->create();

        Customer::create([
            'name' => 'Ana',
            'phone' => '11111111111',
            'is_active' => true,
        ]);
        Customer::create([
            'name' => 'Sandy',
            'phone' => '11999998888',
            'address' => 'Rua da Sandy, 10',
            'city' => 'São Paulo',
            'is_active' => true,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('customers.index'))
            ->assertOk()
            ->getContent();

        // O modal de novo cliente deve ter o campo nome vazio (não o da Sandy).
        $this->assertMatchesRegularExpression(
            '/name="name"[^>]*value=""/',
            $html,
            'Campo nome do formulário de novo cliente deveria estar vazio.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="name"[^>]*value="Sandy"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="address"[^>]*value="Rua da Sandy, 10"/',
            $html
        );
    }

    public function test_create_page_form_is_empty(): void
    {
        $admin = User::factory()->admin()->create();

        Customer::create([
            'name' => 'Sandy',
            'phone' => '11999998888',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('customers.create'))
            ->assertOk()
            ->assertDontSee('value="Sandy"', false);
    }
}
