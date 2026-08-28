<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SalesReportService
{
    /** @var array<string, string> */
    public const ORDER_TYPE_LABELS = [
        'dine_in' => 'Salão',
        'delivery' => 'Delivery',
        'takeaway' => 'Retirada',
    ];

    /**
     * @return array{
     *     from: Carbon,
     *     to: Carbon,
     *     preset: string,
     *     from_date: string,
     *     to_date: string,
     * }
     */
    public function resolveRange(Request $request): array
    {
        $tz = config('app.timezone');
        $preset = $request->string('preset')->toString();

        if ($preset === '' && $request->filled('from')) {
            $preset = 'custom';
        }

        if ($preset === '') {
            $preset = 'today';
        }

        [$from, $to] = match ($preset) {
            'yesterday' => [
                now($tz)->subDay()->startOfDay(),
                now($tz)->subDay()->endOfDay(),
            ],
            'week' => [
                now($tz)->startOfWeek(),
                now($tz)->endOfDay(),
            ],
            'month' => [
                now($tz)->startOfMonth(),
                now($tz)->endOfDay(),
            ],
            'custom' => [
                Carbon::parse($request->input('from', today()->toDateString()), $tz)->startOfDay(),
                Carbon::parse($request->input('to', $request->input('from', today()->toDateString())), $tz)->endOfDay(),
            ],
            default => [
                now($tz)->startOfDay(),
                now($tz)->endOfDay(),
            ],
        };

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
        ];
    }

    /**
     * @return array{
     *     orders_count: int,
     *     dishes_count: int,
     *     revenue: float,
     *     items_revenue: float,
     *     delivery_fees: float,
     *     discounts: float,
     *     cancelled_count: int,
     *     average_ticket: float,
     * }
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $orders = $this->ordersInRange($from, $to);
        $ordersCount = (clone $orders)->count();
        $revenue = (float) (clone $orders)->sum('total');
        $deliveryFees = (float) (clone $orders)->sum('delivery_fee');
        $discounts = (float) (clone $orders)->sum('discount');

        $dishesCount = (int) $this->itemsInRange($from, $to)->sum('order_items.quantity');
        $itemsRevenue = (float) $this->itemsInRange($from, $to)->sum('order_items.subtotal');

        $cancelledCount = Order::query()
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return [
            'orders_count' => $ordersCount,
            'dishes_count' => $dishesCount,
            'revenue' => round($revenue, 2),
            'items_revenue' => round($itemsRevenue, 2),
            'delivery_fees' => round($deliveryFees, 2),
            'discounts' => round($discounts, 2),
            'cancelled_count' => $cancelledCount,
            'average_ticket' => $ordersCount > 0 ? round($revenue / $ordersCount, 2) : 0.0,
        ];
    }

    /** @return Collection<int, array{name: string, quantity_sold: int, revenue: float, orders_count: int}> */
    public function products(Carbon $from, Carbon $to): Collection
    {
        return $this->itemsInRange($from, $to)
            ->select('order_items.product_name', 'order_items.variant_label')
            ->selectRaw('SUM(order_items.quantity) as quantity_sold')
            ->selectRaw('SUM(order_items.subtotal) as revenue')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders_count')
            ->groupBy('order_items.product_name', 'order_items.variant_label')
            ->orderByDesc('quantity_sold')
            ->orderBy('order_items.product_name')
            ->get()
            ->map(fn ($row) => [
                'name' => $this->formatProductName((string) $row->product_name, $row->variant_label),
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => round((float) $row->revenue, 2),
                'orders_count' => (int) $row->orders_count,
            ]);
    }

    /** @return Collection<int, array{name: string, quantity_sold: int, revenue: float}> */
    public function categories(Carbon $from, Carbon $to): Collection
    {
        $driver = OrderItem::query()->getConnection()->getDriverName();
        $categoryExpression = $driver === 'sqlite'
            ? "COALESCE(categories.name, 'Sem categoria')"
            : "COALESCE(categories.name, 'Sem categoria')";

        return $this->itemsInRange($from, $to)
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw("{$categoryExpression} as category_name")
            ->selectRaw('SUM(order_items.quantity) as quantity_sold')
            ->selectRaw('SUM(order_items.subtotal) as revenue')
            ->groupBy('category_name')
            ->orderByDesc('quantity_sold')
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->category_name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => round((float) $row->revenue, 2),
            ]);
    }

    /** @return Collection<int, array{type: string, label: string, orders_count: int, dishes_count: int, revenue: float}> */
    public function byOrderType(Carbon $from, Carbon $to): Collection
    {
        $orderStats = $this->ordersInRange($from, $to)
            ->select('type')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total) as revenue')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $dishStats = $this->itemsInRange($from, $to)
            ->select('orders.type')
            ->selectRaw('SUM(order_items.quantity) as dishes_count')
            ->groupBy('orders.type')
            ->get()
            ->keyBy('type');

        return collect(self::ORDER_TYPE_LABELS)
            ->map(function (string $label, string $type) use ($orderStats, $dishStats) {
                $orders = $orderStats->get($type);
                $dishes = $dishStats->get($type);

                return [
                    'type' => $type,
                    'label' => $label,
                    'orders_count' => $orders ? (int) $orders->orders_count : 0,
                    'dishes_count' => $dishes ? (int) $dishes->dishes_count : 0,
                    'revenue' => $orders ? round((float) $orders->revenue, 2) : 0.0,
                ];
            })
            ->filter(fn (array $row) => $row['orders_count'] > 0 || $row['dishes_count'] > 0)
            ->values();
    }

    /** @return Builder<Order> */
    private function ordersInRange(Carbon $from, Carbon $to): Builder
    {
        return Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to]);
    }

    /** @return Builder<OrderItem> */
    private function itemsInRange(Carbon $from, Carbon $to): Builder
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$from, $to]);
    }

    private function formatProductName(string $productName, mixed $variantLabel): string
    {
        $name = trim($productName) !== '' ? $productName : 'Produto removido';
        $variant = is_string($variantLabel) ? trim($variantLabel) : '';

        if ($variant !== '') {
            return $name.' ('.$variant.')';
        }

        return $name;
    }
}
