<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryArea extends Model
{
    protected $fillable = [
        'name',
        'min_km',
        'max_km',
        'fee',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_km' => 'decimal:2',
            'max_km' => 'decimal:2',
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function rangeLabel(): string
    {
        if ($this->max_km === null) {
            return number_format((float) $this->min_km, 1, ',', '.').' km+';
        }

        return number_format((float) $this->min_km, 1, ',', '.').' – '.number_format((float) $this->max_km, 1, ',', '.').' km';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
