<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Support\SideOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SideOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_default_side_options(): void
    {
        config(['whatsapp_agent.side_options' => ['Batata frita', 'Legumes']]);

        $this->assertTrue(SideOptions::enabled());
        $this->assertSame(['Batata frita', 'Legumes'], SideOptions::all());
        $this->assertStringContainsString('1. Batata frita', SideOptions::listForMessage());
        $this->assertStringContainsString('2. Legumes', SideOptions::listForMessage());
    }

    #[DataProvider('sideChoices')]
    public function test_it_resolves_side_choices(string $input, string $expected): void
    {
        config(['whatsapp_agent.side_options' => ['Batata frita', 'Legumes']]);

        $this->assertSame($expected, SideOptions::resolve($input));
    }

    public function test_it_rejects_unknown_side(): void
    {
        config(['whatsapp_agent.side_options' => ['Batata frita', 'Legumes']]);

        $this->assertNull(SideOptions::resolve('arroz'));
    }

    public function test_needed_for_cart_skips_products_without_side(): void
    {
        config(['whatsapp_agent.side_options' => ['Batata frita', 'Legumes']]);

        $category = Category::create([
            'name' => 'Pratos',
            'is_active' => true,
        ]);

        $feijoada = Product::create([
            'category_id' => $category->id,
            'name' => 'Feijoada',
            'price' => 45,
            'is_available' => true,
            'requires_side' => false,
        ]);

        $this->assertFalse(SideOptions::neededForCart([
            ['product_id' => $feijoada->id, 'quantity' => 1],
        ]));
    }

    public function test_needed_for_cart_true_when_any_product_requires_side(): void
    {
        config(['whatsapp_agent.side_options' => ['Batata frita', 'Legumes']]);

        $category = Category::create([
            'name' => 'Pratos',
            'is_active' => true,
        ]);

        $feijoada = Product::create([
            'category_id' => $category->id,
            'name' => 'Feijoada',
            'price' => 45,
            'is_available' => true,
            'requires_side' => false,
        ]);

        $strogonoff = Product::create([
            'category_id' => $category->id,
            'name' => 'Strogonoff',
            'price' => 30,
            'is_available' => true,
            'requires_side' => true,
        ]);

        $this->assertTrue(SideOptions::neededForCart([
            ['product_id' => $feijoada->id, 'quantity' => 1],
            ['product_id' => $strogonoff->id, 'quantity' => 1],
        ]));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function sideChoices(): array
    {
        return [
            'number 1' => ['1', 'Batata frita'],
            'number 2' => ['2', 'Legumes'],
            'fritas alias' => ['fritas', 'Batata frita'],
            'full name' => ['Batata frita', 'Batata frita'],
            'legumes' => ['legumes', 'Legumes'],
        ];
    }
}
