<?php

namespace App\Support;

use Carbon\CarbonInterface;

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
        $now = ($now ?? now())->timezone(config('app.timezone'));
        $current = $now->format('H:i');

        $withinHours = $current >= $opening && $current < $closing;
        $isOpen = $withinHours && ! $forceClosed;

        $nextOpenDay = self::nextOpenDayKey($current, $opening, $closing, $forceClosed, $withinHours);
        $openingLabel = self::formatLabel($opening);
        $closingLabel = self::formatLabel($closing);
        $dayLabel = $nextOpenDay === 'today' ? 'hoje' : 'amanhã';

        if ($isOpen) {
            return [
                'is_open' => true,
                'opening' => $opening,
                'closing' => $closing,
                'opening_label' => $openingLabel,
                'closing_label' => $closingLabel,
                'next_open_day' => $nextOpenDay,
                'next_open_day_label' => $dayLabel,
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
            'label' => 'Fechado',
            'detail' => "Abrimos {$dayLabel} às {$openingLabel}",
            'force_closed' => $forceClosed,
        ];
    }

    public static function isOpenForWhatsApp(?CarbonInterface $now = null): bool
    {
        return self::forWhatsApp($now)['is_open'];
    }

    private static function nextOpenDayKey(
        string $current,
        string $opening,
        string $closing,
        bool $forceClosed,
        bool $withinHours,
    ): string {
        if ($forceClosed) {
            return $withinHours || $current >= $closing ? 'tomorrow' : 'today';
        }

        if ($current < $opening) {
            return 'today';
        }

        return 'tomorrow';
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
