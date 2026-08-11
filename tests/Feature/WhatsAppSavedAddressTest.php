<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\ConversationalWhatsAppBotService;
use App\Services\DeliveryFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsAppSavedAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_asks_to_confirm_saved_customer_address(): void
    {
        Customer::create([
            'name' => 'Carlos',
            'phone' => '5511999999999',
            'address' => 'Rua das Flores, 100',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
            'is_active' => true,
        ]);

        $result = app(ConversationalWhatsAppBotService::class)
            ->toolSetExtras('5511999999999', 'sem cebola', 'Carlos');

        $this->assertTrue($result['ok']);
        $this->assertSame('address', $result['next']);
        $this->assertSame('Rua das Flores, 100, Centro, São Paulo, SP', $result['saved_address']);
        $this->assertStringContainsString('Rua das Flores, 100', $result['message']);
        $this->assertStringContainsStringIgnoringCase('mesmo', $result['message']);
    }

    public function test_confirming_saved_address_quotes_delivery(): void
    {
        Customer::create([
            'name' => 'Carlos',
            'phone' => '5511888777666',
            'address' => 'Av Paulista, 1000',
            'city' => 'São Paulo',
            'state' => 'SP',
            'is_active' => true,
        ]);

        $fee = Mockery::mock(DeliveryFeeService::class);
        $fee->shouldReceive('diagnoseAddress')
            ->once()
            ->with('Av Paulista, 1000, São Paulo, SP')
            ->andReturn([
                'quote' => [
                    'distance_km' => 2.5,
                    'delivery_area_id' => 1,
                    'delivery_area_name' => 'Zona 1',
                    'delivery_fee' => 8.0,
                ],
                'reason' => null,
                'distance_km' => 2.5,
            ]);

        $this->app->instance(DeliveryFeeService::class, $fee);

        $bot = app(ConversationalWhatsAppBotService::class);
        $bot->toolSetExtras('5511888777666', 'ok', 'Carlos');
        $result = $bot->toolQuoteDelivery('5511888777666', 'sim', 'Carlos');

        $this->assertTrue($result['ok']);
        $this->assertSame(8.0, $result['delivery_fee']);
        $this->assertTrue($result['already_sent_to_customer'] ?? false);
        $this->assertContains($result['next'], ['schedule', 'payment']);
        $this->assertArrayNotHasKey('pix_key', $result);

        $snapshot = $bot->sessionSnapshot('5511888777666');
        $this->assertSame('delivery', $snapshot['order_type']);
        $this->assertFalse($snapshot['saved_address_prompt']);
        $this->assertContains($snapshot['state'], ['schedule', 'payment']);
        $this->assertNull($snapshot['payment_method']);
    }

    public function test_declining_saved_address_asks_for_a_new_one(): void
    {
        Customer::create([
            'name' => 'Carlos',
            'phone' => '5511777666555',
            'address' => 'Rua A, 10',
            'city' => 'São Paulo',
            'state' => 'SP',
            'is_active' => true,
        ]);

        $bot = app(ConversationalWhatsAppBotService::class);
        $bot->toolSetExtras('5511777666555', 'ok', 'Carlos');
        $result = $bot->toolQuoteDelivery('5511777666555', 'não', 'Carlos');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['ask_new_address']);
        $this->assertStringContainsStringIgnoringCase('endereço', $result['message']);
    }
}
