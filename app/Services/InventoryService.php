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

        $order->load('items.product.recipe.ingredients', 'items.productVariant.recipe.ingredients');

        DB::transaction(function () use ($order, $userId) {
            foreach ($order->items as $item) {
                $recipe = $item->productVariant?->recipe ?? $item->product?->recipe;

                if (! $recipe) {
                    continue;
                }

                foreach ($recipe->ingredients as $ingredient) {
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

        $order->load('items.product.recipe.ingredients', 'items.productVariant.recipe.ingredients');

        DB::transaction(function () use ($order, $userId) {
            foreach ($order->items as $item) {
                $recipe = $item->productVariant?->recipe ?? $item->product?->recipe;

                if (! $recipe) {
                    continue;
                }

                foreach ($recipe->ingredients as $ingredient) {
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

    /** Reaplica baixa de estoque após editar itens de um pedido já baixado. */
    public function resyncForOrder(Order $order, ?int $userId = null): void
    {
        $fresh = $order->fresh(['items.product.recipe.ingredients', 'items.productVariant.recipe.ingredients']);

        if (! $fresh) {
            return;
        }

        if ($fresh->inventory_deducted_at) {
            $this->restoreForOrder($fresh, $userId);
            $fresh = $fresh->fresh(['items.product.recipe.ingredients', 'items.productVariant.recipe.ingredients']);
        }

        if ($fresh && $fresh->items->isNotEmpty() && $fresh->status !== 'cancelled') {
            $this->deductForOrder($fresh, $userId);
        }
    }

    public function manualMovement(
        Ingredient $ingredient,
        string $type,
        float $quantity,
        ?string $notes,
        ?int $userId = null,
        ?float $costPrice = null,
    ): void {
        if ($type === 'out' && $ingredient->current_stock < $quantity) {
            throw new RuntimeException('Estoque insuficiente para esta saída.');
        }

        if ($type === 'out') {
            $costPrice = null;
        }

        DB::transaction(function () use ($ingredient, $type, $quantity, $notes, $userId, $costPrice) {
            $this->recordMovement(
                $ingredient,
                $type,
                $quantity,
                'manual',
                $notes,
                null,
                $userId,
                $costPrice,
            );

            if ($type === 'in' && $costPrice !== null && $costPrice >= 0) {
                $ingredient->update(['cost_price' => round($costPrice, 2)]);
            }
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
        ?float $costPrice = null,
    ): void {
        InventoryMovement::create([
            'ingredient_id' => $ingredient->id,
            'order_id' => $orderId,
            'type' => $type,
            'reason' => $reason,
            'quantity' => $quantity,
            'cost_price' => $costPrice,
            'notes' => $notes,
            'user_id' => $userId,
        ]);

        $delta = $type === 'in' ? $quantity : -$quantity;
        $ingredient->increment('current_stock', $delta);
    }
}
