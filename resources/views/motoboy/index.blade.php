<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Apuração motoboy</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <form method="GET" action="{{ route('motoboy.index') }}" class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700">Dia do expediente</label>
                            <input type="date" name="date" id="date" value="{{ $date }}"
                                class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 pb-2">
                            <input type="checkbox" name="delivered_only" value="1" @checked($deliveredOnly)
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            Somente entregues
                        </label>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                            Filtrar
                        </button>
                    </form>
                    <p class="mt-3 text-xs text-gray-500">
                        Some as taxas de entrega dos pedidos delivery do dia e informe a diária para calcular o total a pagar ao motoboy.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border border-indigo-100">
                    <p class="text-sm text-gray-500">Entregas no dia</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['deliveries_count'] }}</p>
                    @if ($summary['pending_count'] > 0)
                        <p class="mt-1 text-xs text-amber-700">{{ $summary['pending_count'] }} ainda não entregue(s)</p>
                    @endif
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border border-emerald-100">
                    <p class="text-sm text-gray-500">Taxas de entrega</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-700">R$ {{ number_format($summary['delivery_fees_total'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border border-amber-100">
                    <p class="text-sm text-gray-500">Diária</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-700">R$ {{ number_format($dailyRate, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border border-violet-100">
                    <p class="text-sm text-gray-500">Total a pagar</p>
                    <p class="mt-1 text-2xl font-bold text-violet-800">R$ {{ number_format($totalPayout, 2, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Fechamento do dia</h3>

                    <form method="POST" action="{{ route('motoboy.store') }}" class="space-y-4 max-w-xl"
                        x-data="{
                            dailyRate: {{ json_encode((float) old('daily_rate', $dailyRate)) }},
                            feesTotal: {{ json_encode((float) $summary['delivery_fees_total']) }},
                            get totalPayout() { return this.dailyRate + this.feesTotal; },
                        }">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        @if ($deliveredOnly)
                            <input type="hidden" name="delivered_only" value="1">
                        @endif

                        <div>
                            <label for="daily_rate" class="block text-sm font-medium text-gray-700">Diária (R$)</label>
                            <input type="number" step="0.01" min="0" name="daily_rate" id="daily_rate" required
                                x-model.number="dailyRate"
                                class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea name="notes" id="notes" rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $settlement?->notes) }}</textarea>
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="mark_paid" value="1" @checked(old('mark_paid', $settlement?->paid_at !== null))
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            Marcar como pago
                        </label>

                        @if ($settlement?->paid_at)
                            <p class="text-xs text-emerald-700">
                                Pago em {{ $settlement->paid_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                @if ($settlement->user)
                                    · {{ $settlement->user->name }}
                                @endif
                            </p>
                        @endif

                        <div class="rounded-lg bg-violet-50 border border-violet-100 px-4 py-3 text-sm text-violet-900">
                            <p>
                                <span class="font-medium">Resumo:</span>
                                R$ {{ number_format($summary['delivery_fees_total'], 2, ',', '.') }} (taxas)
                                + <span x-text="'R$ ' + dailyRate.toFixed(2).replace('.', ',')"></span> (diária)
                                = <span class="font-bold" x-text="'R$ ' + totalPayout.toFixed(2).replace('.', ',')"></span>
                            </p>
                        </div>

                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                            Salvar apuração
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Pedidos delivery do dia</h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pedido</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Horário</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Taxa</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total pedido</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($orders as $order)
                                    <tr>
                                        <td class="px-3 py-3 text-sm">
                                            <a href="{{ route('orders.show', $order) }}" class="text-indigo-600 hover:underline font-medium">
                                                {{ $order->order_number }}
                                            </a>
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap">
                                            {{ $order->created_at->timezone(config('app.timezone'))->format('H:i') }}
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-700">
                                            {{ $order->displayCustomerName() ?? '—' }}
                                        </td>
                                        <td class="px-3 py-3">
                                            <x-order-status-badge :status="$order->status" />
                                        </td>
                                        <td class="px-3 py-3 text-sm text-right font-medium text-emerald-700">
                                            R$ {{ number_format($order->delivery_fee, 2, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-3 text-sm text-right text-gray-700">
                                            R$ {{ number_format($order->total, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500">
                                            Nenhum pedido delivery neste dia{{ $deliveredOnly ? ' com status entregue' : '' }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($orders->isNotEmpty())
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="4" class="px-3 py-3 text-sm font-semibold text-gray-800 text-right">Total das taxas</td>
                                        <td class="px-3 py-3 text-sm font-bold text-emerald-800 text-right">
                                            R$ {{ number_format($summary['delivery_fees_total'], 2, ',', '.') }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
