<?php

namespace App\Support;

use Carbon\Carbon;

class ElapsedTime
{
    public static function minutes(?Carbon $since): int
    {
        if (! $since) {
            return 0;
        }

        return (int) $since->diffInMinutes(now());
    }

    public static function label(?Carbon $since): string
    {
        if (! $since) {
            return '—';
        }

        $minutes = self::minutes($since);

        if ($minutes < 1) {
            return 'agora';
        }

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining > 0 ? "{$hours}h {$remaining}min" : "{$hours}h";
    }
}
