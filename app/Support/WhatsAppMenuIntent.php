<?php

namespace App\Support;

use Illuminate\Support\Str;

class WhatsAppMenuIntent
{
    public static function matches(string $text): bool
    {
        $normalized = self::normalize($text);

        if ($normalized === '') {
            return false;
        }

        $exact = [
            'cardapio',
            'menu',
            'cardapio de hoje',
            'menu de hoje',
            'cardapio do dia',
            'menu do dia',
            'cardapio hoje',
            'ver cardapio',
            'ver o cardapio',
            'manda o cardapio',
            'mande o cardapio',
            'enviar cardapio',
            'envia o cardapio',
        ];

        if (in_array($normalized, $exact, true)) {
            return true;
        }

        if (preg_match('/\bcardapios?\b/u', $normalized) === 1) {
            return true;
        }

        return preg_match('/\bmenu\b.*\b(hoje|dia|segunda|terca|quarta|quinta|sexta|sabado|domingo|amanha)\b|\b(hoje|dia|segunda|terca|quarta|quinta|sexta|sabado|domingo|amanha)\b.*\bmenu\b/u', $normalized) === 1;
    }

    /**
     * Weekday key when the client asked for a specific day's menu; null = no day named.
     */
    public static function requestedDay(string $text): ?string
    {
        if (! self::matches($text)) {
            return null;
        }

        return WeeklyMenuImages::dayKeyFromText($text);
    }

    public static function normalize(string $text): string
    {
        $normalized = Str::lower(Str::ascii(trim($text)));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }
}
