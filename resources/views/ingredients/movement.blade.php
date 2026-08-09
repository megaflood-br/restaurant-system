@php
    $reasonLabels = [
        'sale' => 'Venda',
        'sale_cancel' => 'Estorno de venda',
        'manual' => 'Manual',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Movimentação — {{ $ingredient->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">Estoque atual</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($ingredient->current_stock, 2, ',', '.') }} {{ $ingredient->unit }}</p>
                @if ($ingredient->stockCategory)
                    <p class="text-sm text-gray-500 mt-1">Categoria: {{ $ingredient->stockCategory->name }}</p>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Registrar movimentação manual</h3>
                    <form method="POST" action="{{ route('ingredients.movement.store', $ingredient) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700">Tipo</label>
                            <select name="type" id="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="in">Entrada</option>
                                <option value="out">Saída</option>
                            </select>
                        </div>
                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700">Quantidade ({{ $ingredient->unit }})</label>
                            <input type="number" step="0.01" min="0.01" name="quantity" id="quantity" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea name="notes" id="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            Registrar
                        </button>
                    </form>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Últimas movimentações</h3>
                    <div class="space-y-3">
                        @forelse ($movements as $movement)
                            <div class="flex justify-between items-start border-b border-gray-100 pb-3">
                                <div>
                                    <p class="font-medium {{ $movement->type === 'in' ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $movement->type === 'in' ? 'Entrada' : 'Saída' }} — {{ number_format($movement->quantity, 2, ',', '.') }} {{ $ingredient->unit }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ $reasonLabels[$movement->reason] ?? $movement->reason ?? '—' }}
                                        @if ($movement->order)
                                            · <a href="{{ route('orders.show', $movement->order) }}" class="text-indigo-600 hover:underline">Pedido {{ $movement->order->order_number }}</a>
                                        @endif
                                    </p>
                                    @if ($movement->notes)
                                        <p class="text-sm text-gray-500">{{ $movement->notes }}</p>
                                    @endif
                                    <p class="text-xs text-gray-400">{{ $movement->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nenhuma movimentação registrada.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <a href="{{ route('ingredients.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Voltar ao estoque</a>
        </div>
    </div>
</x-app-layout>
