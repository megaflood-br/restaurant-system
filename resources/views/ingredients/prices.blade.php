<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Preços de compra</h2>
                <p class="mt-1 text-sm text-gray-500">Consulte o custo cadastrado dos itens na hora da compra.</p>
            </div>
            <a href="{{ route('ingredients.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                Itens de estoque
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <x-flash-messages />

                    <form method="GET" class="mb-6 flex flex-wrap items-end gap-4">
                        <div class="min-w-[12rem] flex-1">
                            <label for="q" class="block text-sm font-medium text-gray-700">Buscar item</label>
                            <input type="text" name="q" id="q" value="{{ request('q') }}" placeholder="Ex.: farinha, óleo…"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                        <div>
                            <label for="stock_category" class="block text-sm font-medium text-gray-700">Categoria</label>
                            <select name="stock_category" id="stock_category" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">Todas</option>
                                @foreach ($stockCategories as $category)
                                    <option value="{{ $category->id }}" @selected(request('stock_category') == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="sort" class="block text-sm font-medium text-gray-700">Ordenar</label>
                            <select name="sort" id="sort" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="" @selected(! request('sort'))>Nome (A–Z)</option>
                                <option value="cost_asc" @selected(request('sort') === 'cost_asc')>Preço ↑</option>
                                <option value="cost_desc" @selected(request('sort') === 'cost_desc')>Preço ↓</option>
                            </select>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-xs font-semibold uppercase rounded-md hover:bg-gray-700">Filtrar</button>
                        @if (request()->hasAny(['q', 'stock_category', 'sort']))
                            <a href="{{ route('ingredients.prices') }}" class="text-sm text-indigo-600 hover:underline">Limpar</a>
                        @endif
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tamanho do pacote</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preço pacote</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Custo unitário</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Última compra</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($ingredients as $ingredient)
                                    @php $last = $ingredient->lastPurchase; @endphp
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $ingredient->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $ingredient->stockCategory?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            @if ($ingredient->package_size)
                                                {{ number_format((float) $ingredient->package_size, 2, ',', '.') }} {{ $ingredient->packageSizeLabel() }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">
                                            @if ($ingredient->cost_price !== null)
                                                R$ {{ number_format((float) $ingredient->cost_price, 2, ',', '.') }}
                                            @else
                                                <span class="text-gray-400 font-normal">Sem preço</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            {{ $ingredient->unitCost() > 0 ? $ingredient->formattedUnitCost() : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            @if ($last)
                                                <span>R$ {{ number_format((float) $last->cost_price, 2, ',', '.') }}</span>
                                                <span class="block text-xs text-gray-400">{{ $last->created_at->format('d/m/Y') }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route('ingredients.movement', $ingredient) }}" class="text-green-600 hover:text-green-800 text-sm">Entrada</a>
                                            <a href="{{ route('ingredients.edit', $ingredient) }}" class="text-indigo-600 hover:text-indigo-800 text-sm ml-2">Editar</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">Nenhum item encontrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $ingredients->links() }}</div>

                    <p class="mt-6 text-xs text-gray-500">
                        Dica: na <strong>entrada de estoque</strong>, informe o preço pago no pacote para atualizar esta tabela.
                        Compare o valor da prateleira com o “Preço pacote” antes de comprar.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
