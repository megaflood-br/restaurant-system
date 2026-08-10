@extends('layouts.menu')

@section('page-title', 'Carrinho')

@section('content')
    <div class="px-4 py-6 space-y-4">
        <h2 class="text-xl font-bold">Seu carrinho</h2>

        @foreach ($items as $item)
            <div class="bg-white rounded-2xl border border-gray-100 p-4 flex gap-3">
                @if ($item['image_url'])
                    <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" class="w-16 h-16 rounded-xl object-cover shrink-0">
                @endif
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900">{{ $item['name'] }}</h3>
                    @if ($item['notes'])
                        <p class="text-xs text-gray-500">{{ $item['notes'] }}</p>
                    @endif
                    <p class="menu-text font-bold mt-1">R$ {{ number_format($item['subtotal'], 2, ',', '.') }}</p>

                    <div class="flex items-center gap-2 mt-2">
                        <form method="POST" action="{{ route('public.cart.update') }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                            @if (!empty($item['variant_id']))
                                <input type="hidden" name="variant_id" value="{{ $item['variant_id'] }}">
                            @endif
                            <input type="hidden" name="notes" value="{{ $item['notes'] }}">
                            <input type="hidden" name="quantity" value="{{ max(0, $item['quantity'] - 1) }}">
                            <button type="submit" class="w-8 h-8 rounded-full bg-gray-100 font-bold">−</button>
                        </form>
                        <span class="w-6 text-center font-semibold">{{ $item['quantity'] }}</span>
                        <form method="POST" action="{{ route('public.cart.update') }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                            @if (!empty($item['variant_id']))
                                <input type="hidden" name="variant_id" value="{{ $item['variant_id'] }}">
                            @endif
                            <input type="hidden" name="notes" value="{{ $item['notes'] }}">
                            <input type="hidden" name="quantity" value="{{ $item['quantity'] + 1 }}">
                            <button type="submit" class="w-8 h-8 rounded-full bg-gray-100 font-bold">+</button>
                        </form>

                        <form method="POST" action="{{ route('public.cart.remove') }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                            @if (!empty($item['variant_id']))
                                <input type="hidden" name="variant_id" value="{{ $item['variant_id'] }}">
                            @endif
                            <input type="hidden" name="notes" value="{{ $item['notes'] }}">
                            <button type="submit" class="text-red-500 text-xs font-medium ml-2">Remover</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="bg-white rounded-2xl border border-gray-100 p-4 flex justify-between items-center">
            <span class="font-semibold text-gray-700">Total</span>
            <span class="text-xl font-bold menu-text">R$ {{ number_format($total, 2, ',', '.') }}</span>
        </div>

        <a href="{{ route('public.checkout') }}"
            class="block w-full text-center rounded-2xl menu-bg menu-bg-hover text-white font-semibold py-4 menu-shadow transition">
            Finalizar pedido
        </a>

        <a href="{{ route('public.menu') }}" class="block text-center text-sm text-gray-500 py-2">Continuar comprando</a>
    </div>
@endsection
