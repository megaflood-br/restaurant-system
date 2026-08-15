<?php

namespace Tests\Unit;

use App\Support\WeeklyMenuImages;
use App\Support\WhatsAppMenuIntent;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeeklyMenuImagesDayTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_day_key_from_text_parses_weekdays(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 16:00:00', 'America/Sao_Paulo')); // Saturday

        $this->assertSame('monday', WeeklyMenuImages::dayKeyFromText('cardápio de segunda'));
        $this->assertSame('monday', WeeklyMenuImages::dayKeyFromText('segunda-feira'));
        $this->assertSame('saturday', WeeklyMenuImages::dayKeyFromText('hoje'));
        $this->assertSame('sunday', WeeklyMenuImages::dayKeyFromText('amanhã'));
        $this->assertSame('monday', WeeklyMenuImages::dayKeyFromText('monday'));
        $this->assertNull(WeeklyMenuImages::dayKeyFromText('cardápio'));
    }

    public function test_intent_requested_day(): void
    {
        $this->assertSame('monday', WhatsAppMenuIntent::requestedDay('cardápio de segunda'));
        $this->assertSame('monday', WhatsAppMenuIntent::requestedDay('manda o cardapio de segunda feira'));
        $this->assertNull(WhatsAppMenuIntent::requestedDay('cardápio'));
        $this->assertTrue(WhatsAppMenuIntent::matches('cardápio de segunda'));
    }

    #[DataProvider('menuDayRequests')]
    public function test_it_detects_menu_requests_with_weekday(string $text): void
    {
        $this->assertTrue(WhatsAppMenuIntent::matches($text), "Failed for: {$text}");
    }

    /** @return array<string, array{0: string}> */
    public static function menuDayRequests(): array
    {
        return [
            'monday' => ['cardápio de segunda'],
            'tomorrow menu' => ['menu de amanhã'],
            'friday' => ['cardapio sexta'],
        ];
    }
}
