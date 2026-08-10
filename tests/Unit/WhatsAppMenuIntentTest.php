<?php

namespace Tests\Unit;

use App\Support\WhatsAppMenuIntent;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsAppMenuIntentTest extends TestCase
{
    #[DataProvider('menuRequests')]
    public function test_it_detects_menu_image_requests(string $text): void
    {
        $this->assertTrue(WhatsAppMenuIntent::matches($text), "Failed for: {$text}");
    }

    #[DataProvider('nonMenuRequests')]
    public function test_it_ignores_non_menu_messages(string $text): void
    {
        $this->assertFalse(WhatsAppMenuIntent::matches($text), "Incorrectly matched: {$text}");
    }

    /** @return array<string, array{0: string}> */
    public static function menuRequests(): array
    {
        return [
            'exact' => ['cardapio'],
            'accent' => ['Cardápio'],
            'today' => ['cardapio de hoje'],
            'accent today' => ['Cardápio de hoje'],
            'menu today' => ['menu de hoje'],
            'send it' => ['manda o cardápio'],
            'see menu' => ['ver o cardapio por favor'],
        ];
    }

    /** @return array<string, array{0: string}> */
    public static function nonMenuRequests(): array
    {
        return [
            'greeting' => ['oi'],
            'order dish' => ['quero strogonoff P'],
            'address' => ['rua machado de assis 100'],
            'empty' => ['   '],
        ];
    }
}
