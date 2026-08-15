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

    public function test_saturday_after_hours_skips_closed_sunday(): void
    {
        config([
            'app.timezone' => 'America/Sao_Paulo',
            'general.opening_time' => '11:00',
            'general.closing_time' => '15:00',
            'general.open_days' => [
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
            ],
            'digital_menu.force_closed' => false,
        ]);

        // Saturday 2026-08-15 after closing
        Carbon::setTestNow(Carbon::parse('2026-08-15 16:00:00', 'America/Sao_Paulo'));

        $status = OpeningHours::forWhatsApp();

        $this->assertFalse($status['is_open']);
        $this->assertSame('monday', $status['next_open_day']);
        $this->assertSame('segunda-feira', $status['next_open_day_label']);
        $this->assertSame(2, $status['next_open_days_ahead']);
        $this->assertSame('Abrimos segunda-feira às 11h00', $status['detail']);
    }

    public function test_sunday_is_closed_when_not_in_open_days(): void
    {
        config([
            'app.timezone' => 'America/Sao_Paulo',
            'general.opening_time' => '11:00',
            'general.closing_time' => '15:00',
            'general.open_days' => [
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
            ],
            'digital_menu.force_closed' => false,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-16 12:00:00', 'America/Sao_Paulo'));

        $status = OpeningHours::forWhatsApp();

        $this->assertFalse($status['is_open']);
        // Domingo → segunda é "amanhã".
        $this->assertSame('tomorrow', $status['next_open_day']);
        $this->assertSame('amanhã', $status['next_open_day_label']);
        $this->assertSame(1, $status['next_open_days_ahead']);
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
