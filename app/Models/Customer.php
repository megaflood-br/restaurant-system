<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'birth_date',
        'address',
        'neighborhood',
        'city',
        'state',
        'zip_code',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class)->latest();
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class)->latest();
    }

    public function totalSpent(): float
    {
        return (float) $this->orders()
            ->where('status', '!=', 'cancelled')
            ->sum('total');
    }

    public function ordersCount(): int
    {
        return $this->orders()->count();
    }

    public function lastOrder(): ?Order
    {
        return $this->orders()->latest()->first();
    }

    public function averageTicket(): float
    {
        $count = $this->orders()->where('status', '!=', 'cancelled')->count();

        if ($count === 0) {
            return 0;
        }

        return round($this->totalSpent() / $count, 2);
    }
}
