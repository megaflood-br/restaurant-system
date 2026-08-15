<?php

namespace App\Support;

use Illuminate\Support\Str;

class SideOptions
{
    /** @return list<string> */
    public static function all(): array
    {
        return self::normalize(config('whatsapp_agent.side_options'));
    }

    public static function enabled(): bool
    {
        return self::all() !== [];
    }

    /**
     * True if at least one cart product asks for acompanhamento.
     *
     * @param  list<array{product_id?: int}>  $cart
     */
    public static function neededForCart(array $cart): bool
    {
        if (! self::enabled() || $cart === []) {
            return false;
        }

        $ids = collect($cart)->pluck('product_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return false;
        }

        return \App\Models\Product::query()
            ->whereIn('id', $ids)
            ->where('requires_side', true)
            ->exists();
    }

    /** @return list<string> */
    public static function normalize(mixed $options): array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);

            if (is_array($decoded)) {
                $options = $decoded;
            } else {
                $options = preg_split('/\r\n|\r|\n|,/', $options) ?: [];
            }
        }

        if (! is_array($options)) {
            return [];
        }

        $normalized = [];

        foreach ($options as $option) {
            $label = trim((string) $option);

            if ($label !== '') {
                $normalized[] = $label;
            }
        }

        return array_values(array_unique($normalized));
    }

    /** @param  list<string>  $options */
    public static function listForMessage(?array $options = null): string
    {
        $options ??= self::all();
        $lines = [];

        foreach ($options as $index => $option) {
            $lines[] = ($index + 1).'. '.$option;
        }

        return implode("\n", $lines);
    }

    /** @param  list<string>  $options */
    public static function resolve(string $text, ?array $options = null): ?string
    {
        $options ??= self::all();

        if ($options === []) {
            return null;
        }

        $normalized = self::normalizeText($text);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})$/', $normalized, $matches) === 1) {
            $index = (int) $matches[1] - 1;

            return $options[$index] ?? null;
        }

        foreach ($options as $option) {
            $optionKey = self::normalizeText($option);

            if ($optionKey !== '' && ($normalized === $optionKey || str_contains($normalized, $optionKey) || str_contains($optionKey, $normalized))) {
                return $option;
            }
        }

        $aliases = [
            'fritas' => ['frita', 'fritas', 'batata', 'batata frita', 'batatas'],
            'legumes' => ['legume', 'legumes', 'salada', 'vegetais'],
        ];

        foreach ($options as $option) {
            $optionKey = self::normalizeText($option);

            foreach ($aliases as $aliasGroup) {
                $optionMatchesGroup = false;

                foreach ($aliasGroup as $alias) {
                    if (str_contains($optionKey, $alias)) {
                        $optionMatchesGroup = true;
                        break;
                    }
                }

                if (! $optionMatchesGroup) {
                    continue;
                }

                foreach ($aliasGroup as $alias) {
                    if ($normalized === $alias || str_contains($normalized, $alias)) {
                        return $option;
                    }
                }
            }
        }

        return null;
    }

    private static function normalizeText(string $text): string
    {
        $normalized = Str::lower(Str::ascii(trim($text)));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }
}
