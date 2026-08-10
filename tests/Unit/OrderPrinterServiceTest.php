<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderPrinterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPrinterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_connection_failure_message_explains_lan_requirement(): void
    {
        $message = app(OrderPrinterService::class)
            ->connectionFailureMessage('192.168.1.100', 9100, 110, 'Connection timed out');

        $this->assertStringContainsString('192.168.1.100:9100', $message);
        $this->assertStringContainsString('nuvem/VPS', $message);
        $this->assertStringContainsString('Agente local', $message);
        $this->assertStringContainsString('Navegador', $message);
    }

    public function test_unreachable_host_throws_helpful_error(): void
    {
        config([
            'printing.enabled' => true,
            'printing.driver' => 'network',
            'printing.network.host' => '127.0.0.1',
            'printing.network.port' => 1,
            'printing.network.timeout' => 1,
            'printing.restaurant_name' => 'Bella Bistro',
        ]);

        try {
            app(OrderPrinterService::class)->printTestPage();
            $this->fail('Expected connection failure.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('127.0.0.1:1', $exception->getMessage());
            $this->assertStringContainsString('nuvem/VPS', $exception->getMessage());
        }
    }

    public function test_build_receipt_uses_configured_paper_width(): void
    {
        config([
            'printing.paper_width' => 48,
            'printing.restaurant_name' => 'Bella Bistro',
            'printing.kitchen_hide_prices' => true,
        ]);

        $category = Category::create([
            'name' => 'Pratos',
            'description' => 'Teste',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Strogonoff',
            'description' => 'Teste',
            'price' => 25,
            'is_available' => true,
        ]);

        $order = Order::create([
            'order_number' => 'PED-TEST-1',
            'type' => 'takeaway',
            'status' => 'pending',
            'customer_name' => 'Carlos',
            'customer_phone' => '11999999999',
            'delivery_fee' => 0,
            'total' => 25,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Strogonoff',
            'quantity' => 1,
            'unit_price' => 25,
            'subtotal' => 25,
        ]);

        $text = app(OrderPrinterService::class)->buildReceiptText($order->fresh('items'), 'kitchen');

        $this->assertStringContainsString('COZINHA', $text);
        $this->assertStringContainsString('Strogonoff', $text);
        $this->assertStringContainsString(str_repeat('=', 48), $text);
    }
}
