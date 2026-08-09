@extends('layouts.waiter')

@section('content')
    <div class="px-4 py-6 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold">Pedido — Comanda {{ $comandaNumber ? str_pad((string) $comandaNumber, 3, '0', STR_PAD_LEFT) : '?' }}</h2>
            @if ($comandaNumber && $categories->isNotEmpty())
                <button type="button" @click="$dispatch('open-product-picker', 'waiter-cart')"
                    class="text-sm text-indigo-600 font-semibold">+ Itens</button>
            @endif
        </div>

        @foreach ($items as $item)
            <div class="bg-white rounded-2xl border border-gray-100 p-4 flex gap-3">
                @if ($item['image_url'])
                    <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" class="w-16 h-16 rounded-xl object-cover shrink-0">
                @endif
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900">{{ $item['name'] }}</h3>
                    @if ($item['notes'])
                        <p class="text-xs text-amber-700 bg-amber-50 rounded px-2 py-0.5 mt-1 inline-block">{{ $item['notes'] }}</p>
                    @endif
                    <p class="text-indigo-600 font-bold mt-1">R$ {{ number_format($item['subtotal'], 2, ',', '.') }}</p>

                    <div class="flex items-center gap-3 mt-3">
                        <form method="POST" action="{{ route('waiter.cart.update') }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                            <input type="hidden" name="notes" value="{{ $item['notes'] }}">
                            <input type="hidden" name="quantity" value="{{ max(0, $item['quantity'] - 1) }}">
                            <button type="submit" class="w-10 h-10 rounded-xl bg-gray-100 font-bold text-lg active:bg-gray-200">−</button>
                        </form>
                        <span class="w-8 text-center font-bold text-lg">{{ $item['quantity'] }}</span>
                        <form method="POST" action="{{ route('waiter.cart.update') }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                            <input type="hidden" name="notes" value="{{ $item['notes'] }}">
                            <input type="hidden" name="quantity" value="{{ $item['quantity'] + 1 }}">
                            <button type="submit" class="w-10 h-10 rounded-xl bg-gray-100 font-bold text-lg active:bg-gray-200">+</button>
                        </form>

                        <form method="POST" action="{{ route('waiter.cart.remove') }}" class="ml-auto">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                            <input type="hidden" name="notes" value="{{ $item['notes'] }}">
                            <button type="submit" class="text-red-500 text-sm font-semibold px-2 py-1">Remover</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="bg-white rounded-2xl border border-gray-100 p-4 flex justify-between items-center">
            <span class="font-semibold text-gray-700">Total</span>
            <span class="text-2xl font-bold text-indigo-600">R$ {{ number_format($total, 2, ',', '.') }}</span>
        </div>

        <form method="POST" action="{{ route('waiter.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="comanda_number" value="{{ $comandaNumber }}">

            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Observações do pedido</label>
                <textarea name="notes" id="notes" rows="2" placeholder="Ex: cliente com pressa, aniversário..."
                    class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
            </div>

            @if ($errors->any())
                <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @if (!$comandaNumber)
                <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">
                    Informe a comanda no topo da tela antes de enviar.
                </div>
                <button type="button" disabled class="w-full rounded-2xl bg-gray-300 text-gray-500 font-bold py-4 text-lg cursor-not-allowed">
                    Enviar para cozinha
                </button>
            @else
                <button type="submit" class="w-full rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 text-lg shadow-lg shadow-indigo-600/30 transition active:scale-[0.98]">
                    Enviar para cozinha
                </button>
            @endif
        </form>

        @if (($comandaNumber ?? null) && ($categories ?? collect())->isNotEmpty())
            <x-product-picker-modal
                :categories="$categories"
                :comanda="$comandaNumber"
                :add-url="route('waiter.cart.add')"
                :store-url="route('waiter.store')"
                :summary-url="route('waiter.cart.summary')"
                picker-id="waiter-cart"
            />
        @endif
    </div>
@endsection
