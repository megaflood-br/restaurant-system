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
                        .'Informe um horário nesse período (ex.: *'.$status['next_open_day_label'].' às 11h*).',
                    'label' => null,
                ];
            }

            return ['datetime' => null, 'error' => null, 'label' => 'o mais breve possível'];
        }

        $parsed = self::parseDateTime($normalized, $reference);

        if ($parsed === null) {
            return [
                'datetime' => null,
                'error' => 'Não entendi o horário. Ex.: *agora*, *12:30*, *hoje às 11h*, *amanhã ao meio-dia* ou *segunda às 12h*.',
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

        $weekday = Str::lower(WeeklyMenuImages::labels()[OpeningHours::dayKey($datetime)] ?? '');

        if ($weekday !== '') {
            return "{$weekday} às {$time}";
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
        $text = preg_replace('/[^\p{L}\p{N}\s:]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? $text;

        return (bool) preg_match(
            '/\b(agendar|agendamento|programar|marcar|'
            .'para\s+(?:as|às)\s+\d{1,2}|para\s+(hoje|amanhã|amanha|segunda|terça|terca|quarta|quinta|sexta|sábado|sabado|domingo|depois|mais\s+tarde)|'
            .'(?:às|as)\s+\d{1,2}|\d{1,2}\s*h(?:s|rs)?|\d{1,2}[:h]\d{0,2}|'
            .'meio[\s-]?dia|daqui\s+\d+\s+(hora|minuto))\b/u',
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
        } elseif (($weekdayOffset = self::parseWeekdayOffset($text, $reference)) !== null) {
            $day = $reference->copy()->startOfDay()->addDays($weekdayOffset);
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

    /** Days from reference startOfDay until the named weekday (1–7). */
    private static function parseWeekdayOffset(string $text, Carbon $reference): ?int
    {
        $map = [
            'segunda' => 1,
            'terca' => 2,
            'terça' => 2,
            'quarta' => 3,
            'quinta' => 4,
            'sexta' => 5,
            'sabado' => 6,
            'sábado' => 6,
            'domingo' => 7,
        ];

        foreach ($map as $name => $iso) {
            if (preg_match('/\b'.preg_quote($name, '/').'(?:-feira)?\b/u', $text) !== 1) {
                continue;
            }

            $currentIso = (int) $reference->dayOfWeekIso;
            $offset = ($iso - $currentIso + 7) % 7;

            return $offset === 0 ? 7 : $offset;
        }

        return null;
    }

    private static function rollForwardIfPast(Carbon $parsed, Carbon $reference, bool $explicitDay): Carbon
    {
        $candidate = $parsed->copy();

        if ($explicitDay) {
            // Keep the chosen calendar day; validateDateTime rejects closed weekdays.
            return $candidate;
        }

        if ($candidate->lessThanOrEqualTo($reference)) {
            $candidate->addDay();
        }

        // Fechados: horário sem "hoje/amanhã" deve ir para o próximo dia de expediente.
        if (! OpeningHours::isOpenForWhatsApp($reference) && $candidate->isSameDay($reference)) {
            $next = OpeningHours::nextOpenDate($reference);
            $candidate->setDate($next->year, $next->month, $next->day);
        }

        // Pula dias em que o restaurante não abre (ex.: domingo).
        for ($i = 0; $i < 8; $i++) {
            if (OpeningHours::isOpenOnDate($candidate)) {
                break;
            }
            $candidate->addDay();
        }

        return $candidate;
    }

    private static function buildTime(Carbon $day, int $hour, int $minute): Carbon
    {
        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        return $day->copy()->setTime($hour, $minute, 0);
    }

    /**
     * "Somente hoje" (max_days=0) não pode bloquear o próximo expediente
     * quando já estamos fechados e o bot pediu para agendar.
     */
    private static function effectiveMaxDays(Carbon $reference): int
    {
        $configured = max(0, (int) config('whatsapp_agent.schedule_max_days', 1));

        if (OpeningHours::isOpenForWhatsApp($reference)) {
            return $configured;
        }

        $neededForNextOpen = OpeningHours::daysUntilNextOpen($reference);

        return max($configured, $neededForNextOpen);
    }

    private static function validateDateTime(Carbon $datetime, Carbon $reference): ?string
    {
        $minMinutes = max(15, (int) config('whatsapp_agent.schedule_min_minutes', 30));
        $maxDays = self::effectiveMaxDays($reference);
        $status = OpeningHours::forWhatsApp($reference);
        $hintDay = $status['next_open_day_label'];

        if (! OpeningHours::isOpenOnDate($datetime)) {
            $labels = WeeklyMenuImages::labels();
            $dayName = Str::lower($labels[OpeningHours::dayKey($datetime)] ?? 'esse dia');

            return "Não abrimos *{$dayName}*. Pode agendar para *{$hintDay}* "
                ."entre *{$status['opening_label']}* e *{$status['closing_label']}* "
                ."(ex.: *{$hintDay} às {$status['opening_label']}*).";
        }

        $hours = OpeningHours::forWhatsApp($datetime);

        [$oh, $om] = array_pad(explode(':', $hours['opening']), 2, '0');
        [$ch, $cm] = array_pad(explode(':', $hours['closing']), 2, '0');
        $openAt = $datetime->copy()->setTime((int) $oh, (int) $om, 0);
        $closeAt = $datetime->copy()->setTime((int) $ch, (int) $cm, 0);

        if ($datetime->lessThan($openAt) || $datetime->greaterThanOrEqualTo($closeAt)) {
            return "Nesse dia funcionamos das *{$hours['opening_label']}* às *{$hours['closing_label']}*. "
                ."Escolha um horário nesse intervalo (ex.: *{$hintDay} às {$hours['opening_label']}*).";
        }

        if ($datetime->lessThan($reference->copy()->addMinutes($minMinutes))) {
            $hint = OpeningHours::isOpenForWhatsApp($reference)
                ? ' Escolha um horário mais tarde ou digite *agora*.'
                : " Escolha um horário no próximo expediente (ex.: *{$hintDay} às 11h*).";

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
