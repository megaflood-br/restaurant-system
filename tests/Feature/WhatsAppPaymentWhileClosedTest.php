<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ConversationalWhatsAppBotService;
use App\Services\OpenAiWhatsAppAgentService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class WhatsAppPaymentWhileClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'app.timezone' => 'America/Sao_Paulo',
            'whatsapp_agent.enabled' => true,
            'whatsapp_agent.use_openai' => true,
            'whatsapp_agent.restaurant_name' => 'Bella Bistrô',
            'evolution.enabled' => true,
            'general.opening_time' => '11:00',
            'general.closing_time' => '15:00',
            'digital_menu.force_closed' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cartao_at_payment_while_closed_creates_order_without_openai_restart(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 23:03:00', 'America/Sao_Paulo'));

        $category = Category::create([
            'name' => 'Pratos',
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Filé de frango grelhado',
            'price' => 20,
            'is_available' => true,
            'requires_side' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'label' => 'P',
            'price' => 20,
            'sort_order' => 1,
            'is_available' => true,
        ]);

        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')->never();
        $this->app->instance(OpenAiWhatsAppAgentService::class, $openAi);

        $messages = [];
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')
            ->atLeast()->once()
            ->andReturnUsing(function (string $phone, string $message) use (&$messages) {
                $messages[] = $message;

                return null;
            });
        $whatsApp->shouldReceive('sendImageToPhone')->never();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $phone = '5511999000600';

        $setSession = new ReflectionMethod(ConversationalWhatsAppBotService::class, 'setSession');
        $setSession->setAccessible(true);
        $setSession->invoke($bot, $phone, [
            'state' => 'payment',
            'cart' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]],
            'side' => 'Batata frita',
            'extras_notes' => null,
            'extras_completed' => true,
            'order_type' => 'delivery',
            'delivery_address' => 'Rua Machado de Assis, 465',
            'delivery_fee' => 4.0,
            'scheduled_label' => 'amanhã às 12:00',
            'scheduled_for' => Carbon::parse('2026-08-21 12:00:00', 'America/Sao_Paulo')->toIso8601String(),
        ]);

        $bot->process($phone, 'cartão', 'Carlos');

        $this->assertSame(1, Order::query()->count());
        $order = Order::query()->first();
        $this->assertSame('credit', $order->payment_method);
        $this->assertStringContainsString('Filé', $order->items->first()?->product_name ?? $order->items->first()?->name ?? '');

        $combined = mb_strtolower(implode("\n", $messages));
        $this->assertStringNotContainsString('estamos fechados', $combined);
        $this->assertStringNotContainsString('posso agendar seu pedido', $combined);
        $this->assertStringNotContainsString('que prato você gostaria', $combined);
    }
}
