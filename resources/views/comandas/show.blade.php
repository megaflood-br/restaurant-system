@php
    $editing = $editing ?? false;
    $customers = $customers ?? collect();
    $pickerUrls = [
        'add' => route('waiter.cart.add'),
        'store' => route('waiter.store'),
        'summary' => route('waiter.cart.summary'),
        'returnUrl' => '/comandas/'.$comanda.($editing ? '?edit=1' : ''),
    ];
    $allServed = $bill ? $bill['orders']->every(fn ($o) => $o->status === 'served') : false;
    $hasReadyToServe = $bill ? $bill['orders']->contains(fn ($o) => $o->status === 'ready') : false;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <div>
                <a href="{{ route('comandas.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Comandas</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight mt-1">
                    Comanda {{ str_pad((string) $comanda, 3, '0', STR_PAD_LEFT) }}
                    @if ($editing)
                        <span class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 align-middle">Editando</span>
                    @endif
                </h2>
                @if (! empty($linkedCustomer['name']))
                    <p class="text-sm text-gray-600 mt-0.5">Cliente: <strong>{{ $linkedCustomer['name'] }}</strong></p>
                @endif
            </div>
            <div class="flex flex-col items-end gap-2">
                @if ($bill)
                    <p class="text-2xl font-bold text-indigo-600">R$ {{ number_format($bill['total'], 2, ',', '.') }}</p>
                @endif
                @if ($editing)
                    <a href="{{ route('comandas.show', $comanda) }}"
                        class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                        Sair da edição
                    </a>
                @else
                    <a href="{{ route('comandas.show', ['comanda' => $comanda, 'edit' => 1]) }}"
                        class="inline-flex items-center px-3 py-1.5 bg-amber-500 text-white rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-amber-600">
                        Editar comanda
                    </a>
                @endif
            </div>
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

            @if ($editing)
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="p-6 space-y-3">
                        <h3 class="font-semibold text-gray-900">Cliente da comanda</h3>
                        <form method="POST" action="{{ route('comandas.customer', $comanda) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                            @csrf
                            @method('PATCH')
                            <div class="flex-1">
                                <label for="customer_id" class="block text-sm font-medium text-gray-700">Vincular cliente</label>
                                <select name="customer_id" id="customer_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Sem cliente vinculado</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}" @selected(($linkedCustomer['id'] ?? null) == $customer->id)>
                                            {{ $customer->name }}@if($customer->phone) — {{ $customer->phone }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                                Salvar cliente
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap gap-3">
                <button type="button"
                    @click="$dispatch('open-product-picker', 'comanda-{{ $comanda }}')"
                    class="inline-flex items-center gap-2 px-5 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 shadow-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Adicionar produtos
                </button>

                @if ($editing)
                    <a href="{{ route('comandas.show', $comanda) }}"
                        class="inline-flex items-center gap-2 px-5 py-3 bg-white border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 shadow-sm transition">
                        Sair da edição
                    </a>
                @else
                    <a href="{{ route('comandas.show', ['comanda' => $comanda, 'edit' => 1]) }}"
                        class="inline-flex items-center gap-2 px-5 py-3 bg-amber-500 text-white font-semibold rounded-lg hover:bg-amber-600 shadow-sm transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar comanda
                    </a>
                @endif
            </div>

            @if ($editing)
                <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                    Modo edição: altere quantidade/obs, remova itens, cancele pedidos ou vincule o cliente.
                </div>
            @endif

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
                                <div @class(['border rounded-lg p-4', 'border-red-200 bg-red-50/30' => $order->isDelayed(), 'border-amber-200 bg-amber-50/40' => $editing && ! $order->isDelayed(), 'border-gray-200' => ! $editing && ! $order->isDelayed()])>
                                    <div class="flex justify-between items-start gap-4 mb-2">
                                        <div>
                                            <a href="{{ route('orders.show', $order) }}" class="font-semibold text-indigo-600 hover:underline">
                                                {{ $order->order_number }}
                                            </a>
                                            <p class="text-xs text-gray-500">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                                        </div>
                                        <div class="flex items-center gap-2 flex-wrap justify-end">
                                            <x-order-status-badge :status="$order->status" />
                                            <x-order-wait-time :order="$order" />
                                            @if ($editing)
                                                <form method="POST" action="{{ route('comandas.orders.cancel', [$comanda, $order]) }}"
                                                    onsubmit="return confirm('Cancelar o pedido {{ $order->order_number }}?')">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-800 uppercase tracking-wide">
                                                        Cancelar pedido
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                    @foreach ($order->items as $item)
                                        @if ($editing)
                                            <div class="mt-3 rounded-md border border-gray-200 bg-white p-3 space-y-2">
                                                <div class="flex justify-between gap-3 text-sm">
                                                    <span class="font-medium text-gray-800">{{ $item->displayName() }}</span>
                                                    <span class="text-gray-600">R$ {{ number_format($item->unit_price, 2, ',', '.') }} un.</span>
                                                </div>
                                                <form method="POST" action="{{ route('comandas.items.update', [$comanda, $order, $item]) }}"
                                                    class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="sm:col-span-2">
                                                        <label class="block text-xs font-medium text-gray-600">Qtd</label>
                                                        <input type="number" name="quantity" min="1" max="999" value="{{ $item->quantity }}" required
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                    </div>
                                                    <div class="sm:col-span-7">
                                                        <label class="block text-xs font-medium text-gray-600">Obs.</label>
                                                        <input type="text" name="notes" value="{{ $item->notes }}"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                    </div>
                                                    <div class="sm:col-span-3 flex gap-2">
                                                        <button type="submit"
                                                            class="flex-1 inline-flex justify-center items-center px-3 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                                                            Salvar
                                                        </button>
                                                    </div>
                                                </form>
                                                <form method="POST" action="{{ route('comandas.items.destroy', [$comanda, $order, $item]) }}"
                                                    onsubmit="return confirm('Remover este item?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">
                                                        Remover item
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <div class="flex justify-between text-sm text-gray-600 pl-2">
                                                <span>{{ $item->quantity }}x {{ $item->displayName() }}@if($item->notes) <span class="text-gray-400">({{ $item->notes }})</span>@endif</span>
                                                <span>R$ {{ number_format($item->subtotal, 2, ',', '.') }}</span>
                                            </div>
                                        @endif
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

                @unless ($editing)
                    <form method="POST" action="{{ route('comandas.close', $comanda) }}" class="space-y-4"
                        onsubmit="return confirm('Fechar a comanda {{ str_pad((string) $comanda, 3, '0', STR_PAD_LEFT) }}?')">
                        @csrf
                        <x-payment-method-select />
                        <button type="submit"
                            class="w-full sm:w-auto inline-flex items-center px-6 py-3 bg-emerald-600 text-white font-semibold rounded-md hover:bg-emerald-700">
                            Fechar comanda — R$ {{ number_format($bill['total'], 2, ',', '.') }}
                        </button>
                    </form>
                @endunless
            @endif
        </div>
    </div>
</x-app-layout>
