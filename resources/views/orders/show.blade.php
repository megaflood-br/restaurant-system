<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pedido {{ $order->order_number }}</h2>
            <x-order-status-badge :status="$order->status" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            @php
                $typeLabels = ['dine_in' => 'Salão', 'delivery' => 'Delivery', 'takeaway' => 'Retirada'];
            @endphp

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

                @if ($order->notes)
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
