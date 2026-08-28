<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Relatórios de vendas</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="preset" class="block text-sm font-medium text-gray-700">Período</label>
                            <select name="preset" id="preset" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="today" @selected($range['preset'] === 'today')>Hoje</option>
                                <option value="yesterday" @selected($range['preset'] === 'yesterday')>Ontem</option>
                                <option value="week" @selected($range['preset'] === 'week')>Esta semana</option>
                                <option value="month" @selected($range['preset'] === 'month')>Este mês</option>
                                <option value="custom" @selected($range['preset'] === 'custom')>Personalizado</option>
                            </select>
                        </div>
                        <div>
                            <label for="from" class="block text-sm font-medium text-gray-700">De</label>
                            <input type="date" name="from" id="from" value="{{ $range['from_date'] }}"
                                class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                        <div>
                            <label for="to" class="block text-sm font-medium text-gray-700">Até</label>
                            <input type="date" name="to" id="to" value="{{ $range['to_date'] }}"
                                class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                            Filtrar
                        </button>
                    </form>
                    <p class="mt-3 text-sm text-gray-500">
                        Período:
                        <span class="font-medium text-gray-800">{{ $range['from']->format('d/m/Y H:i') }}</span>
                        até
                        <span class="font-medium text-gray-800">{{ $range['to']->format('d/m/Y H:i') }}</span>
                        · Pedidos cancelados não entram nas vendas.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 sm:gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-5 border border-sky-100">
                    <p class="text-xs sm:text-sm text-gray-500">Pratos vendidos</p>
                    <p class="text-2xl sm:text-3xl font-bold text-sky-700">{{ number_format($summary['dishes_count'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-500">Pedidos</p>
                    <p class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $summary['orders_count'] }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-5 border border-emerald-100">
                    <p class="text-xs sm:text-sm text-gray-500">Faturamento</p>
                    <p class="text-xl sm:text-2xl font-bold text-emerald-700">R$ {{ number_format($summary['revenue'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-500">Ticket médio</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900">R$ {{ number_format($summary['average_ticket'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-500">Taxas entrega</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900">R$ {{ number_format($summary['delivery_fees'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-500">Descontos</p>
                    <p class="text-xl sm:text-2xl font-bold text-amber-700">R$ {{ number_format($summary['discounts'], 2, ',', '.') }}</p>
                </div>
            </div>

            @if ($summary['cancelled_count'] > 0)
                <p class="text-sm text-gray-500">Pedidos cancelados no período (fora do total): {{ $summary['cancelled_count'] }}</p>
            @endif

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4 sm:p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Pratos vendidos</h3>
                        <p class="text-sm text-gray-500 mt-1">Quantidade e faturamento por item do cardápio.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prato / item</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qtd</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pedidos</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($products as $index => $product)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $product['name'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold text-sky-700">{{ number_format($product['quantity_sold'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-600">{{ $product['orders_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">R$ {{ number_format($product['revenue'], 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">Nenhuma venda no período.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($products->isNotEmpty())
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="2" class="px-4 py-3 text-sm font-semibold text-gray-800">Total</td>
                                        <td class="px-4 py-3 text-sm text-right font-bold text-sky-800">{{ number_format($summary['dishes_count'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-600">{{ $summary['orders_count'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">R$ {{ number_format($summary['items_revenue'], 2, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4 sm:p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Por categoria</h3>
                        </div>
                        <div class="p-4 sm:p-6 divide-y divide-gray-100">
                            @forelse ($categories as $category)
                                <div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $category['name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ number_format($category['quantity_sold'], 0, ',', '.') }} prato(s)</p>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-800 shrink-0">
                                        R$ {{ number_format($category['revenue'], 2, ',', '.') }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">Sem vendas por categoria.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4 sm:p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Por tipo de pedido</h3>
                        </div>
                        <div class="p-4 sm:p-6 divide-y divide-gray-100">
                            @forelse ($byType as $row)
                                <div class="py-3 first:pt-0 last:pb-0">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="font-medium text-gray-900">{{ $row['label'] }}</p>
                                        <span class="text-sm font-semibold text-emerald-700">R$ {{ number_format($row['revenue'], 2, ',', '.') }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $row['orders_count'] }} pedido(s) · {{ number_format($row['dishes_count'], 0, ',', '.') }} prato(s)
                                    </p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">Sem pedidos no período.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
