<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class WhatsAppBotPause
{
    public static function pause(string $phone, string $reason = 'human'): void
    {
        $key = self::normalizedPhone($phone);

        if ($key === null) {
            return;
        }

        $minutes = max(5, (int) config('whatsapp_agent.human_pause_minutes', 60));

        Cache::put(self::pauseKey($key), [
            'reason' => $reason,
            'paused_at' => now()->toIso8601String(),
        ], now()->addMinutes($minutes));

        Cache::forget(self::historyKey($key));
    }

    public static function resume(string $phone): void
    {
        $key = self::normalizedPhone($phone);

        if ($key === null) {
            return;
        }

        Cache::forget(self::pauseKey($key));
    }

    public static function isPaused(string $phone): bool
    {
        $key = self::normalizedPhone($phone);

        if ($key === null) {
            return false;
        }

        return Cache::has(self::pauseKey($key));
    }

    /** @return array{reason: string, paused_at: string}|null */
    public static function status(string $phone): ?array
    {
        $key = self::normalizedPhone($phone);

        if ($key === null) {
            return null;
        }

        $status = Cache::get(self::pauseKey($key));

        return is_array($status) ? $status : null;
    }

    public static function markBotOutbound(string $phone, ?string $messageId): void
    {
        $key = self::normalizedPhone($phone);

        if ($key === null || ! filled($messageId)) {
            return;
        }

        $cacheKey = self::botMessagesKey($key);
        $ids = Cache::get($cacheKey, []);

        if (! is_array($ids)) {
            $ids = [];
        }

        $ids[] = $messageId;
        $ids = array_values(array_unique(array_slice($ids, -30)));

        Cache::put($cacheKey, $ids, now()->addMinutes(10));
    }

    public static function wasSentByBot(string $phone, ?string $messageId): bool
    {
        $key = self::normalizedPhone($phone);

        if ($key === null || ! filled($messageId)) {
            return false;
        }

        $ids = Cache::get(self::botMessagesKey($key), []);

        return is_array($ids) && in_array($messageId, $ids, true);
    }

    public static function forgetAiHistory(string $phone): void
    {
        $key = self::normalizedPhone($phone);

        if ($key === null) {
            return;
        }

        Cache::forget(self::historyKey($key));
    }

    private static function normalizedPhone(string $phone): ?string
    {
        return PhoneNumber::normalize($phone) ?? ($phone !== '' ? $phone : null);
    }

    private static function pauseKey(string $phone): string
    {
        return 'whatsapp_bot_paused:'.$phone;
    }

    private static function botMessagesKey(string $phone): string
    {
        return 'whatsapp_bot_sent_ids:'.$phone;
    }

    private static function historyKey(string $phone): string
    {
        return 'whatsapp_ai_history:'.$phone;
    }
}
