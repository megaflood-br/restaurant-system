<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\EvolutionWebhookController;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class EvolutionWebhookDedupeTest extends TestCase
{
    public function test_duplicate_evolution_message_is_processed_once(): void
    {
        Cache::flush();

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('handleInboundMessage')->once();

        $payload = [
            'event' => 'messages.upsert',
            'data' => [
                'key' => [
                    'id' => 'ABC123',
                    'fromMe' => false,
                    'remoteJid' => '5511999999999@s.whatsapp.net',
                ],
                'message' => [
                    'conversation' => 'oi',
                ],
                'pushName' => 'Carlos',
            ],
        ];

        $controller = new EvolutionWebhookController;

        $controller(Request::create('/api/webhooks/evolution', 'POST', $payload), $whatsApp);
        $controller(Request::create('/api/webhooks/evolution', 'POST', $payload), $whatsApp);
    }

    public function test_group_message_is_ignored_even_when_participant_has_phone(): void
    {
        Cache::flush();

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('handleInboundMessage')->never();

        $payload = [
            'event' => 'messages.upsert',
            'data' => [
                'key' => [
                    'id' => 'GROUP001',
                    'fromMe' => false,
                    'remoteJid' => '120363012345678901@g.us',
                    'participant' => '5511888777666@s.whatsapp.net',
                    'participantAlt' => '5511888777666@s.whatsapp.net',
                ],
                'message' => [
                    'conversation' => 'quero strogonoff',
                ],
                'pushName' => 'Cliente no grupo',
            ],
        ];

        $controller = new EvolutionWebhookController;
        $response = $controller(Request::create('/api/webhooks/evolution', 'POST', $payload), $whatsApp);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_group_message_with_remote_jid_alt_phone_is_still_ignored(): void
    {
        Cache::flush();

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('handleInboundMessage')->never();

        $payload = [
            'event' => 'messages.upsert',
            'data' => [
                'key' => [
                    'id' => 'GROUP002',
                    'fromMe' => false,
                    'remoteJid' => '120363999999999999@g.us',
                    'remoteJidAlt' => '5511777666555@s.whatsapp.net',
                    'participant' => '5511777666555@s.whatsapp.net',
                ],
                'message' => [
                    'conversation' => 'cardápio',
                ],
                'pushName' => 'Outro no grupo',
            ],
        ];

        $controller = new EvolutionWebhookController;
        $controller(Request::create('/api/webhooks/evolution', 'POST', $payload), $whatsApp);
    }

    public function test_direct_message_with_lid_and_alt_phone_is_processed(): void
    {
        Cache::flush();

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('handleInboundMessage')
            ->once()
            ->withArgs(function (string $phone, string $text) {
                return $phone === '5511999000111' && $text === 'oi';
            });

        $payload = [
            'event' => 'messages.upsert',
            'data' => [
                'key' => [
                    'id' => 'DM001',
                    'fromMe' => false,
                    'remoteJid' => '123456789012345@lid',
                    'remoteJidAlt' => '5511999000111@s.whatsapp.net',
                ],
                'message' => [
                    'conversation' => 'oi',
                ],
                'pushName' => 'Cliente DM',
            ],
        ];

        $controller = new EvolutionWebhookController;
        $controller(Request::create('/api/webhooks/evolution', 'POST', $payload), $whatsApp);
    }

    public function test_newsletter_message_is_ignored(): void
    {
        Cache::flush();

        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('handleInboundMessage')->never();

        $payload = [
            'event' => 'messages.upsert',
            'data' => [
                'key' => [
                    'id' => 'NEWS001',
                    'fromMe' => false,
                    'remoteJid' => '123456789012345@newsletter',
                ],
                'message' => [
                    'conversation' => 'promo',
                ],
            ],
        ];

        $controller = new EvolutionWebhookController;
        $controller(Request::create('/api/webhooks/evolution', 'POST', $payload), $whatsApp);
    }
}
