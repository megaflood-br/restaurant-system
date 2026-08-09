<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInteraction extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'content',
        'user_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function typeLabels(): array
    {
        return [
            'note' => 'Anotação',
            'call' => 'Ligação',
            'email' => 'E-mail',
            'visit' => 'Visita',
            'complaint' => 'Reclamação',
            'feedback' => 'Feedback',
        ];
    }
}
