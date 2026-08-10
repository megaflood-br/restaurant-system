<?php

namespace App\Support;

class MenuTheme
{
    public const DEFAULT = '#f97316';

    /** @return array<string, string> */
    private static function legacyPresets(): array
    {
        return [
            'orange' => '#f97316',
            'red' => '#ef4444',
            'rose' => '#f43f5e',
            'pink' => '#ec4899',
            'amber' => '#f59e0b',
            'yellow' => '#eab308',
            'lime' => '#84cc16',
            'green' => '#22c55e',
            'emerald' => '#10b981',
            'teal' => '#14b8a6',
            'cyan' => '#06b6d4',
            'sky' => '#0ea5e9',
            'blue' => '#3b82f6',
            'indigo' => '#6366f1',
            'violet' => '#8b5cf6',
            'purple' => '#a855f7',
        ];
    }

    public static function normalize(?string $color): string
    {
        $color = strtolower(trim((string) $color));

        if ($color === '') {
            return self::DEFAULT;
        }

        if (isset(self::legacyPresets()[$color])) {
            return self::legacyPresets()[$color];
        }

        if (preg_match('/^#([0-9a-f]{3})$/', $color, $matches)) {
            $hex = $matches[1];

            return '#'.$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^#([0-9a-f]{6})$/', $color)) {
            return $color;
        }

        return self::DEFAULT;
    }

    /** @return array{400: string, 500: string, 600: string, 700: string, 100: string, 200: string, 800: string, gradient_to: string} */
    public static function palette(?string $color = null): array
    {
        return self::generatePalette(self::normalize($color));
    }

    /** @return array{400: string, 500: string, 600: string, 700: string, 100: string, 200: string, 800: string, gradient_to: string} */
    private static function generatePalette(string $hex): array
    {
        [$h, $s, $l] = self::hexToHsl($hex);

        return [
            '400' => self::hslToHex($h, min(100, $s + 5), min(72, $l + 14)),
            '500' => $hex,
            '600' => self::hslToHex($h, $s, max(18, $l - 10)),
            '700' => self::hslToHex($h, $s, max(12, $l - 18)),
            '100' => self::hslToHex($h, max(25, $s - 35), min(96, $l + 38)),
            '200' => self::hslToHex($h, max(35, $s - 25), min(90, $l + 24)),
            '800' => self::hslToHex($h, $s, max(8, $l - 30)),
            'gradient_to' => self::hslToHex(fmod($h + 18, 360), $s, max(14, $l - 14)),
        ];
    }

    /** @return array{0: float, 1: float, 2: float} */
    private static function hexToHsl(string $hex): array
    {
        $r = hexdec(substr($hex, 1, 2)) / 255;
        $g = hexdec(substr($hex, 3, 2)) / 255;
        $b = hexdec(substr($hex, 5, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, round($l * 100, 2)];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match (true) {
            $max === $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $max === $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [round($h * 60, 2), round($s * 100, 2), round($l * 100, 2)];
    }

    private static function hslToHex(float $h, float $s, float $l): string
    {
        $h = fmod($h, 360);
        if ($h < 0) {
            $h += 360;
        }

        $s = max(0, min(100, $s)) / 100;
        $l = max(0, min(100, $l)) / 100;

        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $hk = $h / 360;

            $r = self::hueToRgb($p, $q, $hk + (1 / 3));
            $g = self::hueToRgb($p, $q, $hk);
            $b = self::hueToRgb($p, $q, $hk - (1 / 3));
        }

        return sprintf('#%02x%02x%02x', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }

    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }

        if ($t < (1 / 6)) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 0.5) {
            return $q;
        }
        if ($t < (2 / 3)) {
            return $p + ($q - $p) * ((2 / 3) - $t) * 6;
        }

        return $p;
    }
}
