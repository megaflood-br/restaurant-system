<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'ingredient_id',
        'order_id',
        'type',
        'reason',
        'quantity',
        'cost_price',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'cost_price' => 'decimal:2',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
