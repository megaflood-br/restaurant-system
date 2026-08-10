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
}
