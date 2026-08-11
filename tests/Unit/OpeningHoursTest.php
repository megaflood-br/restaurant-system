<?php

namespace Tests\Unit;

use App\Support\OpeningHours;
use Carbon\Carbon;
use Tests\TestCase;

class OpeningHoursTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_open_during_business_hours(): void
    {
        config([
            'app.timezone' => 'America/Sao_Paulo',
            'general.opening_time' => '11:00',
            'general.closing_time' => '15:00',
            'digital_menu.force_closed' => false,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-10 12:30:00', 'America/Sao_Paulo'));

        $status = OpeningHours::forWhatsApp();

        $this->assertTrue($status['is_open']);
        $this->assertSame('Aberto', $status['label']);
        $this->assertSame('até 15h00', $status['detail']);
    }

    public function test_closed_after_hours_points_to_tomorrow(): void
    {
        config([
            'app.timezone' => 'America/Sao_Paulo',
            'general.opening_time' => '11:00',
            'general.closing_time' => '15:00',
            'digital_menu.force_closed' => false,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-10 21:45:00', 'America/Sao_Paulo'));

        $status = OpeningHours::forWhatsApp();

        $this->assertFalse($status['is_open']);
        $this->assertSame('tomorrow', $status['next_open_day']);
        $this->assertSame('amanhã', $status['next_open_day_label']);
        $this->assertSame('Abrimos amanhã às 11h00', $status['detail']);
    }

    public function test_closed_before_opening_points_to_today(): void
    {
        config([
            'app.timezone' => 'America/Sao_Paulo',
            'general.opening_time' => '11:00',
            'general.closing_time' => '15:00',
            'digital_menu.force_closed' => false,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-10 09:00:00', 'America/Sao_Paulo'));

        $status = OpeningHours::forWhatsApp();

        $this->assertFalse($status['is_open']);
        $this->assertSame('today', $status['next_open_day']);
        $this->assertSame('hoje', $status['next_open_day_label']);
        $this->assertSame('Abrimos hoje às 11h00', $status['detail']);
    }

    public function test_force_closed_during_hours_points_to_tomorrow(): void
    {
        config([
            'app.timezone' => 'America/Sao_Paulo',
            'general.opening_time' => '11:00',
            'general.closing_time' => '15:00',
            'digital_menu.force_closed' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/Sao_Paulo'));

        $status = OpeningHours::forWhatsApp();

        $this->assertFalse($status['is_open']);
        $this->assertSame('tomorrow', $status['next_open_day']);
    }
}
