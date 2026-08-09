<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'customer_id',
        'order_id',
        'direction',
        'phone',
        'message',
        'status',
        'evolution_message_id',
        'metadata',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function findCustomerByPhone(string $phone): ?Customer
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        return Customer::query()
            ->whereNotNull('phone')
            ->get()
            ->first(function (Customer $customer) use ($normalized) {
                return PhoneNumber::normalize($customer->phone) === $normalized;
            });
    }
}
