<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MotoboySettlement extends Model
{
    protected $fillable = [
        'settlement_date',
        'daily_rate',
        'delivery_fees_total',
        'deliveries_count',
        'notes',
        'paid_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'daily_rate' => 'decimal:2',
            'delivery_fees_total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function totalPayout(): float
    {
        return (float) $this->delivery_fees_total + (float) $this->daily_rate;
    }
}
