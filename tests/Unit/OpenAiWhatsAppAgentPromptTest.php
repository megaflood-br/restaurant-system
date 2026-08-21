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
        $bot->shouldReceive('restaurantAddress')->andReturn('Rua Exemplo, 100');
        $bot->shouldReceive('openingHoursLabel')->andReturn('11:00 às 15:00');
        $bot->shouldReceive('openingHoursSnapshot')->andReturn([
            'is_open' => true,
            'opening' => '11:00',
            'closing' => '15:00',
            'force_closed' => false,
        ])->byDefault();
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
        $this->assertStringNotContainsString('também chame send_menu_image junto com uma mensagem curta de boas-vindas', $system);
        $this->assertStringContainsString('use SOMENTE o campo description', $system);
        $this->assertStringContainsString('requires_side=true', $system);
        $this->assertStringContainsString('cardápio de segunda', $system);
        $this->assertStringContainsString('NÃO chame add_to_cart de novo', $system);
        $this->assertStringContainsString('already_in_cart=true', $system);
        $this->assertStringContainsString('Endereço do restaurante: Rua Exemplo, 100', $system);
        $this->assertStringContainsString('NUNCA use como localização do restaurante', $system);
        $this->assertStringContainsString('O endereço do cliente NÃO é o do restaurante', $system);
    }
}
