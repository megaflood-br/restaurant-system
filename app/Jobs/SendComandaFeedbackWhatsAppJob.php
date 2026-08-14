<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Order;
use App\Services\WhatsAppService;
use App\Support\AppSettings;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendComandaFeedbackWhatsAppJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @param  list<int>  $orderIds
     */
    public function __construct(
        public int $comandaNumber,
        public array $orderIds,
        public string $closedOnDate,
    ) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        AppSettings::loadIntoConfig();

        if (! config('whatsapp_agent.comanda_feedback_enabled')) {
            return;
        }

        if (! config('evolution.enabled')) {
            return;
        }

        $cacheKey = $this->idempotencyKey();

        if (! Cache::add($cacheKey, 1, now()->addDays(3))) {
            return;
        }

        $orders = Order::query()
            ->with(['customer', 'items'])
            ->whereIn('id', $this->orderIds)
            ->get();

        if ($orders->isEmpty()) {
            Cache::forget($cacheKey);

            return;
        }

        [$phone, $customer, $order] = $this->resolveRecipient($orders);

        if ($phone === null) {
            Cache::forget($cacheKey);
            Log::info('Comanda feedback skipped: no phone', [
                'comanda' => $this->comandaNumber,
                'date' => $this->closedOnDate,
            ]);

            return;
        }

        $message = $this->buildMessage($orders, $customer);

        if (trim($message) === '') {
            Cache::forget($cacheKey);

            return;
        }

        try {
            $record = $whatsapp->sendToPhone(
                $phone,
                $message,
                $customer,
                $order,
                null,
                logInteraction: false,
                sentByBot: true,
            );

            $record->update([
                'metadata' => array_merge(
                    is_array($record->metadata) ? $record->metadata : [],
                    [
                        'purpose' => 'comanda_feedback',
                        'comanda_number' => $this->comandaNumber,
                        'closed_on' => $this->closedOnDate,
                        'order_ids' => $this->orderIds,
                    ]
                ),
            ]);

            if ($customer) {
                $customer->interactions()->create([
                    'type' => 'feedback',
                    'content' => '[Pedido de feedback WhatsApp] '.$message,
                    'user_id' => null,
                ]);
            }
        } catch (\Throwable $exception) {
            Cache::forget($cacheKey);

            Log::error('Comanda feedback WhatsApp failed', [
                'comanda' => $this->comandaNumber,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function idempotencyKey(): string
    {
        return 'comanda_feedback:'.$this->closedOnDate.':'.$this->comandaNumber;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Order>  $orders
     * @return array{0: ?string, 1: ?Customer, 2: Order}
     */
    private function resolveRecipient($orders): array
    {
        foreach ($orders as $order) {
            $customer = $order->customer;
            $phone = PhoneNumber::normalize($customer?->phone)
                ?? PhoneNumber::normalize($order->customer_phone);

            if ($phone !== null) {
                return [$phone, $customer, $order];
            }
        }

        return [null, null, $orders->first()];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Order>  $orders
     */
    private function buildMessage($orders, ?Customer $customer): string
    {
        $template = (string) config('whatsapp_agent.comanda_feedback_message', '');

        if ($template === '') {
            $defaults = require config_path('whatsapp_agent.php');
            $template = (string) ($defaults['comanda_feedback_message'] ?? '');
        }

        $items = $orders
            ->flatMap(fn (Order $order) => $order->items)
            ->groupBy(fn ($item) => $item->product_name)
            ->map(function ($group, $name) {
                $qty = (int) $group->sum('quantity');

                return $qty > 1 ? "{$qty}x {$name}" : $name;
            })
            ->values()
            ->implode(', ');

        $restaurantName = filled(config('whatsapp_agent.restaurant_name'))
            ? (string) config('whatsapp_agent.restaurant_name')
            : (string) config('printing.restaurant_name', config('app.name', 'Restaurant System'));

        $customerName = $customer?->name
            ?? $orders->first(fn (Order $o) => filled($o->customer_name))?->customer_name
            ?? 'cliente';

        $replacements = [
            'restaurant_name' => $restaurantName,
            'customer_name' => $customerName,
            'comanda' => str_pad((string) $this->comandaNumber, 3, '0', STR_PAD_LEFT),
            'items' => $items !== '' ? $items : 'seus pratos',
        ];

        return str_replace(
            array_map(fn ($key) => '{'.$key.'}', array_keys($replacements)),
            array_values($replacements),
            $template
        );
    }
}
