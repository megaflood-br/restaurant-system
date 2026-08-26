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
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Estoque atual</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($ingredient->current_stock, 2, ',', '.') }} {{ $ingredient->unit }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Preço de compra (pacote)</p>
                        <p class="text-lg font-semibold text-gray-900">
                            @if ($ingredient->cost_price)
                                R$ {{ number_format((float) $ingredient->cost_price, 2, ',', '.') }}
                                @if ($ingredient->package_size)
                                    <span class="text-sm font-normal text-gray-500">/ {{ number_format((float) $ingredient->package_size, 2, ',', '.') }} {{ $ingredient->packageSizeLabel() }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </p>
                        @if ($ingredient->unitCost() > 0)
                            <p class="text-xs text-gray-500 mt-0.5">{{ $ingredient->formattedUnitCost() }}</p>
                        @endif
                    </div>
                    <div>
                        @if ($ingredient->stockCategory)
                            <p class="text-sm text-gray-500">Categoria</p>
                            <p class="text-sm text-gray-800 mt-1">{{ $ingredient->stockCategory->name }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="{ type: '{{ old('type', 'in') }}' }">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Registrar movimentação manual</h3>
                    <form method="POST" action="{{ route('ingredients.movement.store', $ingredient) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700">Tipo</label>
                            <select name="type" id="type" x-model="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="in">Entrada</option>
                                <option value="out">Saída</option>
                            </select>
                        </div>
                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700">Quantidade ({{ $ingredient->unit }})</label>
                            <input type="number" step="0.01" min="0.01" name="quantity" id="quantity" required
                                value="{{ old('quantity') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div x-show="type === 'in'" x-cloak>
                            <label for="cost_price" class="block text-sm font-medium text-gray-700">Preço de compra do pacote (R$)</label>
                            <input type="number" step="0.01" min="0" name="cost_price" id="cost_price"
                                value="{{ old('cost_price', $ingredient->cost_price) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Ex.: 45,90">
                            <p class="mt-1 text-xs text-gray-500">
                                Opcional. Se preencher, atualiza o custo do item
                                @if ($ingredient->package_size)
                                    (pacote de {{ number_format((float) $ingredient->package_size, 2, ',', '.') }} {{ $ingredient->packageSizeLabel() }})
                                @endif
                                para comparar nas próximas compras.
                            </p>
                            @error('cost_price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea name="notes" id="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
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
                            <div class="flex justify-between items-start gap-3 border-b border-gray-100 pb-3">
                                <div class="min-w-0">
                                    <p class="font-medium {{ $movement->type === 'in' ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $movement->type === 'in' ? 'Entrada' : 'Saída' }} — {{ number_format($movement->quantity, 2, ',', '.') }} {{ $ingredient->unit }}
                                    </p>
                                    @if ($movement->type === 'in' && $movement->cost_price !== null)
                                        <p class="text-sm text-emerald-700">Preço pacote: R$ {{ number_format((float) $movement->cost_price, 2, ',', '.') }}</p>
                                    @endif
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
                                @if ($movement->reason === 'manual')
                                    <form method="POST" action="{{ route('ingredients.movement.destroy', [$ingredient, $movement]) }}"
                                        onsubmit="return confirm('Estornar esta movimentação? O estoque será ajustado automaticamente.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:text-red-800 shrink-0">Estornar</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nenhuma movimentação registrada.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 text-sm">
                <a href="{{ route('ingredients.index') }}" class="text-indigo-600 hover:text-indigo-800">← Voltar ao estoque</a>
                <a href="{{ route('ingredients.prices') }}" class="text-indigo-600 hover:text-indigo-800">Consultar preços de compra</a>
            </div>
        </div>
    </div>
</x-app-layout>
