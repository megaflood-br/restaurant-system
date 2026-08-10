<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

class OrderSchedule
{
    public static function enabled(): bool
    {
        return (bool) config('whatsapp_agent.scheduling_enabled', true);
    }

    /** @return array{datetime: ?Carbon, error: ?string, label: ?string} */
    public static function resolve(string $text, ?Carbon $reference = null): array
    {
        $reference ??= now();
        $normalized = mb_strtolower(trim($text));

        if ($normalized === '') {
            return ['datetime' => null, 'error' => 'Informe um horário ou digite *agora*.', 'label' => null];
        }

        if (self::isImmediate($normalized)) {
            return ['datetime' => null, 'error' => null, 'label' => 'o mais breve possível'];
        }

        $parsed = self::parseDateTime($normalized, $reference);

        if ($parsed === null) {
            return [
                'datetime' => null,
                'error' => 'Não entendi o horário. Ex.: *agora*, *12:30*, *hoje às 18h* ou *amanhã ao meio-dia*.',
                'label' => null,
            ];
        }

        $validation = self::validateDateTime($parsed, $reference);

        if ($validation !== null) {
            return ['datetime' => null, 'error' => $validation, 'label' => null];
        }

        return [
            'datetime' => $parsed,
            'error' => null,
            'label' => self::formatLabel($parsed, $reference),
        ];
    }

    public static function formatLabel(Carbon $datetime, ?Carbon $reference = null): string
    {
        $reference ??= now();
        $time = $datetime->format('H:i');

        if ($datetime->isSameDay($reference)) {
            return "hoje às {$time}";
        }

        if ($datetime->isSameDay($reference->copy()->addDay())) {
            return "amanhã às {$time}";
        }

        return $datetime->format('d/m/Y')." às {$time}";
    }

    public static function formatForMessage(?Carbon $datetime): string
    {
        if ($datetime === null) {
            return 'o mais breve possível';
        }

        return self::formatLabel($datetime);
    }

    public static function mentionsScheduling(string $text): bool
    {
        $text = mb_strtolower(trim($text));

        return (bool) preg_match(
            '/\b(agendar|agendamento|programar|marcar|para\s+(hoje|amanhã|amanha|depois|mais\s+tarde)|às\s+\d|as\s+\d|\d{1,2}[:h]\d{0,2}|meio[\s-]?dia|daqui\s+\d+\s+(hora|minuto))\b/u',
            $text
        );
    }

    private static function isImmediate(string $text): bool
    {
        return (bool) preg_match(
            '/^(agora|já|ja|imediato|imediata|o quanto antes|assim que possível|assim que possivel|para agora|hoje agora|urgente)$/',
            $text
        ) || Str::contains($text, ['para agora', 'o mais breve']);
    }

    private static function parseDateTime(string $text, Carbon $reference): ?Carbon
    {
        $text = preg_replace('/^(quero\s+)?(agendar|programar|marcar)\s+(para\s+)?/u', '', $text) ?? $text;
        $text = preg_replace('/^(pedido\s+)?(para\s+)?/u', '', $text) ?? $text;
        $text = trim($text);

        $day = $reference->copy();

        if (preg_match('/\bamanh[ãa]\b/u', $text)) {
            $day = $reference->copy()->addDay();
        } elseif (preg_match('/\bhoje\b/u', $text)) {
            $day = $reference->copy();
        }

        if (preg_match('/\bmeio[\s-]?dia\b/u', $text)) {
            return $day->copy()->setTime(12, 0, 0);
        }

        if (preg_match('/\b(daqui\s+a?\s*)?(\d+)\s*(hora|horas|h)\b/u', $text, $matches)) {
            return $reference->copy()->addHours((int) $matches[2]);
        }

        if (preg_match('/\b(daqui\s+a?\s*)?(\d+)\s*(minuto|minutos|min)\b/u', $text, $matches)) {
            return $reference->copy()->addMinutes((int) $matches[2]);
        }

        if (preg_match('/\b(\d{1,2})\s*[:h]\s*(\d{2})\b/u', $text, $matches)) {
            return self::buildTime($day, (int) $matches[1], (int) $matches[2]);
        }

        if (preg_match('/\b(?:às|as|para\s+as|para\s+às)?\s*(\d{1,2})\s*h(?:\s*(\d{2}))?\b/u', $text, $matches)) {
            $hour = (int) $matches[1];
            $minute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;

            return self::buildTime($day, $hour, $minute);
        }

        return null;
    }

    private static function buildTime(Carbon $day, int $hour, int $minute): Carbon
    {
        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        return $day->copy()->setTime($hour, $minute, 0);
    }

    private static function validateDateTime(Carbon $datetime, Carbon $reference): ?string
    {
        $minMinutes = max(15, (int) config('whatsapp_agent.schedule_min_minutes', 30));
        $maxDays = max(0, (int) config('whatsapp_agent.schedule_max_days', 1));

        if ($datetime->lessThan($reference->copy()->addMinutes($minMinutes))) {
            return "Preciso de pelo menos {$minMinutes} minutos de antecedência. Escolha um horário mais tarde ou digite *agora*.";
        }

        if ($datetime->greaterThan($reference->copy()->addDays($maxDays)->endOfDay())) {
            return $maxDays === 0
                ? 'Só aceito agendamento para hoje. Informe um horário de hoje ou digite *agora*.'
                : 'Só aceito agendamento para hoje ou amanhã.';
        }

        return null;
    }
}
