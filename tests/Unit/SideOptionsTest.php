<?php

namespace Tests\Unit;

use App\Support\SideOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SideOptionsTest extends TestCase
{
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
