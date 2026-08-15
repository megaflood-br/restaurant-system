<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class OpeningHours
{
    /**
     * Hours used by WhatsApp (general settings, with digital_menu fallback).
     *
     * @return array{
     *     is_open: bool,
     *     opening: string,
     *     closing: string,
     *     opening_label: string,
     *     closing_label: string,
     *     next_open_day: string,
     *     next_open_day_label: string,
     *     next_open_days_ahead: int,
     *     label: string,
     *     detail: string,
     *     force_closed: bool
     * }
     */
    public static function forWhatsApp(?CarbonInterface $now = null): array
    {
        return self::status([
            'opening_time' => (string) (config('general.opening_time') ?: config('digital_menu.opening_time', '09:00')),
            'closing_time' => (string) (config('general.closing_time') ?: config('digital_menu.closing_time', '22:00')),
            'force_closed' => (bool) config('digital_menu.force_closed', false),
            'open_days' => config('general.open_days'),
        ], $now);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{
     *     is_open: bool,
     *     opening: string,
     *     closing: string,
     *     opening_label: string,
     *     closing_label: string,
     *     next_open_day: string,
     *     next_open_day_label: string,
     *     next_open_days_ahead: int,
     *     label: string,
     *     detail: string,
     *     force_closed: bool
     * }
     */
    public static function status(array $settings, ?CarbonInterface $now = null): array
    {
        $opening = self::normalizeTime((string) ($settings['opening_time'] ?? '09:00'));
        $closing = self::normalizeTime((string) ($settings['closing_time'] ?? '22:00'));
        $forceClosed = (bool) ($settings['force_closed'] ?? false);
        $openDays = self::normalizeOpenDays($settings['open_days'] ?? null);
        $now = ($now ?? now())->timezone(config('app.timezone'));
        $current = $now->format('H:i');

        $openWeekday = self::isOpenWeekday($now, $openDays);
        $withinHours = $openWeekday && $current >= $opening && $current < $closing;
        $isOpen = $withinHours && ! $forceClosed;

        $next = self::resolveNextOpenDay($now, $opening, $closing, $forceClosed, $openDays);
        $daysAhead = (int) $now->copy()->startOfDay()->diffInDays($next->copy()->startOfDay());
        $weekdayKey = self::dayKey($next);
        $nextOpenDay = match (true) {
            $daysAhead === 0 => 'today',
            $daysAhead === 1 => 'tomorrow',
            default => $weekdayKey,
        };
        $dayLabel = self::dayLabel($nextOpenDay, $weekdayKey);
        $openingLabel = self::formatLabel($opening);
        $closingLabel = self::formatLabel($closing);

        if ($isOpen) {
            return [
                'is_open' => true,
                'opening' => $opening,
                'closing' => $closing,
                'opening_label' => $openingLabel,
                'closing_label' => $closingLabel,
                'next_open_day' => $nextOpenDay,
                'next_open_day_label' => $dayLabel,
                'next_open_days_ahead' => $daysAhead,
                'label' => 'Aberto',
                'detail' => 'até '.$closingLabel,
                'force_closed' => $forceClosed,
            ];
        }

        return [
            'is_open' => false,
            'opening' => $opening,
            'closing' => $closing,
            'opening_label' => $openingLabel,
            'closing_label' => $closingLabel,
            'next_open_day' => $nextOpenDay,
            'next_open_day_label' => $dayLabel,
            'next_open_days_ahead' => $daysAhead,
            'label' => 'Fechado',
            'detail' => "Abrimos {$dayLabel} às {$openingLabel}",
            'force_closed' => $forceClosed,
        ];
    }

    public static function isOpenForWhatsApp(?CarbonInterface $now = null): bool
    {
        return self::forWhatsApp($now)['is_open'];
    }

    public static function isOpenOnDate(CarbonInterface $day, ?array $openDays = null): bool
    {
        $openDays ??= self::normalizeOpenDays(config('general.open_days'));

        return self::isOpenWeekday($day, $openDays);
    }

    /** Absolute date (start of day) of the next open service day. */
    public static function nextOpenDate(?CarbonInterface $now = null): Carbon
    {
        $status = self::forWhatsApp($now);
        $now = ($now ?? now())->timezone(config('app.timezone'));

        return $now->copy()->startOfDay()->addDays((int) ($status['next_open_days_ahead'] ?? 0));
    }

    public static function daysUntilNextOpen(?CarbonInterface $now = null): int
    {
        return (int) (self::forWhatsApp($now)['next_open_days_ahead'] ?? 0);
    }

    /**
     * @param  mixed  $days
     * @return list<string>
     */
    public static function normalizeOpenDays(mixed $days): array
    {
        if (is_string($days)) {
            $decoded = json_decode($days, true);
            if (is_array($decoded)) {
                $days = $decoded;
            } else {
                $days = preg_split('/\s*,\s*/', $days) ?: [];
            }
        }

        if (! is_array($days) || $days === []) {
            // Default: closed on Sundays (typical lunch service).
            $days = [
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
            ];
        }

        $allowed = WeeklyMenuImages::DAYS;
        $normalized = [];

        foreach ($days as $day) {
            $key = Str::lower(trim((string) $day));
            if (in_array($key, $allowed, true)) {
                $normalized[] = $key;
            }
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized !== [] ? $normalized : $allowed;
    }

    /**
     * @param  list<string>  $openDays
     */
    private static function resolveNextOpenDay(
        CarbonInterface $now,
        string $opening,
        string $closing,
        bool $forceClosed,
        array $openDays,
    ): Carbon {
        $current = $now->format('H:i');

        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $now->copy()->startOfDay()->addDays($offset);

            if (! self::isOpenWeekday($day, $openDays)) {
                continue;
            }

            if ($offset === 0) {
                if ($forceClosed) {
                    continue;
                }

                // Still before opening today → next service is today.
                if ($current < $opening) {
                    return $day;
                }

                // Already past closing (or still "open" window but we're computing next) → skip today when closed.
                if ($current >= $closing) {
                    continue;
                }

                // Within hours: "next open day" for messaging when already open → today.
                return $day;
            }

            return $day;
        }

        return $now->copy()->startOfDay()->addDay();
    }

    /** @param  list<string>  $openDays */
    private static function isOpenWeekday(CarbonInterface $day, array $openDays): bool
    {
        return in_array(self::dayKey($day), $openDays, true);
    }

    public static function dayKey(CarbonInterface $day): string
    {
        return match ((int) $day->dayOfWeekIso) {
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            default => 'sunday',
        };
    }

    private static function dayLabel(string $nextOpenDay, string $weekdayKey): string
    {
        return match ($nextOpenDay) {
            'today' => 'hoje',
            'tomorrow' => 'amanhã',
            default => Str::lower(WeeklyMenuImages::labels()[$weekdayKey] ?? $weekdayKey),
        };
    }

    private static function normalizeTime(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return '09:00';
    }

    public static function formatLabel(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return sprintf('%02dh%02d', (int) $matches[1], (int) $matches[2]);
        }

        return $time;
    }
}
