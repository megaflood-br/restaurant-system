<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ConversationalWhatsAppBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWrongDishFixTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Product> */
    private array $products;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create([
            'name' => 'Pratos',
            'is_active' => true,
        ]);

        foreach ([
            ['Cupim', 'cupim'],
            ['Contra filé Acebolado', 'contra'],
        ] as [$name, $key]) {
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

            $this->products[$key] = $product->fresh('variants');
        }
    }

    public function test_user_text_overrides_wrong_openai_product_when_correcting_dish(): void
    {
        $bot = app(ConversationalWhatsAppBotService::class);
        $phone = '5511999000100';

        $pending = $bot->toolAddToCart($phone, [
            'items' => [[
                'product_name' => 'Contra filé Acebolado',
                'quantity' => 1,
            ]],
        ], 'Cliente', 'sim');

        $this->assertFalse($pending['ok']);
        $this->assertNotEmpty($pending['needs_variant']);

        $result = $bot->toolAddToCart($phone, [
            'items' => [[
                'product_name' => 'Contra filé Acebolado',
                'variant_label' => 'P',
                'quantity' => 1,
            ]],
        ], 'Cliente', 'Quero cupim p');

        $this->assertTrue($result['ok']);
        $this->assertSame(['1x Cupim (P)'], $result['added']);

        $snapshot = $bot->sessionSnapshot($phone);
        $this->assertCount(1, $snapshot['cart']);
        $this->assertStringContainsString('Cupim', $snapshot['cart'][0]['name']);
        $this->assertStringNotContainsString('Contra filé', $snapshot['cart'][0]['name']);
    }

    public function test_pure_confirmation_does_not_complete_pending_variant_with_wrong_dish(): void
    {
        $bot = app(ConversationalWhatsAppBotService::class);
        $phone = '5511999000101';

        $pending = $bot->toolAddToCart($phone, [
            'items' => [[
                'product_name' => 'Contra filé Acebolado',
                'quantity' => 1,
            ]],
        ], 'Cliente', 'quero contra filé');

        $this->assertFalse($pending['ok']);
        $this->assertNotEmpty($pending['needs_variant']);

        $result = $bot->toolAddToCart($phone, [
            'items' => [[
                'product_name' => 'Contra filé Acebolado',
                'quantity' => 1,
            ]],
        ], 'Cliente', 'sim');

        $this->assertFalse($result['ok']);
        $this->assertSame([], $result['added']);
    }
}
