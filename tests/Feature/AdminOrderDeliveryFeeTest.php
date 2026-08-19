<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminOrderDeliveryFeeTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): Product
    {
        $category = Category::create([
            'name' => 'Pratos',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'X-Burger',
            'price' => 25,
            'is_available' => true,
        ]);
    }

    private function fakeNearbyGeocode(): void
    {
        config([
            'digital_menu.city' => 'São Paulo',
            'digital_menu.state' => 'SP',
            'general.delivery_origin_lat' => '-23.5505',
            'general.delivery_origin_lng' => '-46.6333',
        ]);

        Http::fake(function (Request $request) {
            return Http::response([
                [
                    'lat' => '-23.5600',
                    'lon' => '-46.6400',
                    'display_name' => 'Rua A, Vila Mariana, São Paulo, SP, Brasil',
                    'address' => [
                        'road' => 'Rua A',
                        'suburb' => 'Vila Mariana',
                        'city' => 'São Paulo',
                        'state' => 'São Paulo',
                    ],
                ],
            ], 200);
        });
    }

    public function test_delivery_order_with_registered_customer_applies_delivery_fee(): void
    {
        $this->fakeNearbyGeocode();

        $admin = User::factory()->admin()->create();
        $product = $this->seedCatalog();
        $area = DeliveryArea::create([
            'name' => 'Até 5km',
            'min_km' => 0,
            'max_km' => 5,
            'fee' => 8.50,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $customer = Customer::create([
            'name' => 'Maria',
            'phone' => '5511999990000',
            'address' => 'Rua A, 100',
            'neighborhood' => 'Vila Mariana',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '04101-000',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('orders.store'), [
            'type' => 'delivery',
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $order = $customer->orders()->latest('id')->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame('delivery', $order->type);
        $this->assertEquals(8.50, (float) $order->delivery_fee);
        $this->assertSame($area->id, $order->delivery_area_id);
        $this->assertStringContainsString('Rua A, 100', (string) $order->delivery_address);
        $this->assertEquals(33.50, (float) $order->total);
    }

    public function test_delivery_quote_endpoint_returns_fee_for_customer(): void
    {
        $this->fakeNearbyGeocode();

        $admin = User::factory()->admin()->create();
        DeliveryArea::create([
            'name' => 'Até 5km',
            'min_km' => 0,
            'max_km' => 5,
            'fee' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $customer = Customer::create([
            'name' => 'João',
            'phone' => '5511888777666',
            'address' => 'Rua B, 50',
            'neighborhood' => 'Vila Mariana',
            'city' => 'São Paulo',
            'state' => 'SP',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('customers.delivery-quote', $customer));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('delivery_fee', 7);
        $this->assertStringContainsString('Rua B, 50', $response->json('delivery_address'));
    }

    public function test_takeaway_order_does_not_apply_delivery_fee(): void
    {
        $this->fakeNearbyGeocode();

        $admin = User::factory()->admin()->create();
        $product = $this->seedCatalog();
        DeliveryArea::create([
            'name' => 'Até 5km',
            'min_km' => 0,
            'max_km' => 5,
            'fee' => 8.50,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $customer = Customer::create([
            'name' => 'Maria',
            'phone' => '5511999990001',
            'address' => 'Rua A, 100',
            'city' => 'São Paulo',
            'state' => 'SP',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('orders.store'), [
            'type' => 'takeaway',
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $order = $customer->orders()->latest('id')->first();
        $this->assertEquals(0, (float) $order->delivery_fee);
        $this->assertNull($order->delivery_area_id);
        $this->assertEquals(25, (float) $order->total);
    }

    public function test_manual_delivery_fee_overrides_automatic_quote(): void
    {
        $this->fakeNearbyGeocode();

        $admin = User::factory()->admin()->create();
        $product = $this->seedCatalog();
        DeliveryArea::create([
            'name' => 'Até 5km',
            'min_km' => 0,
            'max_km' => 5,
            'fee' => 8.50,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $customer = Customer::create([
            'name' => 'Maria',
            'phone' => '5511999990002',
            'address' => 'Rua A, 100',
            'neighborhood' => 'Vila Mariana',
            'city' => 'São Paulo',
            'state' => 'SP',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('orders.store'), [
            'type' => 'delivery',
            'customer_id' => $customer->id,
            'delivery_fee' => 15,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $order = $customer->orders()->latest('id')->first();
        $this->assertEquals(15, (float) $order->delivery_fee);
        $this->assertNull($order->delivery_area_id);
        $this->assertEquals(40, (float) $order->total);
    }

    public function test_manual_delivery_fee_without_registered_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->seedCatalog();

        $this->actingAs($admin)->post(route('orders.store'), [
            'type' => 'delivery',
            'customer_name' => 'Visitante',
            'customer_phone' => '5511999888777',
            'delivery_fee' => 12,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $order = \App\Models\Order::query()->latest('id')->first();
        $this->assertSame('delivery', $order->type);
        $this->assertEquals(12, (float) $order->delivery_fee);
        $this->assertNull($order->delivery_area_id);
        $this->assertNull($order->delivery_address);
        $this->assertEquals(62, (float) $order->total);
    }
}
