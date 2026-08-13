<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\Setting;
use App\Services\OrderPrinterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintAgentQueueTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-print-agent-token-123456';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('integration', 'api_token', $this->token);
        Setting::set('printing', 'enabled', true);
        Setting::set('printing', 'driver', 'agent');
        Setting::set('printing', 'restaurant_name', 'Bella Bistro');
        Setting::set('printing', 'paper_width', 48);
        \App\Support\AppSettings::loadIntoConfig();
    }

    public function test_dispatch_kitchen_print_enqueues_job(): void
    {
        $order = $this->makeOrder();

        app(OrderPrinterService::class)->dispatchKitchenPrint($order);

        $this->assertDatabaseCount('print_jobs', 1);
        $job = PrintJob::first();
        $this->assertSame('kitchen', $job->type);
        $this->assertSame($order->id, $job->order_id);
        $this->assertSame(PrintJob::STATUS_PENDING, $job->status);
        $this->assertStringContainsString('COZINHA', $job->payload);
    }

    public function test_agent_can_claim_complete_job(): void
    {
        $order = $this->makeOrder();
        app(OrderPrinterService::class)->dispatchKitchenPrint($order);

        $claim = $this->withToken($this->token)
            ->postJson('/api/v1/print-jobs/claim');

        $claim->assertOk();
        $jobId = $claim->json('data.id');
        $this->assertNotNull($jobId);
        $this->assertSame(PrintJob::STATUS_PRINTING, PrintJob::find($jobId)->status);

        $done = $this->withToken($this->token)
            ->postJson('/api/v1/print-jobs/'.$jobId.'/complete');

        $done->assertOk();
        $this->assertSame(PrintJob::STATUS_DONE, PrintJob::find($jobId)->fresh()->status);
    }

    public function test_claim_returns_null_when_empty(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/print-jobs/claim')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_failed_print_returns_job_to_pending(): void
    {
        $order = $this->makeOrder();
        app(OrderPrinterService::class)->dispatchKitchenPrint($order);

        $jobId = $this->withToken($this->token)
            ->postJson('/api/v1/print-jobs/claim')
            ->json('data.id');

        $this->withToken($this->token)
            ->postJson('/api/v1/print-jobs/'.$jobId.'/fail', ['error' => 'timeout'])
            ->assertOk();

        $job = PrintJob::find($jobId)->fresh();
        $this->assertSame(PrintJob::STATUS_PENDING, $job->status);
        $this->assertSame('timeout', $job->last_error);
    }

    private function makeOrder(): Order
    {
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
            'order_number' => 'PED-PRINT-1',
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

        return $order->fresh('items.product');
    }
}
