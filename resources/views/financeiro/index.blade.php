<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Financeiro</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('financeiro.create', ['type' => 'entrada']) }}"
                    class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700">
                    Nova entrada
                </a>
                <a href="{{ route('financeiro.create', ['type' => 'saida']) }}"
                    class="inline-flex items-center px-4 py-2 bg-rose-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-rose-700">
                    Nova saída
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <form method="GET" action="{{ route('financeiro.index') }}" class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="from" class="block text-sm font-medium text-gray-700">De</label>
                            <input type="date" name="from" id="from" value="{{ $from }}" required
                                class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="to" class="block text-sm font-medium text-gray-700">Até</label>
                            <input type="date" name="to" id="to" value="{{ $to }}" required
                                class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                            Filtrar
                        </button>
                    </form>
                    <form method="POST" action="{{ route('financeiro.sync-sales') }}" class="mt-3"
                        onsubmit="return confirm(@js($isSingleDay ? 'Lançar no caixa os pedidos já fechados deste dia que ainda não entraram?' : 'Lançar no caixa os pedidos já fechados deste período que ainda não entraram?'));">
                        @csrf
                        <input type="hidden" name="from" value="{{ $from }}">
                        <input type="hidden" name="to" value="{{ $to }}">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-amber-700">
                            {{ $isSingleDay ? 'Sincronizar vendas do dia' : 'Sincronizar vendas do período' }}
                        </button>
                        <p class="mt-2 text-xs text-gray-500">
                            Use se o faturamento do dashboard estiver maior que as entradas do caixa (ex.: pedidos de salão marcados como conta fechada sem fechar a comanda).
                        </p>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border border-emerald-100">
                    <p class="text-sm text-gray-500">Entradas</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-700">R$ {{ number_format($summary['entradas'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border border-rose-100">
                    <p class="text-sm text-gray-500">Saídas</p>
                    <p class="mt-1 text-2xl font-semibold text-rose-700">R$ {{ number_format($summary['saidas'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border border-indigo-100">
                    <p class="text-sm text-gray-500">{{ $isSingleDay ? 'Saldo do dia' : 'Saldo do período' }}</p>
                    <p class="mt-1 text-2xl font-semibold {{ $summary['saldo'] >= 0 ? 'text-indigo-700' : 'text-rose-700' }}">
                        R$ {{ number_format($summary['saldo'], 2, ',', '.') }}
                    </p>
                    @unless ($isSingleDay)
                        <p class="mt-1 text-xs text-gray-500">
                            {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}
                            —
                            {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
                        </p>
                    @endunless
                </div>
            </div>

            @if (count($summary['by_method']) > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4 sm:p-6">
                        <h3 class="text-sm font-semibold text-gray-800 mb-3">Entradas por forma de pagamento</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                            @foreach ($summary['by_method'] as $method => $total)
                                <div class="rounded-lg bg-gray-50 px-3 py-2">
                                    <p class="text-xs text-gray-500">{{ $paymentLabels[$method] ?? $method }}</p>
                                    <p class="text-sm font-semibold text-gray-900">R$ {{ number_format($total, 2, ',', '.') }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">
                        {{ $isSingleDay ? 'Lançamentos do dia' : 'Lançamentos do período' }}
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        {{ $isSingleDay ? 'Horário' : 'Data / horário' }}
                                    </th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pagamento</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($summary['movements'] as $movement)
                                    <tr>
                                        <td class="px-3 py-3 text-sm text-gray-600 whitespace-nowrap">
                                            @if ($isSingleDay)
                                                {{ $movement->occurred_at?->format('H:i') }}
                                            @else
                                                {{ $movement->occurred_at?->format('d/m/Y H:i') }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-3">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                                'bg-emerald-100 text-emerald-800' => $movement->isEntrada(),
                                                'bg-rose-100 text-rose-800' => $movement->isSaida(),
                                            ])>
                                                {{ $movement->typeLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-700">{{ $movement->categoryLabel() }}</td>
                                        <td class="px-3 py-3 text-sm text-gray-700">
                                            <p>{{ $movement->description ?: '—' }}</p>
                                            @if ($movement->comandaLabel())
                                                <p class="text-xs text-gray-500">Comanda {{ $movement->comandaLabel() }}</p>
                                            @elseif ($movement->order)
                                                <p class="text-xs text-gray-500">
                                                    <a href="{{ route('orders.show', $movement->order) }}" class="text-indigo-600 hover:underline">
                                                        Pedido {{ $movement->order->order_number }}
                                                    </a>
                                                </p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-700">{{ $movement->paymentMethodLabel() }}</td>
                                        <td @class([
                                            'px-3 py-3 text-sm text-right font-semibold whitespace-nowrap',
                                            'text-emerald-700' => $movement->isEntrada(),
                                            'text-rose-700' => $movement->isSaida(),
                                        ])>
                                            {{ $movement->isSaida() ? '-' : '+' }}
                                            R$ {{ number_format((float) $movement->amount, 2, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            @if ($movement->isManual())
                                                <form method="POST" action="{{ route('financeiro.destroy', $movement) }}"
                                                    onsubmit="return confirm('Excluir este lançamento manual?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="from" value="{{ $from }}">
                                                    <input type="hidden" name="to" value="{{ $to }}">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Excluir</button>
                                                </form>
                                            @else
                                                <span class="text-xs text-gray-400">Automático</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-3 py-10 text-center text-gray-500">
                                            Nenhum lançamento neste período. Feche uma comanda ou registre uma entrada/saída.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($dayTotals->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4 sm:p-6">
                        <h3 class="text-sm font-semibold text-gray-800 mb-3">
                            {{ $isSingleDay ? 'Resumo do dia' : 'Resumo por dia' }}
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Entradas</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Saídas</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($dayTotals as $day)
                                        <tr>
                                            <td class="px-3 py-2">
                                                <a href="{{ route('financeiro.index', ['from' => $day['date'], 'to' => $day['date']]) }}" class="text-indigo-600 hover:underline">
                                                    {{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}
                                                </a>
                                            </td>
                                            <td class="px-3 py-2 text-right text-emerald-700">R$ {{ number_format($day['entradas'], 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right text-rose-700">R$ {{ number_format($day['saidas'], 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right font-medium">R$ {{ number_format($day['saldo'], 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
