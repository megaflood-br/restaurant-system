<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    public function deductForOrder(Order $order, ?int $userId = null): void
    {
        if ($order->inventory_deducted_at) {
            return;
        }

        $order->load('items.product.recipe.ingredients');

        DB::transaction(function () use ($order, $userId) {
            foreach ($order->items as $item) {
                if (! $item->product?->recipe) {
                    continue;
                }

                foreach ($item->product->recipe->ingredients as $ingredient) {
                    $quantity = (float) $ingredient->pivot->quantity * (int) $item->quantity;

                    if ($quantity <= 0) {
                        continue;
                    }

                    $this->recordMovement(
                        $ingredient,
                        'out',
                        $quantity,
                        'sale',
                        "Venda — pedido {$order->order_number} ({$item->quantity}x {$item->displayName()})",
                        $order->id,
                        $userId,
                    );
                }
            }

            $order->update(['inventory_deducted_at' => now()]);
        });
    }

    public function restoreForOrder(Order $order, ?int $userId = null): void
    {
        if (! $order->inventory_deducted_at) {
            return;
        }

        $order->load('items.product.recipe.ingredients');

        DB::transaction(function () use ($order, $userId) {
            foreach ($order->items as $item) {
                if (! $item->product?->recipe) {
                    continue;
                }

                foreach ($item->product->recipe->ingredients as $ingredient) {
                    $quantity = (float) $ingredient->pivot->quantity * (int) $item->quantity;

                    if ($quantity <= 0) {
                        continue;
                    }

                    $this->recordMovement(
                        $ingredient,
                        'in',
                        $quantity,
                        'sale_cancel',
                        "Estorno — pedido {$order->order_number} cancelado",
                        $order->id,
                        $userId,
                    );
                }
            }

            $order->update(['inventory_deducted_at' => null]);
        });
    }

    public function manualMovement(
        Ingredient $ingredient,
        string $type,
        float $quantity,
        ?string $notes,
        ?int $userId = null,
    ): void {
        if ($type === 'out' && $ingredient->current_stock < $quantity) {
            throw new RuntimeException('Estoque insuficiente para esta saída.');
        }

        DB::transaction(function () use ($ingredient, $type, $quantity, $notes, $userId) {
            $this->recordMovement(
                $ingredient,
                $type,
                $quantity,
                'manual',
                $notes,
                null,
                $userId,
            );
        });
    }

    private function recordMovement(
        Ingredient $ingredient,
        string $type,
        float $quantity,
        string $reason,
        ?string $notes,
        ?int $orderId,
        ?int $userId,
    ): void {
        InventoryMovement::create([
            'ingredient_id' => $ingredient->id,
            'order_id' => $orderId,
            'type' => $type,
            'reason' => $reason,
            'quantity' => $quantity,
            'notes' => $notes,
            'user_id' => $userId,
        ]);

        $delta = $type === 'in' ? $quantity : -$quantity;
        $ingredient->increment('current_stock', $delta);
    }
}
