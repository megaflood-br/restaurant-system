<?php

namespace App\Support;

use App\Support\DigitalMenu;

class WeeklyMenuImages
{
    /** @var list<string> */
    public const DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'monday' => 'Segunda-feira',
            'tuesday' => 'Terça-feira',
            'wednesday' => 'Quarta-feira',
            'thursday' => 'Quinta-feira',
            'friday' => 'Sexta-feira',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo',
        ];
    }

    public static function todayKey(): string
    {
        return match ((int) now()->dayOfWeekIso) {
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            default => 'sunday',
        };
    }

    /** @return array<string, string|null> */
    public static function empty(): array
    {
        return array_fill_keys(self::DAYS, null);
    }

    /** @param  mixed  $images */
    public static function normalize(mixed $images): array
    {
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($images)) {
            $images = [];
        }

        $normalized = self::empty();

        foreach (self::DAYS as $day) {
            $path = $images[$day] ?? null;
            $normalized[$day] = filled($path) ? (string) $path : null;
        }

        return $normalized;
    }

    /** @param  array<string, string|null>|null  $images */
    public static function pathForDay(?string $day = null, ?array $images = null): ?string
    {
        $day = $day ?? self::todayKey();
        $images = self::normalize($images ?? config('whatsapp_agent.menu_images'));

        if (filled($images[$day] ?? null)) {
            return $images[$day];
        }

        $legacy = config('whatsapp_agent.menu_image');

        return filled($legacy) ? (string) $legacy : null;
    }

    public static function urlForDay(?string $day = null, ?array $images = null): ?string
    {
        return DigitalMenu::assetUrl(self::pathForDay($day, $images));
    }

    public static function urlForToday(): ?string
    {
        return self::urlForDay();
    }

    /** @param  array<string, string|null>|null  $images */
    /** @return array<string, string|null> */
    public static function urls(?array $images = null): array
    {
        $images = self::normalize($images ?? config('whatsapp_agent.menu_images'));
        $urls = [];

        foreach (self::DAYS as $day) {
            $urls[$day] = DigitalMenu::assetUrl($images[$day]);
        }

        return $urls;
    }

    /** @return array<string, string|null> */
    public static function fromLegacy(?string $legacyPath): array
    {
        $images = self::empty();

        if (! filled($legacyPath)) {
            return $images;
        }

        foreach (self::DAYS as $day) {
            $images[$day] = $legacyPath;
        }

        return $images;
    }
}
