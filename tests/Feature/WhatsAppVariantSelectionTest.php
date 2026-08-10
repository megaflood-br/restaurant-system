<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ConversationalWhatsAppBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppVariantSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_to_cart_does_not_accept_invented_openai_size(): void
    {
        $product = $this->createTutuWithSizes();
        $bot = app(ConversationalWhatsAppBotService::class);

        $result = $bot->toolAddToCart('5511999000001', [
            'items' => [[
                'product_name' => 'Tutu de Feijão com Bisteca',
                'variant_label' => 'P',
                'quantity' => 1,
            ]],
        ], 'Sandy', 'Quero um tutu');

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['needs_variant']);
        $this->assertSame([], $result['added']);
        $this->assertStringContainsString('tamanhos', $result['ask_customer']);

        $snapshot = $bot->sessionSnapshot('5511999000001');
        $this->assertSame([], $snapshot['cart']);
    }

    public function test_size_only_reply_completes_pending_variant(): void
    {
        $product = $this->createTutuWithSizes();
        $bot = app(ConversationalWhatsAppBotService::class);

        $bot->toolAddToCart('5511999000002', [
            'items' => [[
                'product_name' => 'tutu',
                'quantity' => 1,
            ]],
        ], 'Sandy', 'quero um tutu');

        $result = $bot->toolAddToCart('5511999000002', [
            'items' => [[
                'product_name' => 'tutu',
                'variant_label' => 'M',
                'quantity' => 1,
            ]],
        ], 'Sandy', 'M');

        $this->assertTrue($result['ok']);

        $snapshot = $bot->sessionSnapshot('5511999000002');
        $this->assertCount(1, $snapshot['cart']);
        $this->assertStringContainsString('(M)', $snapshot['cart'][0]['name']);
    }

    public function test_explicit_size_in_user_message_is_accepted(): void
    {
        $this->createTutuWithSizes();
        $bot = app(ConversationalWhatsAppBotService::class);

        $result = $bot->toolAddToCart('5511999000003', [
            'items' => [[
                'product_name' => 'Tutu de Feijão com Bisteca',
                'variant_label' => 'G',
                'quantity' => 1,
            ]],
        ], 'Sandy', 'quero tutu G');

        $this->assertTrue($result['ok']);
        $this->assertSame(['1x Tutu de Feijão com Bisteca (G)'], $result['added']);
    }

    private function createTutuWithSizes(): Product
    {
        $category = Category::create([
            'name' => 'Pratos',
            'description' => 'Teste',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Tutu de Feijão com Bisteca',
            'description' => 'Tutu',
            'price' => 20,
            'is_available' => true,
        ]);

        foreach ([['P', 20, 1], ['M', 25, 2], ['G', 30, 3]] as [$label, $price, $sort]) {
            ProductVariant::create([
                'product_id' => $product->id,
                'label' => $label,
                'price' => $price,
                'sort_order' => $sort,
                'is_available' => true,
            ]);
        }

        return $product->fresh('variants');
    }
}
