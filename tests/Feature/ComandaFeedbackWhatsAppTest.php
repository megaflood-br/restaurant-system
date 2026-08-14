<?php

namespace Tests\Feature;

use App\Jobs\SendComandaFeedbackWhatsAppJob;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\ComandaBillService;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ComandaFeedbackWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_comanda_queues_feedback_job_when_enabled(): void
    {
        Queue::fake();

        config([
            'whatsapp_agent.comanda_feedback_enabled' => true,
            'whatsapp_agent.comanda_feedback_delay_minutes' => 45,
        ]);

        $this->actingAs($this->admin());
        $order = $this->createDineInOrder(comanda: 4, total: 30, customer: $this->customer());

        app(ComandaBillService::class)->closeComanda(4, 'pix');

        Queue::assertPushed(SendComandaFeedbackWhatsAppJob::class, function (SendComandaFeedbackWhatsAppJob $job) use ($order) {
            return $job->comandaNumber === 4
                && $job->orderIds === [$order->id]
                && $job->delay !== null;
        });
    }

    public function test_closing_comanda_does_not_queue_feedback_when_disabled(): void
    {
        Queue::fake();

        config(['whatsapp_agent.comanda_feedback_enabled' => false]);

        $this->actingAs($this->admin());
        $this->createDineInOrder(comanda: 4, total: 30, customer: $this->customer());

        app(ComandaBillService::class)->closeComanda(4, 'pix');

        Queue::assertNotPushed(SendComandaFeedbackWhatsAppJob::class);
    }

    public function test_feedback_job_sends_whatsapp_with_dish_names(): void
    {
        Http::fake([
            '*/message/sendText/*' => Http::response(['key' => ['id' => 'msg-feedback-1']], 200),
        ]);

        \App\Models\Setting::setMany('whatsapp_agent', [
            'comanda_feedback_enabled' => true,
            'comanda_feedback_message' => 'Oi {customer_name}! Como foi {items} no {restaurant_name}?',
            'restaurant_name' => 'Bella Bistrô',
        ]);
        \App\Models\Setting::setMany('evolution', [
            'enabled' => true,
            'base_url' => 'http://evolution.test',
            'api_key' => 'test-key',
            'instance' => 'restaurant',
        ]);
        \App\Support\AppSettings::loadIntoConfig();

        $customer = $this->customer();
        $order = $this->createDineInOrder(comanda: 7, total: 42, customer: $customer, productName: 'Filé à parmegiana');

        $job = new SendComandaFeedbackWhatsAppJob(7, [$order->id], today()->toDateString());
        $job->handle(app(\App\Services\WhatsAppService::class));

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'phone' => '5511999887766',
            'status' => 'sent',
        ]);

        $message = WhatsAppMessage::query()->first();
        $this->assertStringContainsString('Filé à parmegiana', $message->message);
        $this->assertStringContainsString('Bella Bistrô', $message->message);
        $this->assertStringContainsString('Maria Feedback', $message->message);
        $this->assertSame('comanda_feedback', $message->metadata['purpose'] ?? null);

        $this->assertDatabaseHas('customer_interactions', [
            'customer_id' => $customer->id,
            'type' => 'feedback',
        ]);

        Http::assertSentCount(1);
    }

    public function test_feedback_job_skips_when_no_phone(): void
    {
        Http::fake();

        \App\Models\Setting::setMany('whatsapp_agent', [
            'comanda_feedback_enabled' => true,
        ]);
        \App\Models\Setting::setMany('evolution', [
            'enabled' => true,
            'base_url' => 'http://evolution.test',
            'api_key' => 'test-key',
            'instance' => 'restaurant',
        ]);
        \App\Support\AppSettings::loadIntoConfig();

        $order = $this->createDineInOrder(comanda: 2, total: 20, customer: null);

        $job = new SendComandaFeedbackWhatsAppJob(2, [$order->id], today()->toDateString());
        $job->handle(app(\App\Services\WhatsAppService::class));

        $this->assertSame(0, WhatsAppMessage::query()->count());
        $this->assertSame(0, CustomerInteraction::query()->count());
        Http::assertNothingSent();
    }

    public function test_settings_can_save_comanda_feedback_options(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('settings.whatsapp-agent.update'), [
            'restaurant_name' => 'Teste',
            'estimated_minutes' => 40,
            'human_pause_minutes' => 60,
            'schedule_min_minutes' => 30,
            'schedule_max_days' => 1,
            'comanda_feedback_enabled' => '1',
            'comanda_feedback_delay_minutes' => 20,
            'comanda_feedback_message' => 'Como foi {items}?',
        ])->assertRedirect(route('settings.index', ['tab' => 'whatsapp_agent']));

        $this->assertDatabaseHas('settings', [
            'group' => 'whatsapp_agent',
            'key' => 'comanda_feedback_enabled',
            'value' => '1',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'whatsapp_agent',
            'key' => 'comanda_feedback_delay_minutes',
            'value' => '20',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name' => 'Maria Feedback',
            'phone' => '5511999887766',
            'is_active' => true,
        ]);
    }

    private function createDineInOrder(int $comanda, float $total, ?Customer $customer = null, string $productName = 'Prato teste'): Order
    {
        $category = Category::create([
            'name' => 'Pratos',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $productName,
            'price' => $total,
            'is_available' => true,
        ]);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'type' => 'dine_in',
            'status' => 'served',
            'comanda_number' => $comanda,
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name ?? 'Mesa',
            'customer_phone' => $customer?->phone,
            'total' => $total,
            'delivery_fee' => 0,
            'user_id' => User::factory()->create(['role' => UserRole::Waiter])->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $total,
            'subtotal' => $total,
        ]);

        return $order;
    }
}
