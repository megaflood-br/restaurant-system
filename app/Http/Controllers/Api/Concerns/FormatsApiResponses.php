<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Order;
use App\Support\PaymentMethod;
use Illuminate\Http\JsonResponse;

trait FormatsApiResponses
{
    protected function orderPayload(Order $order, bool $detailed = true): array
    {
        $order->loadMissing(['items.product', 'customer', 'user', 'deliveryArea']);

        $payload = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'type' => $order->type,
            'status' => $order->status,
            'comanda_number' => $order->comanda_number,
            'comanda_label' => $order->comanda_number
                ? str_pad((string) $order->comanda_number, 3, '0', STR_PAD_LEFT)
                : null,
            'customer_id' => $order->customer_id,
            'customer_name' => $order->displayCustomerName(),
            'customer_phone' => $order->customer?->phone ?? $order->customer_phone,
            'notes' => $order->notes,
            'scheduled_for' => $order->scheduled_for?->toIso8601String(),
            'scheduled_label' => $order->scheduledLabel(),
            'total' => (float) $order->total,
            'total_formatted' => number_format((float) $order->total, 2, ',', '.'),
            'payment_method' => $order->payment_method,
            'payment_method_label' => PaymentMethod::label($order->payment_method),
            'is_delayed' => $order->isDelayed(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];

        if ($detailed) {
            $payload['items'] = $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->displayName(),
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'notes' => $item->notes,
            ])->values()->all();

            $payload['delivery'] = [
                'area_id' => $order->delivery_area_id,
                'area_name' => $order->deliveryArea?->name,
                'fee' => (float) $order->delivery_fee,
                'address' => $order->delivery_address,
            ];

            $payload['waiter'] = $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
            ] : null;
        }

        return $payload;
    }

    protected function apiError(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
