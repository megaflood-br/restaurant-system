<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
    ];

    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting.{$group}.{$key}";

        return Cache::rememberForever($cacheKey, function () use ($group, $key, $default) {
            $setting = static::query()
                ->where('group', $group)
                ->where('key', $key)
                ->first();

            if ($setting === null || $setting->value === null) {
                return $default;
            }

            return static::castValue($setting->value);
        });
    }

    public static function set(string $group, string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => static::encodeValue($value)],
        );

        Cache::forget("setting.{$group}.{$key}");
    }

    /** @param  array<string, mixed>  $values */
    public static function setMany(string $group, array $values): void
    {
        foreach ($values as $key => $value) {
            static::set($group, $key, $value);
        }
    }

    public static function flushCache(): void
    {
        static::query()->get(['group', 'key'])->each(function (Setting $setting) {
            Cache::forget("setting.{$setting->group}.{$setting->key}");
        });
    }

    private static function encodeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private static function castValue(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value === '1' || $value === '0') {
            return $value === '1';
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }
}
