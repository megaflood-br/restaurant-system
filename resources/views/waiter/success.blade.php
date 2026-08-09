@extends('layouts.waiter')

@section('content')
    <div class="px-4 py-8 text-center">
        <div class="w-20 h-20 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 mb-1">Pedido enviado!</h2>
        <p class="text-gray-500 mb-6">Comanda {{ str_pad((string) $order->comanda_number, 3, '0', STR_PAD_LEFT) }} — cozinha notificada</p>

        <div class="bg-white rounded-2xl border border-gray-100 p-5 text-left space-y-3 mb-6">
            <div class="text-center border-b border-gray-100 pb-3">
                <p class="text-sm text-gray-500">Pedido</p>
                <p class="text-xl font-bold text-indigo-600">{{ $order->order_number }}</p>
            </div>

            @foreach ($order->items as $item)
                <div class="flex justify-between text-sm">
                    <span>{{ $item->quantity }}x {{ $item->displayName() }}</span>
                    <span class="font-medium">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</span>
                </div>
            @endforeach

            <div class="border-t border-gray-100 pt-3 flex justify-between font-bold">
                <span>Total</span>
                <span class="text-indigo-600">R$ {{ number_format($order->total, 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="space-y-3">
            <a href="{{ route('waiter.menu') }}"
                class="block w-full rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 transition">
                Novo pedido — Comanda {{ str_pad((string) $order->comanda_number, 3, '0', STR_PAD_LEFT) }}
            </a>
            <a href="{{ route('waiter.orders') }}"
                class="block w-full rounded-2xl bg-white border border-gray-200 text-gray-700 font-semibold py-3 transition">
                Ver pedidos abertos
            </a>
        </div>
    </div>
@endsection
