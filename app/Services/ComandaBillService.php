<?php

namespace App\Services;

use App\Models\Order;
use App\Support\ElapsedTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ComandaBillService
{
    public function totalComandas(): int
    {
        return max(1, (int) config('restaurant.total_comandas', 100));
    }

    public function overview(): array
    {
        $totalComandas = $this->totalComandas();
        $ordersByComanda = $this->openOrdersQuery()
            ->withCount('items')
            ->get()
            ->groupBy('comanda_number');

        $active = [];
        $ready = [];

        foreach ($ordersByComanda as $comandaNumber => $orders) {
            $comandaNumber = (int) $comandaNumber;
            $allServed = $orders->every(fn (Order $order) => $order->status === 'served');
            $firstOrderAt = $orders->min('created_at');

            $info = [
                'number' => $comandaNumber,
                'label' => str_pad((string) $comandaNumber, 3, '0', STR_PAD_LEFT),
                'total' => (float) $orders->sum('total'),
                'orders_count' => $orders->count(),
                'items_count' => (int) $orders->sum('items_count'),
                'first_order_at' => $firstOrderAt,
                'elapsed_minutes' => ElapsedTime::minutes($firstOrderAt),
                'elapsed_label' => ElapsedTime::label($firstOrderAt),
                'last_order_at' => $orders->max('created_at'),
                'has_delayed' => $orders->contains(fn (Order $order) => $order->isDelayed()),
            ];

            if ($allServed) {
                $ready[] = $info;
            } else {
                $active[] = $info;
            }
        }

        usort($active, fn ($a, $b) => $a['number'] <=> $b['number']);
        usort($ready, fn ($a, $b) => $a['number'] <=> $b['number']);

        $occupiedNumbers = collect($active)->pluck('number')
            ->merge(collect($ready)->pluck('number'))
            ->unique();

        $free = collect(range(1, $totalComandas))
            ->diff($occupiedNumbers)
            ->values()
            ->map(fn (int $number) => [
                'number' => $number,
                'label' => str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            ])
            ->all();

        return [
            'total_comandas' => $totalComandas,
            'active' => $active,
            'ready' => $ready,
            'free' => $free,
            'counts' => [
                'active' => count($active),
                'ready' => count($ready),
                'free' => count($free),
                'occupied' => count($active) + count($ready),
            ],
        ];
    }

    public function openOrdersForComanda(int $comandaNumber): Collection
    {
        return $this->openOrdersQuery()
            ->where('comanda_number', $comandaNumber)
            ->with(['items.product', 'user'])
            ->oldest()
            ->get();
    }

    public function buildSummary(Collection $orders): array
    {
        $items = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $key = $item->product_id.'|'.($item->notes ?? '');

                if (! isset($items[$key])) {
                    $items[$key] = [
                        'name' => $item->displayName(),
                        'quantity' => 0,
                        'unit_price' => (float) $item->unit_price,
                        'subtotal' => 0.0,
                        'notes' => $item->notes,
                    ];
                }

                $items[$key]['quantity'] += $item->quantity;
                $items[$key]['subtotal'] += (float) $item->subtotal;
            }
        }

        return [
            'comanda_number' => $orders->first()?->comanda_number,
            'orders' => $orders,
            'orders_count' => $orders->count(),
            'items' => array_values($items),
            'total' => (float) $orders->sum('total'),
            'first_order_at' => $orders->min('created_at'),
            'elapsed_label' => ElapsedTime::label($orders->min('created_at')),
        ];
    }

    public function closeComanda(int $comandaNumber, string $paymentMethod): array
    {
        $orders = $this->openOrdersForComanda($comandaNumber);

        if ($orders->isEmpty()) {
            throw new RuntimeException('Nenhum pedido aberto para a comanda '.$comandaNumber.'.');
        }

        $summary = $this->buildSummary($orders);
        $summary['payment_method'] = $paymentMethod;

        DB::transaction(function () use ($orders, $paymentMethod) {
            Order::query()
                ->whereIn('id', $orders->pluck('id'))
                ->update([
                    'status' => 'delivered',
                    'payment_method' => $paymentMethod,
                ]);
        });

        try {
            app(CashFlowService::class)->recordComandaClose($summary, auth()->id());
        } catch (\Throwable) {
            // Fluxo de caixa é best-effort; fechamento da comanda já foi concluído.
        }

        return $summary;
    }

    private function openOrdersQuery()
    {
        return Order::query()
            ->where('type', 'dine_in')
            ->whereNotNull('comanda_number')
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->whereDate('created_at', today());
    }
}
