<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Services\ConversationalWhatsAppBotService;
use App\Services\OpenAiWhatsAppAgentService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class WhatsAppRestaurantLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'whatsapp_agent.enabled' => true,
            'whatsapp_agent.use_openai' => true,
            'whatsapp_agent.restaurant_name' => 'Bella Bistrô',
            'evolution.enabled' => true,
            'general.address' => 'Rua Augusta, 1000',
        ]);
    }

    public function test_onde_fica_replies_with_restaurant_address_not_customer_home(): void
    {
        Customer::create([
            'name' => 'Carlos',
            'phone' => '5511999000310',
            'address' => 'Rua Machado de Assis, 465',
            'neighborhood' => 'Vila Mariana',
            'city' => 'São Paulo',
            'state' => 'SP',
            'is_active' => true,
        ]);

        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')->never();
        $this->app->instance(OpenAiWhatsAppAgentService::class, $openAi);

        $messages = [];
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')
            ->once()
            ->andReturnUsing(function (string $phone, string $message) use (&$messages) {
                $messages[] = $message;

                return null;
            });
        $whatsApp->shouldReceive('sendImageToPhone')->never();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $bot->process('5511999000310', 'onde fica?', 'Carlos');

        $combined = implode("\n", $messages);
        $this->assertStringContainsString('Rua Augusta, 1000', $combined);
        $this->assertStringNotContainsString('Machado de Assis', $combined);
        $this->assertStringNotContainsString('Vila Mariana', $combined);
    }

    public function test_onde_fica_during_ordering_does_not_reset_cart(): void
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
        ]);
        $openAi = Mockery::mock(OpenAiWhatsAppAgentService::class);
        $openAi->shouldReceive('handle')->never();
        $this->app->instance(OpenAiWhatsAppAgentService::class, $openAi);

        $messages = [];
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')
            ->once()
            ->andReturnUsing(function (string $phone, string $message) use (&$messages) {
                $messages[] = $message;

                return null;
            });
        $whatsApp->shouldReceive('sendImageToPhone')->never();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $bot = app(ConversationalWhatsAppBotService::class);
        $phone = '5511999000311';

        $method = new ReflectionMethod(ConversationalWhatsAppBotService::class, 'setSession');
        $method->setAccessible(true);
        $method->invoke($bot, $phone, [
            'state' => 'ordering',
            'cart' => [[
                'product_id' => $product->id,
                'variant_id' => null,
                'quantity' => 1,
                'name' => 'Cupim Assado',
            ]],
            'scheduled_label' => 'hoje às 12:00',
        ]);

        $bot->process($phone, 'onde fica o restaurante', 'Carlos');

        $snapshot = $bot->sessionSnapshot($phone);
        $this->assertSame('ordering', $snapshot['state']);
        $this->assertCount(1, $snapshot['cart']);
        $this->assertSame('hoje às 12:00', $snapshot['scheduled_label']);
        $this->assertStringContainsString('Rua Augusta, 1000', implode("\n", $messages));
    }
}
