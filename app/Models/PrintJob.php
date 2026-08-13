<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PRINTING = 'printing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type',
        'order_id',
        'payload',
        'status',
        'attempts',
        'last_error',
        'claimed_at',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
