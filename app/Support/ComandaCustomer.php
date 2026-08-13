<?php

namespace App\Support;

use App\Models\Customer;

class ComandaCustomer
{
    public static function bind(int $comanda, ?Customer $customer): void
    {
        if ($customer === null) {
            static::forget($comanda);

            return;
        }

        session([
            "comanda_customers.{$comanda}" => [
                'id' => $customer->id,
                'name' => $customer->name,
            ],
        ]);

        // Compatível com fluxo antigo (cliente → comanda balcão).
        session([
            'comanda_customer_id' => $customer->id,
            'comanda_customer_name' => $customer->name,
        ]);
    }

    public static function forget(int $comanda): void
    {
        session()->forget("comanda_customers.{$comanda}");
    }

    /** @return array{id: int, name: string}|null */
    public static function get(int $comanda): ?array
    {
        $bound = session("comanda_customers.{$comanda}");

        if (! is_array($bound) || empty($bound['id'])) {
            return null;
        }

        return [
            'id' => (int) $bound['id'],
            'name' => (string) ($bound['name'] ?? ''),
        ];
    }

    public static function id(int $comanda): ?int
    {
        return static::get($comanda)['id'] ?? null;
    }

    public static function name(int $comanda): ?string
    {
        $name = static::get($comanda)['name'] ?? null;

        return filled($name) ? $name : null;
    }
}
