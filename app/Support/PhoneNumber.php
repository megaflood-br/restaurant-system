<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(?string $phone, ?string $countryCode = null): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $phone = trim($phone);

        if (str_contains($phone, '@')) {
            $phone = explode('@', $phone)[0];
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        $countryCode = $countryCode ?? config('integration.default_country_code', config('evolution.default_country_code', '55'));

        if (str_starts_with($digits, $countryCode)) {
            return $digits;
        }

        if (strlen($digits) >= 10 && strlen($digits) <= 11) {
            return $countryCode.$digits;
        }

        return $digits;
    }

    public static function isValid(?string $phone): bool
    {
        if ($phone === null || trim($phone) === '') {
            return false;
        }

        if (self::looksLikePlaceholder($phone)) {
            return false;
        }

        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return false;
        }

        return strlen($normalized) >= 12 && strlen($normalized) <= 13;
    }

    public static function looksLikePlaceholder(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $raw = trim($value);
        $lower = strtolower($raw);

        if (! preg_match('/\d/', $raw)) {
            return true;
        }

        $needles = [
            'client_phone_number',
            'customer_phone_number',
            'phone_number',
            '{{',
            '}}',
            '$json',
            '$input',
            '$node',
            'undefined',
            'null',
        ];

        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function formatForStorage(?string $phone): ?string
    {
        if (! self::isValid($phone)) {
            return null;
        }

        return self::formatDisplay($phone) ?? trim((string) $phone);
    }

    public static function formatDisplay(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        $countryCode = config('integration.default_country_code', config('evolution.default_country_code', '55'));

        if (str_starts_with($normalized, $countryCode)) {
            $local = substr($normalized, strlen($countryCode));

            if (strlen($local) === 11) {
                return sprintf('(%s) %s-%s', substr($local, 0, 2), substr($local, 2, 5), substr($local, 7));
            }

            if (strlen($local) === 10) {
                return sprintf('(%s) %s-%s', substr($local, 0, 2), substr($local, 2, 4), substr($local, 6));
            }
        }

        return $phone;
    }
}
