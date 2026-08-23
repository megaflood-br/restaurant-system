<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStockValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_total_stock_value(): void
    {
        $admin = User::factory()->admin()->create();

        Ingredient::create([
            'name' => 'Farinha',
            'unit' => 'kg',
            'package_size' => 5,
            'cost_price' => 50,
            'current_stock' => 2,
            'minimum_stock' => 5,
        ]);
        Ingredient::create([
            'name' => 'Óleo',
            'unit' => 'L',
            'package_size' => 1,
            'cost_price' => 12,
            'current_stock' => 10,
            'minimum_stock' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Valor total em estoque')
            ->assertSee('R$ 140,00')
            ->assertSee('R$ 20,00 em risco');
    }
}
