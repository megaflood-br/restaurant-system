<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Services\ComandaBillService;
use Illuminate\Http\JsonResponse;

class ComandaApiController extends Controller
{
    use FormatsApiResponses;

    public function index(ComandaBillService $comandas): JsonResponse
    {
        return response()->json(['data' => $comandas->overview()]);
    }

    public function show(int $comanda, ComandaBillService $comandas): JsonResponse
    {
        $orders = $comandas->openOrdersForComanda($comanda);

        if ($orders->isEmpty()) {
            return response()->json([
                'comanda' => $comanda,
                'comanda_label' => str_pad((string) $comanda, 3, '0', STR_PAD_LEFT),
                'open' => false,
                'bill' => null,
            ]);
        }

        $bill = $comandas->buildSummary($orders);

        return response()->json([
            'comanda' => $comanda,
            'comanda_label' => str_pad((string) $comanda, 3, '0', STR_PAD_LEFT),
            'open' => true,
            'bill' => [
                'total' => (float) $bill['total'],
                'total_formatted' => number_format((float) $bill['total'], 2, ',', '.'),
                'orders_count' => $bill['orders_count'],
                'elapsed_label' => $bill['elapsed_label'] ?? null,
                'first_order_at' => $bill['first_order_at']?->toIso8601String(),
                'items' => $bill['items'],
                'orders' => $orders->map(fn ($order) => $this->orderPayload($order)),
            ],
        ]);
    }
}
