@php
    $pickerUrls = [
        'add' => route('waiter.cart.add'),
        'store' => route('waiter.store'),
        'summary' => route('waiter.cart.summary'),
        'returnUrl' => '/comandas/'.$comanda,
    ];
    $allServed = $bill ? $bill['orders']->every(fn ($o) => $o->status === 'served') : false;
    $hasReadyToServe = $bill ? $bill['orders']->contains(fn ($o) => $o->status === 'ready') : false;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <a href="{{ route('comandas.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Comandas</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight mt-1">
                    Comanda {{ str_pad((string) $comanda, 3, '0', STR_PAD_LEFT) }}
                </h2>
                @if (! empty($linkedCustomer['name']))
                    <p class="text-sm text-gray-600 mt-0.5">Cliente: <strong>{{ $linkedCustomer['name'] }}</strong></p>
                @endif
            </div>
            @if ($bill)
                <p class="text-2xl font-bold text-indigo-600">R$ {{ number_format($bill['total'], 2, ',', '.') }}</p>
            @endif
        </div>
    </x-slot>

    <div class="py-12" x-data>
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            @if ($bill && ($bill['elapsed_label'] ?? null))
                <p class="text-sm text-gray-500">
                    Aberta há <strong>{{ $bill['elapsed_label'] }}</strong>
                    @if ($bill['first_order_at'] ?? null)
                        · desde {{ $bill['first_order_at']->format('H:i') }}
                    @endif
                </p>
            @elseif (! $bill)
                <p class="text-sm text-gray-500">Comanda aberta — adicione o primeiro pedido.</p>
            @endif

            @if ($hasReadyToServe)
                <div class="rounded-lg bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm text-indigo-900">
                    Há pedidos prontos na cozinha aguardando entrega.
                </div>
            @endif

            @if ($allServed)
                <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                    Todos os pedidos foram entregues — pronta para fechar a conta.
                </div>
            @endif

            <button type="button"
                @click="$dispatch('open-product-picker', 'comanda-{{ $comanda }}')"
                class="inline-flex items-center gap-2 px-5 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 shadow-sm transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Adicionar produtos
            </button>

            @if ($categories->isNotEmpty())
                <x-product-picker-modal
                    :categories="$categories"
                    :comanda="$comanda"
                    :add-url="$pickerUrls['add']"
                    :store-url="$pickerUrls['store']"
                    :summary-url="$pickerUrls['summary']"
                    :return-url="$pickerUrls['returnUrl']"
                    picker-id="comanda-{{ $comanda }}"
                    :auto-open="$autoOpenPicker"
                />
            @endif

            @if ($bill)
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">{{ $bill['orders_count'] }} pedido(s)</h3>
                        <div class="space-y-4">
                            @foreach ($bill['orders'] as $order)
                                <div @class(['border rounded-lg p-4', 'border-red-200 bg-red-50/30' => $order->isDelayed(), 'border-gray-200' => ! $order->isDelayed()])>
                                    <div class="flex justify-between items-start gap-4 mb-2">
                                        <div>
                                            <a href="{{ route('orders.show', $order) }}" class="font-semibold text-indigo-600 hover:underline">
                                                {{ $order->order_number }}
                                            </a>
                                            <p class="text-xs text-gray-500">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <x-order-status-badge :status="$order->status" />
                                            <x-order-wait-time :order="$order" />
                                        </div>
                                    </div>
                                    @foreach ($order->items as $item)
                                        <div class="flex justify-between text-sm text-gray-600 pl-2">
                                            <span>{{ $item->quantity }}x {{ $item->displayName() }}</span>
                                            <span>R$ {{ number_format($item->subtotal, 2, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                    <div class="flex justify-between text-sm font-medium mt-2 pl-2 border-t border-gray-100 pt-2">
                                        <span class="text-gray-500">Subtotal</span>
                                        <span>R$ {{ number_format($order->total, 2, ',', '.') }}</span>
                                    </div>
                                    @if ($order->user)
                                        <p class="text-xs text-gray-400 mt-1 pl-2">Garçom: {{ $order->user->name }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Resumo consolidado</h3>
                        <div class="space-y-2">
                            @foreach ($bill['items'] as $item)
                                <div class="flex justify-between text-sm">
                                    <span>{{ $item['quantity'] }}x {{ $item['name'] }}</span>
                                    <span class="font-medium">R$ {{ number_format($item['subtotal'], 2, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="border-t border-gray-200 mt-4 pt-4 flex justify-between font-bold text-lg">
                            <span>Total</span>
                            <span class="text-indigo-600">R$ {{ number_format($bill['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('comandas.close', $comanda) }}" class="space-y-4"
                    onsubmit="return confirm('Fechar a comanda {{ str_pad((string) $comanda, 3, '0', STR_PAD_LEFT) }}?')">
                    @csrf
                    <x-payment-method-select />
                    <button type="submit"
                        class="w-full sm:w-auto inline-flex items-center px-6 py-3 bg-emerald-600 text-white font-semibold rounded-md hover:bg-emerald-700">
                        Fechar comanda — R$ {{ number_format($bill['total'], 2, ',', '.') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
