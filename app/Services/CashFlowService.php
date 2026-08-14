<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Order;
use App\Support\CashCategory;
use App\Support\PaymentMethod;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashFlowService
{
    /**
     * @param  array{
     *     type: string,
     *     category: string,
     *     amount: float|int|string,
     *     payment_method?: ?string,
     *     description?: ?string,
     *     occurred_at?: CarbonInterface|string|null,
     *     user_id?: ?int,
     *     order_id?: ?int,
     *     comanda_number?: ?int,
     *     source?: string,
     *     source_key?: ?string,
     *     meta?: ?array
     * }  $data
     */
    public function record(array $data): CashMovement
    {
        $type = $data['type'];
        $category = $data['category'];
        $amount = round((float) $data['amount'], 2);

        if (! in_array($type, ['entrada', 'saida'], true)) {
            throw new \InvalidArgumentException('Tipo de lançamento inválido.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Valor do lançamento deve ser maior que zero.');
        }

        if (! in_array($category, CashCategory::keysForType($type), true)) {
            throw new \InvalidArgumentException('Categoria inválida para o tipo '.$type.'.');
        }

        $occurredAt = isset($data['occurred_at'])
            ? Carbon::parse($data['occurred_at'])->timezone(config('app.timezone'))
            : now()->timezone(config('app.timezone'));

        $paymentMethod = $data['payment_method'] ?? null;
        if ($paymentMethod !== null && $paymentMethod !== '' && PaymentMethod::normalize($paymentMethod) === null) {
            throw new \InvalidArgumentException('Forma de pagamento inválida.');
        }

        return CashMovement::create([
            'type' => $type,
            'category' => $category,
            'amount' => $amount,
            'payment_method' => $paymentMethod ? PaymentMethod::normalize($paymentMethod) : null,
            'description' => $data['description'] ?? null,
            'occurred_at' => $occurredAt,
            'reference_date' => $occurredAt->toDateString(),
            'source' => $data['source'] ?? 'manual',
            'source_key' => $data['source_key'] ?? null,
            'comanda_number' => $data['comanda_number'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'meta' => $data['meta'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $bill  Summary from ComandaBillService::closeComanda
     */
    public function recordComandaClose(array $bill, ?int $userId = null): ?CashMovement
    {
        $comandaNumber = (int) ($bill['comanda_number'] ?? 0);
        $total = round((float) ($bill['total'] ?? 0), 2);
        $paymentMethod = (string) ($bill['payment_method'] ?? '');

        if ($comandaNumber < 1 || $total <= 0) {
            return null;
        }

        $orderIds = collect($bill['orders'] ?? [])
            ->map(fn ($order) => is_object($order) ? $order->id : ($order['id'] ?? null))
            ->filter()
            ->values()
            ->all();

        $referenceDate = today()->toDateString();
        $sourceKey = 'comanda:'.$referenceDate.':'.$comandaNumber;

        if (CashMovement::query()->where('source', 'comanda')->where('source_key', $sourceKey)->exists()) {
            return CashMovement::query()->where('source', 'comanda')->where('source_key', $sourceKey)->first();
        }

        // Evita duplicar se pedidos da comanda já entraram individualmente no caixa.
        $alreadyRecorded = (float) CashMovement::query()
            ->where('source', 'order')
            ->whereIn('order_id', $orderIds)
            ->sum('amount');

        $amount = round($total - $alreadyRecorded, 2);

        if ($amount <= 0) {
            return null;
        }

        try {
            return $this->record([
                'type' => 'entrada',
                'category' => 'venda_comanda',
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'description' => 'Fechamento da comanda '.str_pad((string) $comandaNumber, 3, '0', STR_PAD_LEFT),
                'comanda_number' => $comandaNumber,
                'order_id' => $orderIds[0] ?? null,
                'user_id' => $userId,
                'source' => 'comanda',
                'source_key' => $sourceKey,
                'meta' => [
                    'order_ids' => $orderIds,
                    'orders_count' => count($orderIds),
                    'already_recorded_orders' => $alreadyRecorded,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Cash flow: failed to record comanda close', [
                'comanda' => $comandaNumber,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function recordOrderSale(Order $order, ?int $userId = null): ?CashMovement
    {
        if ($order->status === 'cancelled') {
            return null;
        }

        $total = round((float) $order->total, 2);

        if ($total <= 0) {
            return null;
        }

        // Só ignora se ESTE pedido já entrou no fechamento da comanda (meta.order_ids).
        if ($order->type === 'dine_in' && $order->comanda_number) {
            $comandaMovement = $this->comandaCloseMovementFor($order);

            if ($comandaMovement && $this->orderCoveredByComandaClose($order, $comandaMovement)) {
                return $comandaMovement;
            }
        }

        $sourceKey = 'order:'.$order->id;

        if (CashMovement::query()->where('source', 'order')->where('source_key', $sourceKey)->exists()) {
            return CashMovement::query()->where('source', 'order')->where('source_key', $sourceKey)->first();
        }

        $category = match ($order->type) {
            'delivery' => 'venda_delivery',
            'takeaway' => 'venda_retirada',
            'dine_in' => 'venda_comanda',
            default => 'venda',
        };

        $label = match ($order->type) {
            'delivery' => 'Delivery',
            'takeaway' => 'Retirada',
            'dine_in' => 'Salão / comanda '.str_pad((string) ($order->comanda_number ?? 0), 3, '0', STR_PAD_LEFT),
            default => 'Pedido',
        };

        try {
            return $this->record([
                'type' => 'entrada',
                'category' => $category,
                'amount' => $total,
                'payment_method' => $order->payment_method,
                'description' => $label.' '.$order->order_number,
                'order_id' => $order->id,
                'comanda_number' => $order->comanda_number,
                'user_id' => $userId,
                'source' => 'order',
                'source_key' => $sourceKey,
                'occurred_at' => $order->created_at ?? $order->updated_at ?? now(),
                'meta' => [
                    'order_type' => $order->type,
                    'order_number' => $order->order_number,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Cash flow: failed to record order sale', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Cria lançamentos faltantes para pedidos já entregues/fechados no dia.
     *
     * @return array{created: int, amount: float}
     */
    public function syncDeliveredSalesForDate(CarbonInterface $date, ?int $userId = null): array
    {
        $date = $date->timezone(config('app.timezone'))->startOfDay();

        $orders = Order::query()
            ->whereDate('created_at', $date->toDateString())
            ->where('status', 'delivered')
            ->orderBy('id')
            ->get();

        $created = 0;
        $amount = 0.0;

        foreach ($orders as $order) {
            if ($this->hasSaleRecorded($order)) {
                continue;
            }

            $movement = $this->recordOrderSale($order, $userId);

            if ($movement && $movement->wasRecentlyCreated) {
                $created++;
                $amount += (float) $movement->amount;
            }
        }

        return [
            'created' => $created,
            'amount' => round($amount, 2),
        ];
    }

    public function hasSaleRecorded(Order $order): bool
    {
        if (CashMovement::query()->where('source', 'order')->where('source_key', 'order:'.$order->id)->exists()) {
            return true;
        }

        if ($order->type === 'dine_in' && $order->comanda_number) {
            $comandaMovement = $this->comandaCloseMovementFor($order);

            return $comandaMovement !== null
                && $this->orderCoveredByComandaClose($order, $comandaMovement);
        }

        return false;
    }

    /**
     * Diferença entre totais de pedidos entregues do dia e o que já está no caixa
     * (lançamentos order/comanda desse dia).
     */
    public function deliveredSalesGapForDate(CarbonInterface $date): float
    {
        $dateString = $date->timezone(config('app.timezone'))->toDateString();

        $ordersTotal = (float) Order::query()
            ->whereDate('created_at', $dateString)
            ->where('status', 'delivered')
            ->sum('total');

        $cashTotal = (float) CashMovement::query()
            ->whereDate('reference_date', $dateString)
            ->where('type', 'entrada')
            ->whereIn('source', ['order', 'comanda'])
            ->sum('amount');

        return round(max(0, $ordersTotal - $cashTotal), 2);
    }

    private function comandaCloseMovementFor(Order $order): ?CashMovement
    {
        if (! $order->comanda_number) {
            return null;
        }

        $comandaKey = 'comanda:'.($order->created_at?->toDateString() ?? today()->toDateString()).':'.$order->comanda_number;

        return CashMovement::query()
            ->where('source', 'comanda')
            ->where('source_key', $comandaKey)
            ->first();
    }

    /**
     * Pedido de salão só está coberto pelo fechamento se aparecer em meta.order_ids.
     * Sem meta (legado): assume coberto para não duplicar.
     */
    private function orderCoveredByComandaClose(Order $order, CashMovement $comandaMovement): bool
    {
        $meta = $comandaMovement->meta ?? null;

        if (! is_array($meta) || ! array_key_exists('order_ids', $meta)) {
            return true;
        }

        $ids = collect($meta['order_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();

        return in_array((int) $order->id, $ids, true);
    }

    public function deleteManual(CashMovement $movement): void
    {
        if (! $movement->isManual()) {
            throw new \RuntimeException('Só é possível excluir lançamentos manuais.');
        }

        $movement->delete();
    }

    /** @return array{date: string, entradas: float, saidas: float, saldo: float, by_method: array<string, float>, movements: Collection<int, CashMovement>} */
    public function dailySummary(?CarbonInterface $date = null): array
    {
        $date = ($date ?? today())->timezone(config('app.timezone'))->startOfDay();
        $dateString = $date->toDateString();

        $movements = CashMovement::query()
            ->with(['user', 'order'])
            ->whereDate('reference_date', $dateString)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        $entradas = (float) $movements->where('type', 'entrada')->sum('amount');
        $saidas = (float) $movements->where('type', 'saida')->sum('amount');

        $byMethod = [];
        foreach ($movements->where('type', 'entrada') as $movement) {
            $key = $movement->payment_method ?: 'nao_informado';
            $byMethod[$key] = ($byMethod[$key] ?? 0) + (float) $movement->amount;
        }

        return [
            'date' => $dateString,
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo' => $entradas - $saidas,
            'by_method' => $byMethod,
            'movements' => $movements,
        ];
    }

    /** @return Collection<int, array{date: string, entradas: float, saidas: float, saldo: float}> */
    public function rangeTotals(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return CashMovement::query()
            ->select([
                'reference_date',
                DB::raw("SUM(CASE WHEN type = 'entrada' THEN amount ELSE 0 END) as entradas"),
                DB::raw("SUM(CASE WHEN type = 'saida' THEN amount ELSE 0 END) as saidas"),
            ])
            ->whereDate('reference_date', '>=', $from->toDateString())
            ->whereDate('reference_date', '<=', $to->toDateString())
            ->groupBy('reference_date')
            ->orderBy('reference_date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->reference_date)->toDateString(),
                'entradas' => (float) $row->entradas,
                'saidas' => (float) $row->saidas,
                'saldo' => (float) $row->entradas - (float) $row->saidas,
            ]);
    }
}
