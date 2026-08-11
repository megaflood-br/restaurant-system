<?php

namespace Tests\Unit;

use App\Support\OrderSchedule;
use Carbon\Carbon;
use Tests\TestCase;

class OrderScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'America/Sao_Paulo',
            'general.opening_time' => '11:00',
            'general.closing_time' => '15:00',
            'digital_menu.force_closed' => false,
            'whatsapp_agent.scheduling_enabled' => true,
            'whatsapp_agent.schedule_min_minutes' => 15,
            'whatsapp_agent.schedule_max_days' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_as_11hs_at_night_schedules_tomorrow_morning(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 21:45:00', 'America/Sao_Paulo'));

        $resolved = OrderSchedule::resolve('as 11hs');

        $this->assertNull($resolved['error']);
        $this->assertNotNull($resolved['datetime']);
        $this->assertTrue($resolved['datetime']->isSameDay(Carbon::parse('2026-08-11', 'America/Sao_Paulo')));
        $this->assertSame('11:00', $resolved['datetime']->format('H:i'));
        $this->assertStringContainsString('amanhã', (string) $resolved['label']);
    }

    public function test_agora_while_closed_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 21:45:00', 'America/Sao_Paulo'));

        $resolved = OrderSchedule::resolve('agora');

        $this->assertNull($resolved['datetime']);
        $this->assertNotNull($resolved['error']);
        $this->assertStringContainsStringIgnoringCase('fechados', (string) $resolved['error']);
        $this->assertStringNotContainsStringIgnoringCase('mais breve', (string) $resolved['error']);
    }

    public function test_agora_while_open_is_accepted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $resolved = OrderSchedule::resolve('agora');

        $this->assertNull($resolved['error']);
        $this->assertNull($resolved['datetime']);
        $this->assertSame('o mais breve possível', $resolved['label']);
    }

    public function test_23hs_outside_opening_hours_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 21:45:00', 'America/Sao_Paulo'));

        $resolved = OrderSchedule::resolve('23hs');

        $this->assertNull($resolved['datetime']);
        $this->assertNotNull($resolved['error']);
        $this->assertStringContainsString('11h00', (string) $resolved['error']);
        $this->assertStringContainsString('15h00', (string) $resolved['error']);
    }

    public function test_schedule_prompt_while_closed_does_not_offer_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 21:45:00', 'America/Sao_Paulo'));

        $prompt = OrderSchedule::schedulePrompt();

        $this->assertStringContainsStringIgnoringCase('fechados', $prompt);
        $this->assertStringNotContainsString('*agora*', $prompt);
    }

    public function test_bare_11h_is_clock_time_not_relative_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $resolved = OrderSchedule::resolve('11h');

        // 11h already passed at noon → tomorrow 11:00
        $this->assertNull($resolved['error']);
        $this->assertSame('11:00', $resolved['datetime']?->format('H:i'));
        $this->assertTrue($resolved['datetime']->isSameDay(Carbon::parse('2026-08-11', 'America/Sao_Paulo')));
    }
}
