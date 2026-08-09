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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <x-flash-messages />

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
                                    <a href="{{ route('orders.print', $order) }}" target="_blank" class="text-gray-600">Imprimir</a>
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-gray-500 py-8">Nenhum pedido registrado.</p>
                        @endforelse
                    </div>

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
                'customers' => $customers,
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
