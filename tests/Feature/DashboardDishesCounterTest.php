<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDishesCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_dishes_today_and_month_excluding_cancelled(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::create(['name' => 'Pratos', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cupim',
            'price' => 30,
            'is_available' => true,
        ]);

        $today = Order::create([
            'order_number' => 'PED-DISH-1',
            'type' => 'takeaway',
            'status' => 'pending',
            'total' => 90,
            'user_id' => $admin->id,
        ]);
        $today->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Cupim',
            'quantity' => 2,
            'unit_price' => 30,
            'subtotal' => 60,
        ]);
        $today->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Cupim',
            'quantity' => 1,
            'unit_price' => 30,
            'subtotal' => 30,
        ]);

        $cancelled = Order::create([
            'order_number' => 'PED-DISH-2',
            'type' => 'takeaway',
            'status' => 'cancelled',
            'total' => 30,
            'user_id' => $admin->id,
        ]);
        $cancelled->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Cupim',
            'quantity' => 5,
            'unit_price' => 30,
            'subtotal' => 150,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Pratos hoje')
            ->assertSee('Pratos mês');

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/Pratos hoje<\/p>\s*<p[^>]*>3<\/p>/', $html);
        $this->assertMatchesRegularExpression('/Pratos mês<\/p>\s*<p[^>]*>3<\/p>/', $html);
        $this->assertDoesNotMatchRegularExpression('/Pratos hoje<\/p>\s*<p[^>]*>8<\/p>/', $html);
    }
}
