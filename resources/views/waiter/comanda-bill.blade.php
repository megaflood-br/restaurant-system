@extends('layouts.waiter')

@section('content')
    @php
        $allServed = $bill ? $bill['orders']->every(fn ($o) => $o->status === 'served') : false;
        $hasReadyToServe = $bill ? $bill['orders']->contains(fn ($o) => $o->status === 'ready') : false;
    @endphp

    <div class="px-4 py-6 space-y-4" x-data>
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('waiter.comandas.index') }}" class="text-sm text-indigo-600 font-medium">← Comandas</a>
                <h2 class="text-2xl font-bold mt-1">Comanda {{ str_pad((string) $comanda, 3, '0', STR_PAD_LEFT) }}</h2>
                @if (! empty($linkedCustomer['name']))
                    <p class="text-sm text-gray-600 mt-0.5">Cliente: <strong>{{ $linkedCustomer['name'] }}</strong></p>
                @endif
                @if ($bill && ($bill['elapsed_label'] ?? null))
                    <p class="text-sm text-gray-500 mt-0.5">
                        Tempo aberta: <strong class="text-gray-700">{{ $bill['elapsed_label'] }}</strong>
                        @if ($bill['first_order_at'] ?? null)
                            <span class="text-gray-400">· desde {{ $bill['first_order_at']->format('H:i') }}</span>
                        @endif
                    </p>
                @elseif (! $bill)
                    <p class="text-sm text-gray-500 mt-0.5">Adicione o primeiro pedido.</p>
                @endif
            </div>
            @if ($bill)
                <div class="text-right">
                    <p class="text-sm text-gray-500">Total da conta</p>
                    <p class="text-2xl font-bold text-indigo-600">R$ {{ number_format($bill['total'], 2, ',', '.') }}</p>
                </div>
            @endif
        </div>

        @if ($hasReadyToServe)
            <div class="rounded-xl bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm text-indigo-900">
                Há pedidos <strong>prontos na cozinha</strong> aguardando entrega ao cliente.
            </div>
        @endif

        @if ($allServed)
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900 flex items-center gap-2">
                <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                </svg>
                <span>Todos os pedidos foram entregues — pronta para fechar a conta.</span>
            </div>
        @endif

        <button type="button"
            @click="$dispatch('open-product-picker', 'comanda-{{ $comanda }}')"
            class="flex items-center justify-center gap-2 w-full rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 text-lg shadow-lg shadow-indigo-600/30 transition active:scale-[0.98]">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Adicionar produtos
        </button>

        @if ($categories->isNotEmpty())
            <x-product-picker-modal
                :categories="$categories"
                :comanda="$comanda"
                :add-url="route('waiter.cart.add')"
                :store-url="route('waiter.store')"
                :summary-url="route('waiter.cart.summary')"
                picker-id="comanda-{{ $comanda }}"
                :auto-open="$autoOpenPicker ?? false"
            />
        @endif

        @if ($bill)
            <div class="bg-white rounded-2xl border border-gray-100 p-4">
                <p class="text-sm text-gray-500 mb-3">{{ $bill['orders_count'] }} pedido(s) na comanda</p>

                @foreach ($bill['orders'] as $order)
                    <div @class([
                        'border-b border-gray-100 last:border-0 py-3 first:pt-0',
                        'bg-red-50/50 -mx-4 px-4 rounded-lg' => $order->isDelayed(),
                    ])>
                        <div class="flex justify-between items-start gap-2 mb-2">
                            <div>
                                <span class="font-semibold text-gray-800">{{ $order->order_number }}</span>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $order->created_at->format('H:i') }}</p>
                            </div>
                            <x-order-wait-time :order="$order" />
                        </div>
                        <div class="mb-2">
                            <x-order-serve-button :order="$order" />
                        </div>
                        @foreach ($order->items as $item)
                            <div class="flex justify-between text-sm text-gray-600 pl-2">
                                <span>{{ $item->quantity }}x {{ $item->displayName() }}</span>
                                <span>R$ {{ number_format($item->subtotal, 2, ',', '.') }}</span>
                            </div>
                        @endforeach
                        <div class="flex justify-between text-sm font-medium mt-1 pl-2">
                            <span class="text-gray-500">Subtotal pedido</span>
                            <span>R$ {{ number_format($order->total, 2, ',', '.') }}</span>
                        </div>
                        @if ($order->user)
                            <p class="text-xs text-gray-400 mt-1 pl-2">Garçom: {{ $order->user->name }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 p-4">
                <h3 class="font-bold text-gray-900 mb-3">Resumo consolidado</h3>
                <div class="space-y-2">
                    @foreach ($bill['items'] as $item)
                        <div class="flex justify-between text-sm">
                            <div>
                                <span class="font-medium">{{ $item['quantity'] }}x {{ $item['name'] }}</span>
                                @if ($item['notes'])
                                    <span class="text-xs text-amber-700 block">{{ $item['notes'] }}</span>
                                @endif
                            </div>
                            <span class="font-semibold">R$ {{ number_format($item['subtotal'], 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-gray-100 mt-4 pt-4 flex justify-between font-bold text-lg">
                    <span>Total</span>
                    <span class="text-indigo-600">R$ {{ number_format($bill['total'], 2, ',', '.') }}</span>
                </div>
            </div>

            @if (session('error'))
                <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-700">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('waiter.comandas.close', $comanda) }}" class="space-y-4">
                @csrf
                <x-payment-method-select compact />
                <button type="submit"
                    class="w-full rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 text-lg shadow-lg shadow-emerald-600/30 transition active:scale-[0.98]">
                    Fechar comanda — R$ {{ number_format($bill['total'], 2, ',', '.') }}
                </button>
            </form>
        @endif
    </div>
@endsection
