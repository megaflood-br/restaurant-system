<?php

namespace Tests\Feature;

use App\Services\ConversationalWhatsAppBotService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class WhatsAppWelcomeNoAutoMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('public');
        Storage::disk('public')->put('menu/hoje.jpg', 'fake-image');

        config([
            'whatsapp_agent.enabled' => true,
            'whatsapp_agent.use_openai' => false,
            'whatsapp_agent.restaurant_name' => 'Bella Bistrô',
            'whatsapp_agent.menu_image' => 'menu/hoje.jpg',
            'evolution.enabled' => true,
        ]);
    }

    public function test_greeting_does_not_send_menu_image(): void
    {
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->once();
        $whatsApp->shouldReceive('sendImageToPhone')->never();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        app(ConversationalWhatsAppBotService::class)
            ->process('5511999000100', 'ola', 'Carlos');
    }

    public function test_explicit_menu_request_sends_menu_image(): void
    {
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->atLeast()->once();
        $whatsApp->shouldReceive('sendImageToPhone')->once();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        app(ConversationalWhatsAppBotService::class)
            ->process('5511999000101', 'cardápio', 'Carlos');
    }
}
