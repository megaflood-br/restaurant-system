<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class CartService
{
    public function __construct(
        protected string $sessionKey = 'public_cart',
    ) {}

    public function all(): array
    {
        $defaults = [
            'items' => [],
            'comanda_number' => null,
            'type' => 'dine_in',
        ];

        $cart = session($this->sessionKey);

        if (! is_array($cart)) {
            return $defaults;
        }

        // Sessões antigas usavam table_number (antes da migração para comanda)
        if (! array_key_exists('comanda_number', $cart) && array_key_exists('table_number', $cart)) {
            $cart['comanda_number'] = $cart['table_number'];
            unset($cart['table_number']);
            session([$this->sessionKey => $cart]);
        }

        return array_merge($defaults, $cart);
    }

    public function items(): Collection
    {
        $cart = $this->all();
        $productIds = collect($cart['items'])->pluck('product_id');

        if ($productIds->isEmpty()) {
            return collect();
        }

        $products = Product::with(['category', 'recipe'])
            ->whereIn('id', $productIds)
            ->where('is_available', true)
            ->get()
            ->keyBy('id');

        return collect($cart['items'])->map(function ($item) use ($products) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                return null;
            }

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image_url' => $product->image_url,
                'quantity' => (int) $item['quantity'],
                'notes' => $item['notes'] ?? null,
                'subtotal' => (float) $product->price * (int) $item['quantity'],
            ];
        })->filter()->values();
    }

    public function count(): int
    {
        return $this->items()->sum('quantity');
    }

    public function total(): float
    {
        return $this->items()->sum('subtotal');
    }

    public function isEmpty(): bool
    {
        return $this->items()->isEmpty();
    }

    public function setContext(?int $comandaNumber = null, string $type = 'dine_in'): void
    {
        $cart = $this->all();
        $cart['comanda_number'] = $comandaNumber;
        $cart['type'] = $type;
        session([$this->sessionKey => $cart]);
    }

    public function setComandaNumber(?int $comandaNumber): void
    {
        $cart = $this->all();
        $cart['comanda_number'] = $comandaNumber;
        session([$this->sessionKey => $cart]);
    }

    public function add(int $productId, int $quantity = 1, ?string $notes = null): void
    {
        $cart = $this->all();
        $items = $cart['items'];

        $found = false;

        foreach ($items as &$item) {
            if ($item['product_id'] === $productId && ($item['notes'] ?? null) === $notes) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        unset($item);

        if (! $found) {
            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'notes' => $notes,
            ];
        }

        $cart['items'] = $items;
        session([$this->sessionKey => $cart]);
    }

    public function update(int $productId, int $quantity, ?string $notes = null): void
    {
        $cart = $this->all();
        $items = [];

        foreach ($cart['items'] as $item) {
            if ($item['product_id'] === $productId && ($item['notes'] ?? null) === $notes) {
                if ($quantity > 0) {
                    $item['quantity'] = $quantity;
                    $items[] = $item;
                }
            } else {
                $items[] = $item;
            }
        }

        $cart['items'] = $items;
        session([$this->sessionKey => $cart]);
    }

    public function remove(int $productId, ?string $notes = null): void
    {
        $this->update($productId, 0, $notes);
    }

    public function clearItems(): void
    {
        $cart = $this->all();
        $cart['items'] = [];
        session([$this->sessionKey => $cart]);
    }

    public function clear(): void
    {
        session()->forget($this->sessionKey);
    }
}
