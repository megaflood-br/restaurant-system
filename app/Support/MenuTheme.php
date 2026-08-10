<?php

namespace App\Support;

class MenuTheme
{
    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'orange' => 'Laranja',
            'red' => 'Vermelho',
            'rose' => 'Rosa',
            'pink' => 'Pink',
            'amber' => 'Âmbar',
            'yellow' => 'Amarelo',
            'lime' => 'Lima',
            'green' => 'Verde',
            'emerald' => 'Esmeralda',
            'teal' => 'Teal',
            'cyan' => 'Ciano',
            'sky' => 'Azul claro',
            'blue' => 'Azul',
            'indigo' => 'Índigo',
            'violet' => 'Violeta',
            'purple' => 'Roxo',
        ];
    }

    public static function normalize(?string $theme): string
    {
        $theme = strtolower(trim((string) $theme));

        return array_key_exists($theme, self::labels()) ? $theme : 'orange';
    }

    /** @return array{400: string, 500: string, 600: string, 700: string, 100: string, 200: string, 800: string, gradient_to: string} */
    public static function palette(?string $theme = null): array
    {
        $theme = self::normalize($theme);

        return self::palettes()[$theme];
    }

    /** @return array<string, array{400: string, 500: string, 600: string, 700: string, 100: string, 200: string, 800: string, gradient_to: string}> */
    public static function palettes(): array
    {
        return [
            'orange' => self::row('#fb923c', '#f97316', '#ea580c', '#c2410c', '#ffedd5', '#fed7aa', '#9a3412', '#ef4444'),
            'red' => self::row('#f87171', '#ef4444', '#dc2626', '#b91c1c', '#fee2e2', '#fecaca', '#991b1b', '#b91c1c'),
            'rose' => self::row('#fb7185', '#f43f5e', '#e11d48', '#be123c', '#ffe4e6', '#fecdd3', '#9f1239', '#be123c'),
            'pink' => self::row('#f472b6', '#ec4899', '#db2777', '#be185d', '#fce7f3', '#fbcfe8', '#9d174d', '#be185d'),
            'amber' => self::row('#fbbf24', '#f59e0b', '#d97706', '#b45309', '#fef3c7', '#fde68a', '#92400e', '#d97706'),
            'yellow' => self::row('#facc15', '#eab308', '#ca8a04', '#a16207', '#fef9c3', '#fef08a', '#854d0e', '#ca8a04'),
            'lime' => self::row('#a3e635', '#84cc16', '#65a30d', '#4d7c0f', '#ecfccb', '#d9f99d', '#365314', '#65a30d'),
            'green' => self::row('#4ade80', '#22c55e', '#16a34a', '#15803d', '#dcfce7', '#bbf7d0', '#166534', '#15803d'),
            'emerald' => self::row('#34d399', '#10b981', '#059669', '#047857', '#d1fae5', '#a7f3d0', '#065f46', '#047857'),
            'teal' => self::row('#2dd4bf', '#14b8a6', '#0d9488', '#0f766e', '#ccfbf1', '#99f6e4', '#115e59', '#0f766e'),
            'cyan' => self::row('#22d3ee', '#06b6d4', '#0891b2', '#0e7490', '#cffafe', '#a5f3fc', '#155e75', '#0e7490'),
            'sky' => self::row('#38bdf8', '#0ea5e9', '#0284c7', '#0369a1', '#e0f2fe', '#bae6fd', '#075985', '#0369a1'),
            'blue' => self::row('#60a5fa', '#3b82f6', '#2563eb', '#1d4ed8', '#dbeafe', '#bfdbfe', '#1e40af', '#1d4ed8'),
            'indigo' => self::row('#818cf8', '#6366f1', '#4f46e5', '#4338ca', '#e0e7ff', '#c7d2fe', '#3730a3', '#4338ca'),
            'violet' => self::row('#a78bfa', '#8b5cf6', '#7c3aed', '#6d28d9', '#ede9fe', '#ddd6fe', '#5b21b6', '#6d28d9'),
            'purple' => self::row('#c084fc', '#a855f7', '#9333ea', '#7e22ce', '#f3e8ff', '#e9d5ff', '#6b21a8', '#7e22ce'),
        ];
    }

    /** @return array{400: string, 500: string, 600: string, 700: string, 100: string, 200: string, 800: string, gradient_to: string} */
    private static function row(
        string $c400,
        string $c500,
        string $c600,
        string $c700,
        string $c100,
        string $c200,
        string $c800,
        string $gradientTo,
    ): array {
        return [
            '400' => $c400,
            '500' => $c500,
            '600' => $c600,
            '700' => $c700,
            '100' => $c100,
            '200' => $c200,
            '800' => $c800,
            'gradient_to' => $gradientTo,
        ];
    }
}
