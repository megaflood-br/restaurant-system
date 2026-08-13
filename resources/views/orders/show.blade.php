@php
    $editing = $editing ?? false;
    $customers = $customers ?? collect();
    $typeLabels = ['dine_in' => 'Salão', 'delivery' => 'Delivery', 'takeaway' => 'Retirada'];
    $canEdit = $order->status !== 'cancelled';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Pedido {{ $order->order_number }}
                    @if ($editing)
                        <span class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 align-middle">Editando</span>
                    @endif
                </h2>
            </div>
            <x-order-status-badge :status="$order->status" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <div class="flex flex-wrap gap-3">
                @if ($canEdit)
                    @if ($editing)
                        <a href="{{ route('orders.show', $order) }}"
                            class="inline-flex items-center gap-2 px-5 py-3 bg-white border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 shadow-sm transition">
                            Sair da edição
                        </a>
                    @else
                        <a href="{{ route('orders.show', ['order' => $order, 'edit' => 1]) }}"
                            class="inline-flex items-center gap-2 px-5 py-3 bg-amber-500 text-white font-semibold rounded-lg hover:bg-amber-600 shadow-sm transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Editar pedido
                        </a>
                    @endif
                @endif
            </div>

            @if ($editing)
                <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                    Modo edição: altere quantidade/obs dos itens, remova itens ou atualize cliente/observações.
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <p class="text-sm text-gray-500">Tipo</p>
                        <p class="font-medium text-gray-900">{{ $typeLabels[$order->type] ?? $order->type }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">{{ $order->type === 'dine_in' ? 'Comanda' : 'Cliente' }}</p>
                        <p class="font-medium text-gray-900">
                            @if ($order->type === 'dine_in')
                                Comanda {{ $order->comanda_number ? str_pad((string) $order->comanda_number, 3, '0', STR_PAD_LEFT) : '—' }}
                            @elseif ($order->customer)
                                <a href="{{ route('customers.show', $order->customer) }}" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $order->customer->name }}
                                </a>
                            @else
                                {{ $order->customer_name ?? '—' }}
                            @endif
                        </p>
                        @if ($order->customer_phone)
                            <p class="text-sm text-gray-500">{{ $order->customer_phone }}</p>
                        @endif
                        @if ($order->type === 'delivery')
                            @if ($order->deliveryArea)
                                <p class="text-sm text-gray-500">{{ $order->deliveryArea->name }}</p>
                            @endif
                            @if ($order->delivery_address)
                                <p class="text-sm text-gray-500">{{ $order->delivery_address }}</p>
                            @endif
                        @endif
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Garçom / Atendente</p>
                        <p class="font-medium text-gray-900">{{ $order->user?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total</p>
                        <p class="text-2xl font-bold text-green-600">R$ {{ number_format($order->total, 2, ',', '.') }}</p>
                        @if ($order->payment_method)
                            <p class="text-sm text-gray-500 mt-1">Pagamento: <strong>{{ \App\Support\PaymentMethod::label($order->payment_method) }}</strong></p>
                        @endif
                        @if ($order->delivery_fee > 0)
                            <p class="text-xs text-gray-500">inclui taxa de entrega R$ {{ number_format($order->delivery_fee, 2, ',', '.') }}</p>
                        @endif
                    </div>
                </div>

                @if ($editing)
                    <form method="POST" action="{{ route('orders.details', $order) }}" class="mb-6 space-y-4 rounded-lg border border-amber-200 bg-amber-50/40 p-4">
                        @csrf
                        @method('PATCH')
                        <h3 class="text-sm font-semibold text-gray-900">Dados do pedido</h3>
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea name="notes" id="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $order->notes) }}</textarea>
                        </div>
                        @if ($order->type !== 'dine_in')
                            <div>
                                <label for="customer_id" class="block text-sm font-medium text-gray-700">Cliente cadastrado</label>
                                <select name="customer_id" id="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Sem cliente / manual</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}" @selected(old('customer_id', $order->customer_id) == $customer->id)>
                                            {{ $customer->name }}@if($customer->phone) — {{ $customer->phone }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label for="customer_name" class="block text-sm font-medium text-gray-700">Nome (manual)</label>
                                    <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name', $order->customer_name) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label for="customer_phone" class="block text-sm font-medium text-gray-700">Telefone</label>
                                    <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone', $order->customer_phone) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                        @endif
                        @if ($order->type === 'delivery')
                            <div>
                                <label for="delivery_address" class="block text-sm font-medium text-gray-700">Endereço de entrega</label>
                                <input type="text" name="delivery_address" id="delivery_address" value="{{ old('delivery_address', $order->delivery_address) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="max-w-xs">
                                <label for="delivery_fee" class="block text-sm font-medium text-gray-700">Taxa de entrega (R$)</label>
                                <input type="number" step="0.01" min="0" name="delivery_fee" id="delivery_fee" value="{{ old('delivery_fee', $order->delivery_fee) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        @endif
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                            Salvar dados
                        </button>
                    </form>
                @elseif ($order->notes)
                    <div class="mb-6 p-3 bg-yellow-50 rounded-md text-sm text-yellow-800">
                        <strong>Observações:</strong> {{ $order->notes }}
                    </div>
                @endif

                @if ($order->scheduled_for)
                    <div class="mb-6 p-3 bg-amber-50 border border-amber-200 rounded-md text-sm text-amber-900">
                        <strong>Horário agendado:</strong> {{ $order->scheduledLabel() }}
                        <span class="text-amber-700">({{ $order->scheduled_for->format('d/m/Y H:i') }})</span>
                    </div>
                @endif

                <h3 class="text-lg font-semibold text-gray-800 mb-4">Itens</h3>

                @if ($editing)
                    <div class="space-y-3 mb-6">
                        @forelse ($order->items as $item)
                            <div class="rounded-lg border border-gray-200 p-4 space-y-2">
                                <div class="flex justify-between gap-3 text-sm">
                                    <span class="font-medium text-gray-900">{{ $item->displayName() }}</span>
                                    <span class="text-gray-600">R$ {{ number_format($item->unit_price, 2, ',', '.') }} un.</span>
                                </div>
                                <form method="POST" action="{{ route('orders.items.update', [$order, $item]) }}"
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
                                    <div class="sm:col-span-3">
                                        <button type="submit"
                                            class="w-full inline-flex justify-center items-center px-3 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                                            Salvar
                                        </button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('orders.items.destroy', [$order, $item]) }}"
                                    onsubmit="return confirm('Remover este item?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">
                                        Remover item
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nenhum item neste pedido.</p>
                        @endforelse
                    </div>
                @else
                    <div class="overflow-x-auto mb-6">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produto</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qtd</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preço unit.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900">{{ $item->displayName() }}</p>
                                            @if ($item->notes)
                                                <p class="text-sm text-gray-500">{{ $item->notes }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">{{ $item->quantity }}</td>
                                        <td class="px-4 py-3 text-sm">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm font-medium">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="flex flex-wrap items-end gap-3">
                    <form method="POST" action="{{ route('orders.status', $order) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Atualizar status</label>
                            <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach (['pending' => 'Pendente', 'preparing' => 'Preparando', 'ready' => 'Pronto', 'served' => 'Entregue', 'delivered' => 'Conta fechada', 'cancelled' => 'Cancelado'] as $value => $label)
                                    <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            Atualizar
                        </button>
                    </form>

                    @php
                        $serverSidePrint = in_array(config('printing.driver'), ['network', 'agent'], true)
                            && (config('printing.driver') === 'agent' || filled(config('printing.network.host')));
                    @endphp

                    @if ($serverSidePrint)
                        <form method="POST" action="{{ route('orders.print.network', $order) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                                {{ config('printing.driver') === 'agent' ? 'Imprimir (agente)' : 'Imprimir na rede' }}
                            </button>
                        </form>
                        <a href="{{ route('orders.print', ['order' => $order]) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                            Ver no navegador
                        </a>
                    @else
                        <a href="{{ route('orders.print', ['order' => $order, 'autoprint' => 1]) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            Imprimir comanda
                        </a>
                    @endif

                    <form method="POST" action="{{ route('orders.destroy', $order) }}"
                        onsubmit="return confirm('Excluir o pedido {{ $order->order_number }}? Esta ação não pode ser desfeita.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
                            Excluir pedido
                        </button>
                    </form>
                </div>
            </div>

            <a href="{{ route('orders.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Voltar aos pedidos</a>
        </div>
    </div>
</x-app-layout>
