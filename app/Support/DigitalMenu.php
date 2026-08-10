<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class DigitalMenu
{
    /** @return array<string, mixed> */
    public static function data(): array
    {
        $data = AppSettings::digitalMenu();
        $status = self::openingStatus($data);

        return array_merge($data, [
            'cover_url' => self::assetUrl($data['cover_image'] ?? null),
            'logo_url' => self::assetUrl($data['logo_image'] ?? null),
            'is_open' => $status['is_open'],
            'status_label' => $status['label'],
            'status_detail' => $status['detail'],
            'theme_color' => $data['theme_color'] ?? config('digital_menu.theme_color', 'orange'),
            'theme_palette' => MenuTheme::palette($data['theme_color'] ?? null),
        ]);
    }

    /** @param  array<string, mixed>  $settings */
    public static function openingStatus(array $settings): array
    {
        $opening = $settings['opening_time'] ?? '09:00';
        $closing = $settings['closing_time'] ?? '22:00';
        $openAt = self::formatTime($opening);

        if ($settings['force_closed'] ?? false) {
            return [
                'is_open' => false,
                'label' => 'Fechado',
                'detail' => "Abrimos às {$openAt}",
            ];
        }

        $now = now()->format('H:i');
        $isOpen = $now >= $opening && $now < $closing;

        if ($isOpen) {
            return [
                'is_open' => true,
                'label' => 'Aberto',
                'detail' => 'até '.self::formatTime($closing),
            ];
        }

        return [
            'is_open' => false,
            'label' => 'Fechado',
            'detail' => "Abrimos às {$openAt}",
        ];
    }

    public static function assetUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public static function publicUrl(string $path = '/'): string
    {
        $domain = config('digital_menu.public_domain');

        if (filled($domain)) {
            $path = '/'.ltrim($path, '/');

            return 'https://'.$domain.($path === '/' ? '' : $path);
        }

        return url('/cardapio'.($path === '/' ? '' : $path));
    }

    public static function usesDedicatedDomain(): bool
    {
        return filled(config('digital_menu.public_domain'));
    }

    private static function formatTime(string $time): string
    {
        $parts = explode(':', $time);

        return sprintf('%02dh%02d', (int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0));
    }
}
