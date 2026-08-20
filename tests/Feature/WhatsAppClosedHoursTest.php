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
use Tests\TestCase;

class WhatsAppClosedHoursTest extends TestCase
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

    public function test_force_closed_blocks_new_orders(): void
    {
        config(['digital_menu.force_closed' => true]);
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')
            ->once()
            ->withArgs(function (string $phone, string $message) {
                return str_contains($message, 'fechado');
            });
        $whatsApp->shouldReceive('sendImageToPhone')->never();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $bot->process('5511999000300', 'ola', 'Carlos');

        $snapshot = $bot->sessionSnapshot('5511999000300');
        $this->assertSame('welcome', $snapshot['state']);
        $this->assertSame([], $snapshot['cart']);
    }

    public function test_outside_hours_still_allows_greeting_for_scheduling(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 18:45:00', 'America/Sao_Paulo'));

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->atLeast()->once();
        $whatsApp->shouldReceive('sendImageToPhone')->never();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $bot->process('5511999000301', 'ola', 'Carlos');

        $snapshot = $bot->sessionSnapshot('5511999000301');
        $this->assertSame('ordering', $snapshot['state']);
    }

    public function test_greeting_while_open_starts_welcome_flow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->atLeast()->once();
        $whatsApp->shouldReceive('sendImageToPhone')->never();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $bot->process('5511999000302', 'ola', 'Carlos');

        $snapshot = $bot->sessionSnapshot('5511999000302');
        $this->assertSame('ordering', $snapshot['state']);
    }

    public function test_outside_hours_menu_item_is_registered_in_php_with_correct_dish(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 18:45:00', 'America/Sao_Paulo'));
        config(['whatsapp_agent.use_openai' => true]);

        $this->seedCupimAndContraFilé();

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
        $bot->process('5511999000303', 'Quero um cupim p', 'Carlos');

        $snapshot = $bot->sessionSnapshot('5511999000303');
        $this->assertCount(1, $snapshot['cart']);
        $this->assertStringContainsString('Cupim', $snapshot['cart'][0]['name']);
        $this->assertStringNotContainsString('Contra filé', $snapshot['cart'][0]['name']);

        $combined = implode("\n", $messages);
        $this->assertStringContainsString('fechados', mb_strtolower($combined));
        $this->assertStringContainsString('Cupim', $combined);
        $this->assertStringNotContainsString('Contra filé', $combined);
    }

    public function test_outside_hours_greeting_does_not_match_coca_cola(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 18:45:00', 'America/Sao_Paulo'));
        config(['whatsapp_agent.use_openai' => true]);

        $category = Category::create([
            'name' => 'Bebidas',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Coca Cola 200ml',
            'price' => 6,
            'is_available' => true,
        ]);

        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')->once()->andReturn(false);
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
        $bot->process('5511999000304', 'Ola', 'Carlos');

        $snapshot = $bot->sessionSnapshot('5511999000304');
        $this->assertSame([], $snapshot['cart']);

        $combined = implode("\n", $messages);
        $this->assertStringNotContainsString('Coca Cola', $combined);
    }

    private function seedCupimAndContraFilé(): void
    {
        $category = Category::create([
            'name' => 'Pratos',
            'is_active' => true,
        ]);

        foreach (['Cupim', 'Contra filé Acebolado'] as $name) {
            $product = Product::create([
                'category_id' => $category->id,
                'name' => $name,
                'price' => 30,
                'is_available' => true,
            ]);

            foreach ([['P', 30, 1], ['M', 40, 2], ['G', 50, 3]] as [$label, $price, $sort]) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'label' => $label,
                    'price' => $price,
                    'sort_order' => $sort,
                    'is_available' => true,
                ]);
            }
        }
    }
}
