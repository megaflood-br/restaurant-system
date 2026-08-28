<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_lists_dishes_sold_by_product_in_period(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::create(['name' => 'Pratos', 'is_active' => true]);
        $cupim = Product::create([
            'category_id' => $category->id,
            'name' => 'Cupim',
            'price' => 30,
            'is_available' => true,
        ]);
        $bebida = Product::create([
            'category_id' => $category->id,
            'name' => 'Suco',
            'price' => 8,
            'is_available' => true,
        ]);

        $day = Carbon::parse('2026-08-25 12:00:00', config('app.timezone'));

        $order = Order::create([
            'order_number' => 'PED-REP-1',
            'type' => 'delivery',
            'status' => 'delivered',
            'total' => 68,
            'delivery_fee' => 0,
            'user_id' => $admin->id,
        ]);
        $order->forceFill(['created_at' => $day, 'updated_at' => $day])->save();
        $order->items()->createMany([
            [
                'product_id' => $cupim->id,
                'product_name' => 'Cupim',
                'quantity' => 2,
                'unit_price' => 30,
                'subtotal' => 60,
            ],
            [
                'product_id' => $bebida->id,
                'product_name' => 'Suco',
                'quantity' => 1,
                'unit_price' => 8,
                'subtotal' => 8,
            ],
        ]);

        $cancelled = Order::create([
            'order_number' => 'PED-REP-2',
            'type' => 'takeaway',
            'status' => 'cancelled',
            'total' => 30,
            'user_id' => $admin->id,
        ]);
        $cancelled->forceFill(['created_at' => $day, 'updated_at' => $day])->save();
        $cancelled->items()->create([
            'product_id' => $cupim->id,
            'product_name' => 'Cupim',
            'quantity' => 10,
            'unit_price' => 30,
            'subtotal' => 300,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.index', [
                'preset' => 'custom',
                'from' => '2026-08-25',
                'to' => '2026-08-25',
            ]))
            ->assertOk()
            ->assertSee('Relatórios de vendas')
            ->assertSee('Pratos vendidos')
            ->assertSee('Cupim')
            ->assertSee('Suco')
            ->assertSee('3')
            ->assertSee('R$ 68,00')
            ->assertSee('Delivery');
    }

    public function test_guest_cannot_access_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }
}
