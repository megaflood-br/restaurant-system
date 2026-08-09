@props(['status'])

@php
    $classes = match($status) {
        'pending' => 'bg-yellow-100 text-yellow-800',
        'preparing' => 'bg-blue-100 text-blue-800',
        'ready' => 'bg-indigo-100 text-indigo-800',
        'served' => 'bg-teal-100 text-teal-800',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800',
    };

    $labels = [
        'pending' => 'Pendente',
        'preparing' => 'Preparando',
        'ready' => 'Pronto',
        'served' => 'Entregue',
        'delivered' => 'Conta fechada',
        'cancelled' => 'Cancelado',
    ];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {$classes}"]) }}>
    {{ $labels[$status] ?? $status }}
</span>
