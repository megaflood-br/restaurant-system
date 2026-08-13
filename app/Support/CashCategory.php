<?php

namespace App\Support;

class CashCategory
{
    /** @return array<string, string> */
    public static function labelsForType(string $type): array
    {
        return $type === 'saida' ? self::saidaLabels() : self::entradaLabels();
    }

    /** @return array<string, string> */
    public static function entradaLabels(): array
    {
        return [
            'venda_comanda' => 'Venda (comanda)',
            'venda_delivery' => 'Venda (delivery)',
            'venda_retirada' => 'Venda (retirada)',
            'venda' => 'Venda',
            'suprimento' => 'Suprimento de caixa',
            'outros_entrada' => 'Outras entradas',
        ];
    }

    /** @return array<string, string> */
    public static function saidaLabels(): array
    {
        return [
            'sangria' => 'Sangria',
            'despesa' => 'Despesa',
            'compra' => 'Compra',
            'outros_saida' => 'Outras saídas',
        ];
    }

    /** @return array<string, string> */
    public static function allLabels(): array
    {
        return self::entradaLabels() + self::saidaLabels();
    }

    public static function label(?string $category): string
    {
        if ($category === null || $category === '') {
            return '—';
        }

        return self::allLabels()[$category] ?? $category;
    }

    /** @return list<string> */
    public static function keysForType(string $type): array
    {
        return array_keys(self::labelsForType($type));
    }
}
