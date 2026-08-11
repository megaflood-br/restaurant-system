<?php

namespace App\Models;

use App\Support\CashCategory;
use App\Support\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    protected $fillable = [
        'type',
        'category',
        'amount',
        'payment_method',
        'description',
        'occurred_at',
        'reference_date',
        'source',
        'source_key',
        'comanda_number',
        'order_id',
        'user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'reference_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEntrada(): bool
    {
        return $this->type === 'entrada';
    }

    public function isSaida(): bool
    {
        return $this->type === 'saida';
    }

    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    public function typeLabel(): string
    {
        return $this->isEntrada() ? 'Entrada' : 'Saída';
    }

    public function categoryLabel(): string
    {
        return CashCategory::label($this->category);
    }

    public function paymentMethodLabel(): string
    {
        return PaymentMethod::label($this->payment_method);
    }

    public function signedAmount(): float
    {
        $amount = (float) $this->amount;

        return $this->isEntrada() ? $amount : -$amount;
    }

    public function comandaLabel(): ?string
    {
        if ($this->comanda_number === null) {
            return null;
        }

        return str_pad((string) $this->comanda_number, 3, '0', STR_PAD_LEFT);
    }
}
