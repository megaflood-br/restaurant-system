<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
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

class WhatsAppOrderFlowFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'app.timezone' => 'America/Sao_Paulo',
            'whatsapp_agent.enabled' => true,
            'whatsapp_agent.use_openai' => false,
            'whatsapp_agent.restaurant_name' => 'Bella Bistrô',
            'whatsapp_agent.pix_key' => '1194396-1625',
            'whatsapp_agent.scheduling_enabled' => true,
            'whatsapp_agent.schedule_min_minutes' => 15,
            'evolution.enabled' => true,
            'general.opening_time' => '11:00',
            'general.closing_time' => '15:00',
            'digital_menu.force_closed' => false,
            'whatsapp_agent.side_options' => ['Batata frita', 'Legumes'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pix_payment_creates_pending_order_immediately(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $product = $this->createStrogonoff();
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->atLeast()->once();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $phone = '5511999000400';
        $this->seedPaymentReadySession($bot, $phone, $product);

        $result = $bot->toolSetPayment($phone, 'pix', 'Carlos');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['order_created']);
        $this->assertTrue($result['already_sent_to_customer']);
        $this->assertTrue($result['awaiting_pix_proof']);
        $this->assertNotEmpty($result['order_number']);

        $order = Order::query()->where('order_number', $result['order_number'])->first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame('pix', $order->payment_method);
        $this->assertStringContainsString('Aguardando comprovante PIX', (string) $order->notes);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('pix_wait', $bot->sessionSnapshot($phone)['state']);
    }

    public function test_cash_payment_creates_order_and_does_not_lie_on_failure(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $product = $this->createStrogonoff();
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->atLeast()->once();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $phone = '5511999000404';
        $this->seedPaymentReadySession($bot, $phone, $product);

        $result = $bot->toolSetPayment($phone, 'dinheiro', 'Carlos');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['order_created']);
        $this->assertTrue($result['already_sent_to_customer']);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame('cash', Order::query()->first()->payment_method);
    }

    public function test_set_payment_with_empty_cart_returns_error(): void
    {
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->zeroOrMoreTimes();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $result = $bot->toolSetPayment('5511999000405', 'pix', 'Carlos');

        $this->assertFalse($result['ok']);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_payment_reply_is_handled_before_openai_and_creates_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $product = $this->createStrogonoff();
        $phone = '5511999000406';

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->atLeast()->once();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')->never();
        $this->app->instance(OpenAiWhatsAppAgentService::class, $openAi);

        config(['whatsapp_agent.use_openai' => true]);

        $bot = app(ConversationalWhatsAppBotService::class);
        $this->seedPaymentReadySession($bot, $phone, $product);

        $bot->process($phone, 'pode ser no pix', 'Carlos');

        $this->assertSame(1, Order::query()->count());
        $this->assertSame('pix', Order::query()->first()->payment_method);
        $this->assertSame('pix_wait', $bot->sessionSnapshot($phone)['state']);
    }

    public function test_set_payment_rejects_invented_pix_when_user_only_confirmed_address(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $product = $this->createStrogonoff();
        $phone = '5511999000407';

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->zeroOrMoreTimes();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $this->seedPaymentReadySession($bot, $phone, $product);

        $result = $bot->toolSetPayment($phone, 'pix', 'Carlos', [
            'user_text' => 'sim',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame(0, Order::query()->count());
        $this->assertStringContainsStringIgnoringCase('não escolheu', $result['error']);
    }

    public function test_set_payment_rejects_when_not_in_payment_state(): void
    {
        $product = $this->createStrogonoff();
        $phone = '5511999000408';
        $bot = app(ConversationalWhatsAppBotService::class);

        $method = new ReflectionMethod(ConversationalWhatsAppBotService::class, 'setSession');
        $method->setAccessible(true);
        $variant = $product->variants->first();
        $method->invoke($bot, $phone, [
            'state' => 'address',
            'saved_address_prompt' => true,
            'cart' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
                'name' => 'Strogonoff de Frango (P)',
                'unit_price' => 20.0,
            ]],
        ]);

        $result = $bot->toolSetPayment($phone, 'pix', 'Carlos', [
            'user_text' => 'pix',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame('address', $result['state']);
    }

    public function test_can_switch_from_pix_wait_to_cash(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $product = $this->createStrogonoff();
        $phone = '5511999000409';

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->atLeast()->once();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')->never();
        $this->app->instance(OpenAiWhatsAppAgentService::class, $openAi);

        config(['whatsapp_agent.use_openai' => true]);

        $bot = app(ConversationalWhatsAppBotService::class);
        $this->seedPaymentReadySession($bot, $phone, $product);
        $bot->toolSetPayment($phone, 'pix', 'Carlos');

        $this->assertSame('pix_wait', $bot->sessionSnapshot($phone)['state']);
        $this->assertSame(1, Order::query()->count());

        $bot->process($phone, 'dinheiro', 'Carlos');

        $order = Order::query()->first();
        $this->assertSame('cash', $order->payment_method);
        $this->assertStringNotContainsString('Aguardando comprovante PIX', (string) $order->notes);
        $this->assertSame('welcome', $bot->sessionSnapshot($phone)['state']);
    }

    public function test_confirming_saved_address_before_openai_does_not_send_pix(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        Customer::create([
            'name' => 'Carlos',
            'phone' => '5511999000410',
            'address' => 'Rua Teste, 10',
            'city' => 'São Paulo',
            'state' => 'SP',
            'is_active' => true,
        ]);

        $product = $this->createStrogonoff();
        $phone = '5511999000410';

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')
            ->atLeast()
            ->once()
            ->withArgs(function (string $to, string $message) {
                return ! str_contains(mb_strtolower($message), 'chave pix')
                    && ! str_contains(mb_strtolower($message), 'pix_key');
            });
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $fee = Mockery::mock(\App\Services\DeliveryFeeService::class);
        $fee->shouldReceive('quoteForAddress')->once()->andReturn([
            'distance_km' => 1.2,
            'delivery_area_id' => 1,
            'delivery_area_name' => 'Zona 1',
            'delivery_fee' => 5.0,
        ]);
        $this->app->instance(\App\Services\DeliveryFeeService::class, $fee);

        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')->never();
        $this->app->instance(OpenAiWhatsAppAgentService::class, $openAi);

        config(['whatsapp_agent.use_openai' => true]);

        $bot = app(ConversationalWhatsAppBotService::class);
        $method = new ReflectionMethod(ConversationalWhatsAppBotService::class, 'setSession');
        $method->setAccessible(true);
        $variant = $product->variants->first();
        $method->invoke($bot, $phone, [
            'state' => 'address',
            'saved_address_prompt' => true,
            'saved_address' => 'Rua Teste, 10, São Paulo, SP',
            'cart' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
                'name' => 'Strogonoff de Frango (P)',
                'unit_price' => 20.0,
            ]],
        ]);

        $bot->process($phone, 'sim', 'Carlos');

        $this->assertSame(0, Order::query()->count());
        $snapshot = $bot->sessionSnapshot($phone);
        $this->assertContains($snapshot['state'], ['schedule', 'payment']);
        $this->assertNull($snapshot['payment_method']);
    }

    public function test_side_reply_is_handled_before_openai_path(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $this->createStrogonoff();
        $phone = '5511999000401';

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->atLeast()->once();
        $whatsApp->shouldReceive('sendImageToPhone')->zeroOrMoreTimes();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')->never();
        $this->app->instance(OpenAiWhatsAppAgentService::class, $openAi);

        config(['whatsapp_agent.use_openai' => true]);

        $bot = app(ConversationalWhatsAppBotService::class);
        $bot->toolAddToCart($phone, [
            'items' => [[
                'product_name' => 'Strogonoff de Frango',
                'variant_label' => 'P',
                'quantity' => 1,
            ]],
        ], 'Carlos', 'strogonoff P');
        $bot->toolFinalizeItems($phone, 'Carlos');

        $this->assertSame('side', $bot->sessionSnapshot($phone)['state']);

        $bot->process($phone, 'Fritas', 'Carlos');

        $snapshot = $bot->sessionSnapshot($phone);
        $this->assertSame('extras', $snapshot['state']);
    }

    public function test_force_closed_blocks_new_orders(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));
        config(['digital_menu.force_closed' => true]);

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')
            ->once()
            ->withArgs(fn (string $phone, string $message) => str_contains(mb_strtolower($message), 'fechado'));
        $whatsApp->shouldReceive('sendImageToPhone')->never();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $bot->process('5511999000402', 'ola', 'Carlos');

        $snapshot = $bot->sessionSnapshot('5511999000402');
        $this->assertSame('welcome', $snapshot['state']);
        $this->assertSame([], $snapshot['cart']);
    }

    public function test_after_hours_still_allows_greeting_for_scheduling(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 21:45:00', 'America/Sao_Paulo'));

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->atLeast()->once();
        $whatsApp->shouldReceive('sendImageToPhone')->zeroOrMoreTimes();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $bot->process('5511999000403', 'ola', 'Carlos');

        $snapshot = $bot->sessionSnapshot('5511999000403');
        $this->assertSame('ordering', $snapshot['state']);
    }

    public function test_adding_items_while_waiting_for_address_does_not_insist_on_address(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $product = $this->createStrogonoff();
        $phone = '5511999000420';

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')
            ->atLeast()
            ->once()
            ->withArgs(function (string $to, string $message) {
                $lower = mb_strtolower($message);

                return ! str_contains($lower, 'endereço')
                    && ! str_contains($lower, 'endereco');
            });
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $fee = Mockery::mock(\App\Services\DeliveryFeeService::class);
        $fee->shouldReceive('quoteForAddress')->never();
        $this->app->instance(\App\Services\DeliveryFeeService::class, $fee);

        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')->never();
        $this->app->instance(OpenAiWhatsAppAgentService::class, $openAi);

        config(['whatsapp_agent.use_openai' => true]);

        $bot = app(ConversationalWhatsAppBotService::class);
        $method = new ReflectionMethod(ConversationalWhatsAppBotService::class, 'setSession');
        $method->setAccessible(true);
        $variant = $product->variants->first();
        $method->invoke($bot, $phone, [
            'state' => 'address',
            'saved_address_prompt' => false,
            'cart' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]],
            'side' => 'Batata frita',
            'extras_notes' => 'sem cebola',
            'extras_completed' => true,
        ]);

        $bot->process($phone, 'quero mais um strogonoff P', 'Carlos');

        $snapshot = $bot->sessionSnapshot($phone);
        $this->assertSame('ordering', $snapshot['state']);
        $this->assertCount(1, $snapshot['cart']);
        $this->assertSame(2, (int) $snapshot['cart'][0]['quantity']);
    }

    public function test_quero_mais_while_waiting_for_address_returns_to_ordering(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $product = $this->createStrogonoff();
        $phone = '5511999000421';

        $replies = [];
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')
            ->atLeast()
            ->once()
            ->andReturnUsing(function (string $to, string $message) use (&$replies) {
                $replies[] = $message;

                return Mockery::mock(\App\Models\WhatsAppMessage::class);
            });
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')->never();
        $this->app->instance(OpenAiWhatsAppAgentService::class, $openAi);

        config(['whatsapp_agent.use_openai' => true]);

        $bot = app(ConversationalWhatsAppBotService::class);
        $method = new ReflectionMethod(ConversationalWhatsAppBotService::class, 'setSession');
        $method->setAccessible(true);
        $variant = $product->variants->first();
        $method->invoke($bot, $phone, [
            'state' => 'address',
            'cart' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]],
            'extras_completed' => true,
            'side' => 'Batata frita',
        ]);

        $bot->process($phone, 'quero mais', 'Carlos');

        $this->assertSame('ordering', $bot->sessionSnapshot($phone)['state']);
        $this->assertNotEmpty($replies);
        $this->assertStringContainsString('pronto', mb_strtolower($replies[0]));
    }

    public function test_pronto_after_interrupt_skips_completed_side_and_extras(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $product = $this->createStrogonoff();
        $phone = '5511999000422';

        $replies = [];
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')
            ->atLeast()
            ->once()
            ->andReturnUsing(function (string $to, string $message) use (&$replies) {
                $replies[] = $message;

                return Mockery::mock(\App\Models\WhatsAppMessage::class);
            });
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $method = new ReflectionMethod(ConversationalWhatsAppBotService::class, 'setSession');
        $method->setAccessible(true);
        $variant = $product->variants->first();
        $method->invoke($bot, $phone, [
            'state' => 'ordering',
            'cart' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 2,
            ]],
            'side' => 'Batata frita',
            'extras_notes' => 'sem cebola',
            'extras_completed' => true,
        ]);

        $bot->process($phone, 'pronto', 'Carlos');

        $snapshot = $bot->sessionSnapshot($phone);
        $this->assertSame('address', $snapshot['state']);
        $joined = mb_strtolower(implode("\n", $replies));
        $this->assertStringNotContainsString('acompanhamento', $joined);
        $this->assertTrue(
            str_contains($joined, 'endereço')
            || str_contains($joined, 'endereco')
            || str_contains($joined, 'retirada')
        );
    }

    private function createStrogonoff(): Product
    {
        $category = Category::create([
            'name' => 'Pratos',
            'description' => 'Teste',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Strogonoff de Frango',
            'description' => 'Strogonoff',
            'price' => 20,
            'is_available' => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'label' => 'P',
            'price' => 20,
            'sort_order' => 1,
            'is_available' => true,
        ]);

        return $product->fresh('variants');
    }

    private function seedPaymentReadySession(ConversationalWhatsAppBotService $bot, string $phone, Product $product): void
    {
        $method = new ReflectionMethod(ConversationalWhatsAppBotService::class, 'setSession');
        $method->setAccessible(true);

        $variant = $product->variants->first();

        $method->invoke($bot, $phone, [
            'state' => 'payment',
            'cart' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
                'name' => 'Strogonoff de Frango (P)',
                'unit_price' => 20.0,
            ]],
            'side' => 'Batata frita',
            'extras_notes' => 'sem talher',
            'order_type' => 'delivery',
            'delivery_address' => 'rua buenos aires, 1036',
            'delivery_fee' => 4.0,
            'delivery_area_id' => null,
            'scheduled_for' => null,
            'scheduled_label' => 'o mais breve possível',
            'payment_method' => null,
            'order_claimed' => false,
        ]);
    }
}
