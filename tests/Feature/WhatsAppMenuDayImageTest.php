<?php

namespace Tests\Feature;

use App\Services\ConversationalWhatsAppBotService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class WhatsAppMenuDayImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('public');
        Storage::disk('public')->put('whatsapp/saturday.jpg', 'sat');
        Storage::disk('public')->put('whatsapp/monday.jpg', 'mon');

        config([
            'app.timezone' => 'America/Sao_Paulo',
            'app.url' => 'http://localhost',
            'whatsapp_agent.enabled' => true,
            'whatsapp_agent.use_openai' => false,
            'whatsapp_agent.restaurant_name' => 'Bella Bistrô',
            'whatsapp_agent.menu_image' => null,
            'whatsapp_agent.menu_images' => [
                'monday' => 'whatsapp/monday.jpg',
                'tuesday' => null,
                'wednesday' => null,
                'thursday' => null,
                'friday' => null,
                'saturday' => 'whatsapp/saturday.jpg',
                'sunday' => null,
            ],
            'general.opening_time' => '11:00',
            'general.closing_time' => '15:00',
            'general.open_days' => [
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
            ],
            'digital_menu.force_closed' => false,
            'evolution.enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cardapio_de_segunda_sends_monday_image_on_saturday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 16:30:00', 'America/Sao_Paulo'));

        $sentUrl = null;
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->atLeast()->once();
        $whatsApp->shouldReceive('sendImageToPhone')
            ->once()
            ->andReturnUsing(function (string $to, string $url) use (&$sentUrl) {
                $sentUrl = $url;

                return Mockery::mock(\App\Models\WhatsAppMessage::class);
            });
        $this->app->instance(WhatsAppService::class, $whatsApp);

        app(ConversationalWhatsAppBotService::class)
            ->process('5511999000500', 'cardápio de segunda', 'Carlos');

        $this->assertNotNull($sentUrl);
        $this->assertStringContainsString('monday.jpg', $sentUrl);
        $this->assertStringNotContainsString('saturday.jpg', $sentUrl);
    }

    public function test_bare_cardapio_while_closed_saturday_sends_monday_image(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 16:30:00', 'America/Sao_Paulo'));

        $sentUrl = null;
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->atLeast()->once();
        $whatsApp->shouldReceive('sendImageToPhone')
            ->once()
            ->andReturnUsing(function (string $to, string $url) use (&$sentUrl) {
                $sentUrl = $url;

                return Mockery::mock(\App\Models\WhatsAppMessage::class);
            });
        $this->app->instance(WhatsAppService::class, $whatsApp);

        app(ConversationalWhatsAppBotService::class)
            ->process('5511999000501', 'cardápio', 'Carlos');

        $this->assertNotNull($sentUrl);
        $this->assertStringContainsString('monday.jpg', $sentUrl);
    }

    public function test_tool_send_menu_image_accepts_day_argument(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 16:30:00', 'America/Sao_Paulo'));

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendToPhone')->zeroOrMoreTimes();
        $whatsApp->shouldReceive('sendImageToPhone')->once();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $result = app(ConversationalWhatsAppBotService::class)
            ->toolSendMenuImage('5511999000502', 'Carlos', 'segunda');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['sent']);
        $this->assertSame('monday', $result['day']);
        $this->assertSame('Segunda-feira', $result['day_label']);
    }
}
