<table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pedido</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente/Comanda</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Garçom</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
        @php
            $typeLabels = ['dine_in' => 'Salão', 'delivery' => 'Delivery', 'takeaway' => 'Retirada'];
        @endphp
        @forelse ($orders as $order)
            <tr>
                <td class="px-4 py-3">
                    <p class="font-medium text-gray-900">{{ $order->order_number }}</p>
                    <p class="text-xs text-gray-500">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                    @if ($order->isScheduled())
                        <p class="text-xs font-medium text-amber-700 mt-0.5">📅 {{ $order->scheduledLabel() }}</p>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $typeLabels[$order->type] ?? $order->type }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">
                    @if ($order->type === 'dine_in')
                        Comanda {{ $order->comanda_number ? str_pad((string) $order->comanda_number, 3, '0', STR_PAD_LEFT) : '—' }}
                    @elseif ($order->customer)
                        <a href="{{ route('customers.show', $order->customer) }}" class="text-indigo-600 hover:text-indigo-800">
                            {{ $order->customer->name }}
                        </a>
                    @else
                        {{ $order->customer_name ?? '—' }}
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $order->user?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm font-medium text-gray-900">R$ {{ number_format($order->total, 2, ',', '.') }}</td>
                <td class="px-4 py-3"><x-order-status-badge :status="$order->status" /></td>
                <td class="px-4 py-3 text-right space-x-2">
                    <a href="{{ route('orders.show', $order) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Detalhes</a>
                    <a href="{{ route('orders.print', $order) }}" target="_blank" class="text-gray-600 hover:text-gray-800 text-sm">Imprimir</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Nenhum pedido registrado.</td>
            </tr>
        @endforelse
    </tbody>
</table>
