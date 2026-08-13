<?php

namespace App\Models;

use App\Support\ElapsedTime;
use App\Support\OrderSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_id',
        'comanda_number',
        'delivery_area_id',
        'delivery_fee',
        'delivery_address',
        'type',
        'status',
        'customer_name',
        'customer_phone',
        'notes',
        'scheduled_for',
        'total',
        'payment_method',
        'user_id',
        'inventory_deducted_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'scheduled_for' => 'datetime',
            'inventory_deducted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveryArea(): BelongsTo
    {
        return $this->belongsTo(DeliveryArea::class);
    }

    public function itemsSubtotal(): float
    {
        return (float) $this->items()->sum('subtotal');
    }

    public function displayCustomerName(): ?string
    {
        return $this->customer?->name ?? $this->customer_name;
    }

    public function recalculateTotal(): void
    {
        $this->update([
            'total' => $this->itemsSubtotal() + (float) $this->delivery_fee,
        ]);
    }

    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = sprintf('PED-%s-', $date);

        $max = 0;

        foreach (static::query()->where('order_number', 'like', $prefix.'%')->pluck('order_number') as $number) {
            if (preg_match('/^PED-\d{8}-(\d+)$/', (string) $number, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $sequence = $max + 1;

        // Garante número livre mesmo se houver “buracos” ou corrida entre requests.
        while (
            static::query()
                ->where('order_number', sprintf('PED-%s-%04d', $date, $sequence))
                ->exists()
        ) {
            $sequence++;
        }

        return sprintf('PED-%s-%04d', $date, $sequence);
    }

    public function waitingMinutes(): int
    {
        return ElapsedTime::minutes($this->created_at);
    }

    public function waitingLabel(): string
    {
        return ElapsedTime::label($this->created_at);
    }

    public function isDelayed(): bool
    {
        if (in_array($this->status, ['served', 'delivered', 'cancelled'], true)) {
            return false;
        }

        return $this->waitingMinutes() >= (int) config('restaurant.order_delay_minutes', 25);
    }

    public function canBeMarkedServed(): bool
    {
        return $this->type === 'dine_in' && $this->status === 'ready';
    }

    public static function delayThresholdMinutes(): int
    {
        return (int) config('restaurant.order_delay_minutes', 25);
    }

    public function scheduledLabel(): ?string
    {
        if ($this->scheduled_for === null) {
            return null;
        }

        return OrderSchedule::formatLabel($this->scheduled_for);
    }

    public function isScheduled(): bool
    {
        return $this->scheduled_for !== null && $this->scheduled_for->isFuture();
    }
}
