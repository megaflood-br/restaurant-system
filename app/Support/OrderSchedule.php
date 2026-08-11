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
        $reference ??= now()->timezone(config('app.timezone'));
        $normalized = mb_strtolower(trim($text));

        if ($normalized === '') {
            return ['datetime' => null, 'error' => 'Informe um horário ou digite *agora*.', 'label' => null];
        }

        if (self::isImmediate($normalized)) {
            if (! OpeningHours::isOpenForWhatsApp($reference)) {
                $status = OpeningHours::forWhatsApp($reference);

                return [
                    'datetime' => null,
                    'error' => 'No momento estamos *fechados*. Não dá para entregar agora. '
                        ."Abrimos *{$status['next_open_day_label']}* às *{$status['opening_label']}*. "
                        .'Informe um horário nesse período (ex.: *amanhã às 11h*).',
                    'label' => null,
                ];
            }

            return ['datetime' => null, 'error' => null, 'label' => 'o mais breve possível'];
        }

        $parsed = self::parseDateTime($normalized, $reference);

        if ($parsed === null) {
            return [
                'datetime' => null,
                'error' => 'Não entendi o horário. Ex.: *agora*, *12:30*, *hoje às 11h* ou *amanhã ao meio-dia*.',
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
        $reference ??= now()->timezone(config('app.timezone'));
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

    public static function schedulePrompt(): string
    {
        if (OpeningHours::isOpenForWhatsApp()) {
            return (string) config('whatsapp_agent.schedule_message');
        }

        $status = OpeningHours::forWhatsApp();

        return "Estamos *fechados* agora. Pode *agendar* para *{$status['next_open_day_label']}* "
            ."entre *{$status['opening_label']}* e *{$status['closing_label']}*.\n\n"
            .'Ex.: *'.$status['next_open_day_label'].' às 12h*, *12hs* ou *12:30*.';
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
        $explicitDay = false;

        if (preg_match('/\bamanh[ãa]\b/u', $text)) {
            $day = $reference->copy()->addDay();
            $explicitDay = true;
        } elseif (preg_match('/\bhoje\b/u', $text)) {
            $day = $reference->copy();
            $explicitDay = true;
        }

        if (preg_match('/\bmeio[\s-]?dia\b/u', $text)) {
            return self::rollForwardIfPast($day->copy()->setTime(12, 0, 0), $reference, $explicitDay);
        }

        // Relative duration: only "daqui a N hora(s)" or "N horas/minutos" — not bare "11h".
        if (preg_match('/\bdaqui\s+a?\s*(\d+)\s*(hora|horas|h)\b/u', $text, $matches)
            || preg_match('/\b(\d+)\s*(horas)\b/u', $text, $matches)) {
            return $reference->copy()->addHours((int) $matches[1]);
        }

        if (preg_match('/\bdaqui\s+a?\s*(\d+)\s*(minuto|minutos|min)\b/u', $text, $matches)
            || preg_match('/\b(\d+)\s*(minutos|min)\b/u', $text, $matches)) {
            return $reference->copy()->addMinutes((int) $matches[1]);
        }

        if (preg_match('/\b(\d{1,2})\s*[:h]\s*(\d{2})\b/u', $text, $matches)) {
            return self::rollForwardIfPast(
                self::buildTime($day, (int) $matches[1], (int) $matches[2]),
                $reference,
                $explicitDay
            );
        }

        // "11h", "11hs", "às 11h", "as 11hs"
        if (preg_match('/\b(?:às|as|para\s+as|para\s+às)?\s*(\d{1,2})\s*h(?:s|rs)?(?:\s*(\d{2}))?\b/u', $text, $matches)) {
            $hour = (int) $matches[1];
            $minute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;

            return self::rollForwardIfPast(self::buildTime($day, $hour, $minute), $reference, $explicitDay);
        }

        return null;
    }

    private static function rollForwardIfPast(Carbon $parsed, Carbon $reference, bool $explicitDay): Carbon
    {
        if ($explicitDay) {
            return $parsed;
        }

        // Horário já passou hoje → amanhã.
        if ($parsed->lessThanOrEqualTo($reference)) {
            return $parsed->copy()->addDay();
        }

        // Fechados e o próximo expediente é amanhã: "12hs" sem "hoje" = amanhã 12h
        // (mesmo que o relógio ainda não tenha chegado nesse horário hoje).
        if (! OpeningHours::isOpenForWhatsApp($reference) && $parsed->isSameDay($reference)) {
            $status = OpeningHours::forWhatsApp($reference);

            if (($status['next_open_day'] ?? '') === 'tomorrow') {
                return $parsed->copy()->addDay();
            }
        }

        return $parsed;
    }

    private static function buildTime(Carbon $day, int $hour, int $minute): Carbon
    {
        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        return $day->copy()->setTime($hour, $minute, 0);
    }

    /**
     * "Somente hoje" (max_days=0) não pode bloquear o próximo expediente
     * quando já estamos fechados e o bot pediu para agendar amanhã.
     */
    private static function effectiveMaxDays(Carbon $reference): int
    {
        $configured = max(0, (int) config('whatsapp_agent.schedule_max_days', 1));

        if (OpeningHours::isOpenForWhatsApp($reference)) {
            return $configured;
        }

        $status = OpeningHours::forWhatsApp($reference);
        $neededForNextOpen = ($status['next_open_day'] ?? '') === 'tomorrow' ? 1 : 0;

        return max($configured, $neededForNextOpen);
    }

    private static function validateDateTime(Carbon $datetime, Carbon $reference): ?string
    {
        $minMinutes = max(15, (int) config('whatsapp_agent.schedule_min_minutes', 30));
        $maxDays = self::effectiveMaxDays($reference);
        $hours = OpeningHours::forWhatsApp($datetime);

        [$oh, $om] = array_pad(explode(':', $hours['opening']), 2, '0');
        [$ch, $cm] = array_pad(explode(':', $hours['closing']), 2, '0');
        $openAt = $datetime->copy()->setTime((int) $oh, (int) $om, 0);
        $closeAt = $datetime->copy()->setTime((int) $ch, (int) $cm, 0);

        if ($datetime->lessThan($openAt) || $datetime->greaterThanOrEqualTo($closeAt)) {
            $status = OpeningHours::forWhatsApp($reference);
            $hintDay = ($status['next_open_day'] ?? '') === 'tomorrow' ? 'amanhã' : 'hoje';

            return "Nesse dia funcionamos das *{$hours['opening_label']}* às *{$hours['closing_label']}*. "
                ."Escolha um horário nesse intervalo (ex.: *{$hintDay} às {$hours['opening_label']}*).";
        }

        if ($datetime->lessThan($reference->copy()->addMinutes($minMinutes))) {
            $hint = OpeningHours::isOpenForWhatsApp($reference)
                ? ' Escolha um horário mais tarde ou digite *agora*.'
                : ' Escolha um horário no próximo expediente (ex.: *amanhã às 11h*).';

            return "Preciso de pelo menos {$minMinutes} minutos de antecedência.".$hint;
        }

        if ($datetime->greaterThan($reference->copy()->addDays($maxDays)->endOfDay())) {
            return $maxDays <= 0
                ? 'Só aceito agendamento para hoje. Informe um horário de hoje dentro do funcionamento.'
                : ($maxDays === 1
                    ? 'Só aceito agendamento para hoje ou amanhã.'
                    : "Só aceito agendamento até {$maxDays} dias à frente.");
        }

        return null;
    }
}
