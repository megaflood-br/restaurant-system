<?php

namespace Tests\Feature;

use App\Services\ConversationalWhatsAppBotService;
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
}
