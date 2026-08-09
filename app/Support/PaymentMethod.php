<?php

namespace App\Support;

use Illuminate\Support\Str;

class PaymentMethod
{
    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'cash' => 'Dinheiro',
            'pix' => 'PIX',
            'credit' => 'Cartão de crédito',
            'debit' => 'Cartão de débito',
            'voucher' => 'Vale refeição',
        ];
    }

    public static function label(?string $method): string
    {
        if ($method === null || $method === '') {
            return '—';
        }

        return self::labels()[$method] ?? $method;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    /** Aceita código interno, rótulo PT ou aliases comuns (ex.: debito → debit). */
    public static function normalize(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $raw = trim($input);

        if (in_array($raw, self::keys(), true)) {
            return $raw;
        }

        $slug = self::slug($raw);

        foreach (self::aliases() as $alias => $method) {
            if ($slug === self::slug($alias)) {
                return $method;
            }
        }

        foreach (self::labels() as $key => $label) {
            if ($slug === self::slug($label)) {
                return $key;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function aliases(): array
    {
        return [
            'dinheiro' => 'cash',
            'cash' => 'cash',
            'pix' => 'pix',
            'credito' => 'credit',
            'crédito' => 'credit',
            'cartao credito' => 'credit',
            'cartão crédito' => 'credit',
            'cartao de credito' => 'credit',
            'cartão de crédito' => 'credit',
            'debito' => 'debit',
            'débito' => 'debit',
            'cartao debito' => 'debit',
            'cartão débito' => 'debit',
            'cartao de debito' => 'debit',
            'cartão de débito' => 'debit',
            'vale' => 'voucher',
            'vale refeicao' => 'voucher',
            'vale refeição' => 'voucher',
            'voucher' => 'voucher',
            '1' => 'cash',
            '2' => 'pix',
            '3' => 'credit',
            '4' => 'debit',
            '5' => 'voucher',
        ];
    }

    private static function slug(string $value): string
    {
        return Str::slug(Str::ascii(strtolower(trim($value))), ' ');
    }
}
