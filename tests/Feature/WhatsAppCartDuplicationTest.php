<?php

namespace Tests\Feature;

use App\Models\Category;
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

class WhatsAppCartDuplicationTest extends TestCase
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
            'general.closing_time' => '23:00',
            'digital_menu.force_closed' => false,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-20 22:31:00', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_php_preregister_plus_openai_add_to_cart_does_not_duplicate_quantity(): void
    {
        $product = $this->seedCupimAssado(requiresSide: true);

        $bot = app(ConversationalWhatsAppBotService::class);
        $phone = '5511999000400';
        $userText = 'um cupim p';

        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')
            ->once()
            ->andReturnUsing(function () use ($bot, $phone, $userText) {
                // Simula a OpenAI chamando add_to_cart no mesmo turno após o pré-registro PHP.
                $result = $bot->toolAddToCart($phone, [
                    'items' => [[
                        'product_name' => 'Cupim Assado',
                        'variant_label' => 'P',
                        'quantity' => 1,
                    ]],
                ], 'Carlos', $userText);

                $this->assertTrue($result['ok']);
                $this->assertTrue($result['already_in_cart'] ?? false);

                return true;
            });
        $this->app->instance(OpenAiWhatsAppAgentService::class, $openAi);

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->zeroOrMoreTimes();
        $whatsApp->shouldReceive('sendImageToPhone')->never();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot->process($phone, $userText, 'Carlos');

        $snapshot = $bot->sessionSnapshot($phone);
        $this->assertCount(1, $snapshot['cart']);
        $this->assertSame(1, $snapshot['cart'][0]['quantity']);
        $this->assertStringContainsString('Cupim', $snapshot['cart'][0]['name']);
        $this->assertSame($product->id, Product::query()->where('name', 'Cupim Assado')->value('id'));
    }

    public function test_fritas_while_ordering_sets_side_without_duplicating_cart(): void
    {
        $product = $this->seedCupimAssado(requiresSide: true);
        $variant = $product->variants->firstWhere('label', 'P');

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
        $phone = '5511999000401';

        $setSession = new ReflectionMethod(ConversationalWhatsAppBotService::class, 'setSession');
        $setSession->setAccessible(true);
        $setSession->invoke($bot, $phone, [
            'state' => 'ordering',
            'cart' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]],
        ]);

        $bot->process($phone, 'fritas', 'Carlos');

        $snapshot = $bot->sessionSnapshot($phone);
        $this->assertSame('extras', $snapshot['state']);
        $this->assertCount(1, $snapshot['cart']);
        $this->assertSame(1, $snapshot['cart'][0]['quantity']);

        $getSession = new ReflectionMethod(ConversationalWhatsAppBotService::class, 'getSession');
        $getSession->setAccessible(true);
        $raw = $getSession->invoke($bot, $phone);
        $this->assertSame('Batata frita', $raw['side'] ?? null);

        $combined = mb_strtolower(implode("\n", $messages));
        $this->assertStringNotContainsString('tamanhos p, m ou g', $combined);
    }

    public function test_second_identical_order_message_still_adds_again_on_new_turn(): void
    {
        $this->seedCupimAssado(requiresSide: false);

        $bot = app(ConversationalWhatsAppBotService::class);
        $phone = '5511999000402';

        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')->andReturn(true);
        $this->app->instance(OpenAiWhatsAppAgentService::class, $openAi);

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->zeroOrMoreTimes();
        $whatsApp->shouldReceive('sendImageToPhone')->never();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot->process($phone, 'um cupim p', 'Carlos');
        $bot->process($phone, 'um cupim p', 'Carlos');

        $snapshot = $bot->sessionSnapshot($phone);
        $this->assertCount(1, $snapshot['cart']);
        $this->assertSame(2, $snapshot['cart'][0]['quantity']);
    }

    private function seedCupimAssado(bool $requiresSide): Product
    {
        $category = Category::create([
            'name' => 'Pratos',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cupim Assado',
            'price' => 28,
            'is_available' => true,
            'requires_side' => $requiresSide,
        ]);

        foreach ([['P', 28, 1], ['M', 38, 2], ['G', 48, 3]] as [$label, $price, $sort]) {
            ProductVariant::create([
                'product_id' => $product->id,
                'label' => $label,
                'price' => $price,
                'sort_order' => $sort,
                'is_available' => true,
            ]);
        }

        return $product->fresh('variants');
    }
}
