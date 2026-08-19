<?php

namespace App\Services;

use App\Models\MotoboySettlement;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MotoboyPayoutService
{
    /**
     * @return Collection<int, Order>
     */
    public function ordersForDate(CarbonInterface $date, bool $deliveredOnly = false): Collection
    {
        $query = Order::query()
            ->with('customer')
            ->where('type', 'delivery')
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', $date->toDateString())
            ->orderBy('created_at');

        if ($deliveredOnly) {
            $query->where('status', 'delivered');
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{deliveries_count: int, delivery_fees_total: float, delivered_count: int, pending_count: int}
     */
    public function summarize(Collection $orders): array
    {
        $deliveredCount = $orders->where('status', 'delivered')->count();

        return [
            'deliveries_count' => $orders->count(),
            'delivery_fees_total' => round((float) $orders->sum('delivery_fee'), 2),
            'delivered_count' => $deliveredCount,
            'pending_count' => $orders->count() - $deliveredCount,
        ];
    }

    public function settlementForDate(CarbonInterface $date): ?MotoboySettlement
    {
        return MotoboySettlement::query()
            ->whereDate('settlement_date', $date->toDateString())
            ->first();
    }

    public function saveSettlement(
        CarbonInterface $date,
        float $dailyRate,
        ?string $notes,
        bool $markPaid,
        User $user,
        bool $deliveredOnly = false,
    ): MotoboySettlement {
        $orders = $this->ordersForDate($date, $deliveredOnly);
        $summary = $this->summarize($orders);

        $settlement = MotoboySettlement::query()->firstOrNew([
            'settlement_date' => $date->toDateString(),
        ]);

        $settlement->fill([
            'daily_rate' => round(max(0, $dailyRate), 2),
            'delivery_fees_total' => $summary['delivery_fees_total'],
            'deliveries_count' => $summary['deliveries_count'],
            'notes' => $notes,
            'user_id' => $user->id,
        ]);

        if ($markPaid) {
            $settlement->paid_at = $settlement->paid_at ?? now();
        } else {
            $settlement->paid_at = null;
        }

        $settlement->save();

        return $settlement;
    }
}
