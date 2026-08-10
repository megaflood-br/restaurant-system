@extends('layouts.menu')

@section('page-title', 'Pedido confirmado')

@section('content')
    <div class="px-4 py-8 text-center">
        <div class="w-20 h-20 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 mb-2">Pedido confirmado!</h2>
        <p class="text-gray-500 mb-6">Seu pedido foi enviado para a cozinha.</p>

        <div class="bg-white rounded-2xl border border-gray-100 p-6 text-left space-y-4 mb-6">
            <div class="text-center border-b border-gray-100 pb-4">
                <p class="text-sm text-gray-500">Número do pedido</p>
                <p class="text-2xl font-bold menu-text">{{ $order->order_number }}</p>
            </div>

            @php
                $typeLabels = ['dine_in' => 'Salão', 'delivery' => 'Delivery', 'takeaway' => 'Retirada'];
                $statusLabels = ['pending' => 'Recebido', 'preparing' => 'Preparando', 'ready' => 'Pronto', 'delivered' => 'Entregue'];
            @endphp

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-500">Tipo</p>
                    <p class="font-semibold">{{ $typeLabels[$order->type] ?? $order->type }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Status</p>
                    <p class="font-semibold">{{ $statusLabels[$order->status] ?? $order->status }}</p>
                </div>
            </div>

            @if ($order->type === 'dine_in' && $order->comanda_number)
                <p class="text-sm"><span class="text-gray-500">Comanda:</span> <strong>{{ str_pad((string) $order->comanda_number, 3, '0', STR_PAD_LEFT) }}</strong></p>
            @endif

            @if ($order->type === 'delivery')
                @if ($order->deliveryArea)
                    <p class="text-sm"><span class="text-gray-500">Bairro:</span> <strong>{{ $order->deliveryArea->name }}</strong></p>
                @endif
                @if ($order->delivery_address)
                    <p class="text-sm"><span class="text-gray-500">Endereço:</span> <strong>{{ $order->delivery_address }}</strong></p>
                @endif
            @endif

            <div class="border-t border-gray-100 pt-4 space-y-2">
                @foreach ($order->items as $item)
                    <div class="flex justify-between text-sm">
                        <span>{{ $item->quantity }}x {{ $item->displayName() }}</span>
                        <span>R$ {{ number_format($item->subtotal, 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>

            @if ($order->delivery_fee > 0)
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Taxa de entrega</span>
                    <span>R$ {{ number_format($order->delivery_fee, 2, ',', '.') }}</span>
                </div>
            @endif

            <div class="border-t border-gray-100 pt-4 flex justify-between font-bold">
                <span>Total</span>
                <span class="menu-text">R$ {{ number_format($order->total, 2, ',', '.') }}</span>
            </div>
        </div>

        <a href="{{ route('public.menu') }}"
            class="inline-block w-full rounded-2xl menu-bg menu-bg-hover text-white font-semibold py-4 transition">
            Fazer novo pedido
        </a>
    </div>
@endsection
