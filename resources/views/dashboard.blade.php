<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6">
                    <p class="text-xs sm:text-sm text-gray-500">Clientes ativos</p>
                    <p class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $stats['customers'] }}</p>
                    <a href="{{ route('customers.index') }}" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-800 mt-2 inline-block">Ver CRM</a>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6">
                    <p class="text-xs sm:text-sm text-gray-500">Produtos cadastrados</p>
                    <p class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $stats['products'] }}</p>
                    <a href="{{ route('products.index') }}" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-800 mt-2 inline-block">Ver cardápio</a>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6">
                    <p class="text-xs sm:text-sm text-gray-500">Pedidos hoje</p>
                    <p class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $stats['orders_today'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6">
                    <p class="text-xs sm:text-sm text-gray-500">Pedidos mês</p>
                    <p class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $stats['orders_month'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6">
                    <p class="text-xs sm:text-sm text-gray-500">Faturamento hoje</p>
                    <p class="text-xl sm:text-3xl font-bold text-green-600">R$ {{ number_format($stats['revenue_today'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6">
                    <p class="text-xs sm:text-sm text-gray-500">Faturamento mês</p>
                    <p class="text-xl sm:text-3xl font-bold text-green-600">R$ {{ number_format($stats['revenue_month'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6">
                    <p class="text-xs sm:text-sm text-gray-500">Pedidos em andamento</p>
                    <p class="text-2xl sm:text-3xl font-bold text-amber-600">{{ $stats['orders_in_progress'] }}</p>
                    <a href="{{ route('orders.index') }}" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-800 mt-2 inline-block">Ver pedidos</a>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6">
                    <p class="text-xs sm:text-sm text-gray-500">Estoque baixo</p>
                    <p class="text-2xl sm:text-3xl font-bold text-red-600">{{ $stats['low_stock_count'] }}</p>
                    @if ($stats['low_stock_value'] > 0)
                        <p class="text-xs sm:text-sm text-red-700 mt-1">R$ {{ number_format($stats['low_stock_value'], 2, ',', '.') }} em risco</p>
                    @endif
                    <a href="{{ route('ingredients.index', ['stock' => 'low']) }}" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-800 mt-2 inline-block">Ver estoque baixo</a>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6">
                    <p class="text-xs sm:text-sm text-gray-500">Valor total em estoque</p>
                    <p class="text-xl sm:text-3xl font-bold text-indigo-700">R$ {{ number_format($stats['stock_total_value'], 2, ',', '.') }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $stats['stock_items_count'] }} item(ns) · {{ $stats['stock_unpriced_count'] }} sem preço</p>
                    <a href="{{ route('ingredients.index') }}" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-800 mt-2 inline-block">Ver estoque</a>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4 sm:p-6 border-b border-gray-200 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                        <h3 class="text-lg font-semibold text-gray-800">Pedidos do dia</h3>
                        <a href="{{ route('orders.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">Ver todos</a>
                    </div>
                    <div class="p-4 sm:p-6 divide-y divide-gray-100">
                        @forelse ($orders_today as $order)
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 py-3 first:pt-0 last:pb-0">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $order->order_number }}</p>
                                    <p class="text-sm text-gray-500">
                                        {{ $order->created_at->format('H:i') }} ·
                                        {{ $order->items->count() }} item(ns) ·
                                        R$ {{ number_format($order->total, 2, ',', '.') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <x-order-status-badge :status="$order->status" />
                                    <a href="{{ route('orders.show', $order) }}" class="text-sm text-indigo-600">Ver</a>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">Nenhum pedido registrado hoje.</p>
                        @endforelse
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4 sm:p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Em andamento</h3>
                        </div>
                        <div class="p-4 sm:p-6 divide-y divide-gray-100">
                            @forelse ($pending_orders as $order)
                                <div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 truncate">{{ $order->order_number }}</p>
                                        <p class="text-xs text-gray-500 truncate">R$ {{ number_format($order->total, 2, ',', '.') }}</p>
                                    </div>
                                    <x-order-status-badge :status="$order->status" />
                                </div>
                            @empty
                                <p class="text-gray-500 text-sm">Nenhum pedido em andamento.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4 sm:p-6 border-b border-gray-200 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Estoque baixo</h3>
                                @if ($stats['low_stock_value'] > 0)
                                    <p class="text-xs text-gray-500 mt-1">Valor: R$ {{ number_format($stats['low_stock_value'], 2, ',', '.') }}</p>
                                @endif
                            </div>
                            <a href="{{ route('ingredients.index', ['stock' => 'low']) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Ver</a>
                        </div>
                        <div class="p-4 sm:p-6 divide-y divide-gray-100">
                            @forelse ($low_stock as $ingredient)
                                @php $lineValue = $ingredient->stockValue(); @endphp
                                <div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 truncate">{{ $ingredient->name }}</p>
                                        <p class="text-xs text-gray-500">
                                            Mín. {{ number_format($ingredient->minimum_stock, 2, ',', '.') }} {{ $ingredient->unit }}
                                            @if ($lineValue > 0)
                                                · R$ {{ number_format($lineValue, 2, ',', '.') }}
                                            @endif
                                        </p>
                                    </div>
                                    <span class="text-sm font-semibold text-red-600 shrink-0">
                                        {{ number_format($ingredient->current_stock, 2, ',', '.') }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-gray-500 text-sm">Estoque OK.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
