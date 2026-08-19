<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pedidos</h2>
            <button type="button" x-data="" @click="$dispatch('open-modal', 'new-order')"
                class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Novo pedido
            </button>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <form method="GET" action="{{ route('orders.index') }}" class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700">Dia</label>
                            <input type="date" name="date" id="date" value="{{ $date }}"
                                class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                            Filtrar
                        </button>
                        @if (request()->filled('customer_id'))
                            <input type="hidden" name="customer_id" value="{{ request('customer_id') }}">
                        @endif
                        @if (request()->boolean('new'))
                            <input type="hidden" name="new" value="1">
                        @endif
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border border-indigo-100">
                    <p class="text-sm text-gray-500">Pedidos do dia</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $dailyStats['orders_count'] }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border border-emerald-100">
                    <p class="text-sm text-gray-500">Vendas do dia</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-700">R$ {{ number_format($dailyStats['revenue'], 2, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-gray-500">Sem pedidos cancelados</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border border-gray-200">
                    <p class="text-sm text-gray-500">Cancelados</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-600">{{ $dailyStats['cancelled_count'] }}</p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <x-flash-messages />

                    <x-bulk-select :action="route('orders.bulk-destroy')" confirm="Excluir :count pedido(s) selecionado(s)? Esta ação não pode ser desfeita.">
                        <div class="hidden md:block overflow-x-auto">
                            @include('orders._table', ['orders' => $orders])
                        </div>

                        <div class="md:hidden space-y-3">
                            @php
                                $typeLabels = ['dine_in' => 'Salão', 'delivery' => 'Delivery', 'takeaway' => 'Retirada'];
                            @endphp
                            @forelse ($orders as $order)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start gap-2">
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $order->order_number }}</p>
                                            <p class="text-xs text-gray-500">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                                        </div>
                                        <x-order-status-badge :status="$order->status" />
                                    </div>
                                    <p class="text-sm text-gray-600 mt-2">{{ $typeLabels[$order->type] ?? $order->type }} · R$ {{ number_format($order->total, 2, ',', '.') }}</p>
                                    <div class="mt-3 flex gap-3 text-sm">
                                        <a href="{{ route('orders.show', $order) }}" class="text-indigo-600">Detalhes</a>
                                        @if ($order->status !== 'cancelled')
                                            <a href="{{ route('orders.show', ['order' => $order, 'edit' => 1]) }}" class="text-amber-600 font-medium">Editar</a>
                                        @endif
                                        @include('orders._print-action', [
                                            'order' => $order,
                                            'linkClass' => 'text-gray-600',
                                            'buttonClass' => 'text-gray-600',
                                        ])
                                        <form method="POST" action="{{ route('orders.destroy', $order) }}" class="inline"
                                            onsubmit="return confirm('Excluir o pedido {{ $order->order_number }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600">Excluir</button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center text-gray-500 py-8">Nenhum pedido neste dia.</p>
                            @endforelse
                        </div>
                    </x-bulk-select>

                    <div class="mt-4">{{ $orders->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    <x-modal name="new-order" maxWidth="4xl" focusable>
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Novo pedido</h3>
            @include('orders._form', [
                'products' => $products,
                'selectedCustomer' => $selectedCustomer,
                'modal' => true,
            ])
        </div>
    </x-modal>
</x-app-layout>

@if (request()->boolean('new') || request()->filled('customer_id'))
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'new-order' }));
        });
    </script>
@endif
