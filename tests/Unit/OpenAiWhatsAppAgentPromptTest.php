<?php

namespace Tests\Unit;

use App\Services\ConversationalWhatsAppBotService;
use App\Services\OpenAiClient;
use App\Services\OpenAiWhatsAppAgentService;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class OpenAiWhatsAppAgentPromptTest extends TestCase
{
    public function test_system_prompt_forbids_menu_image_on_greeting(): void
    {
        $bot = Mockery::mock(ConversationalWhatsAppBotService::class);
        $bot->shouldReceive('sessionSnapshot')->andReturn([]);
        $bot->shouldReceive('menuSnapshot')->andReturn([]);
        $bot->shouldReceive('restaurantDisplayName')->andReturn('Bella Bistrô');
        $bot->shouldReceive('openingHoursLabel')->andReturn('11:00 às 15:00');
        $bot->shouldReceive('savedAddressForPhone')->andReturn(null);
        $bot->shouldReceive('normalizedPhoneKey')->andReturn('5511999000200');

        $agent = new OpenAiWhatsAppAgentService(
            Mockery::mock(OpenAiClient::class),
            $bot,
        );

        $reflection = new ReflectionClass($agent);
        $method = $reflection->getMethod('buildMessages');
        $method->setAccessible(true);

        $messages = $method->invoke($agent, '5511999000200', 'Carlos');
        $system = $messages[0]['content'] ?? '';

        $this->assertStringContainsString('NÃO chame send_menu_image', $system);
        $this->assertStringNotContainsString('Na primeira saudação, também chame send_menu_image', $system);
    }
}
