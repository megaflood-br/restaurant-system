<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Itens de estoque</h2>
            <div class="flex gap-2">
                <a href="{{ route('ingredients.prices') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    Preços de compra
                </a>
                <a href="{{ route('stock-categories.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    Categorias
                </a>
                <a href="{{ route('ingredients.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                    Novo item
                </a>
            </div>
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
                            <input type="text" name="q" id="q" value="{{ $filters['q'] ?? request('q') }}" placeholder="Ex.: farinha, óleo…"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                        <div>
                            <label for="stock_category" class="block text-sm font-medium text-gray-700">Categoria</label>
                            <select name="stock_category" id="stock_category" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">Todas</option>
                                @foreach ($stockCategories as $category)
                                    <option value="{{ $category->id }}" @selected(($filters['stock_category'] ?? request('stock_category')) == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="stock" class="block text-sm font-medium text-gray-700">Situação do estoque</label>
                            <select name="stock" id="stock" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="" @selected(! ($filters['stock'] ?? request('stock')))>Todas</option>
                                <option value="low" @selected(($filters['stock'] ?? request('stock')) === 'low')>Estoque baixo</option>
                                <option value="ok" @selected(($filters['stock'] ?? request('stock')) === 'ok')>Estoque OK</option>
                                <option value="zero" @selected(($filters['stock'] ?? request('stock')) === 'zero')>Sem estoque (zerado)</option>
                            </select>
                        </div>
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700">Preço de compra</label>
                            <select name="price" id="price" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="" @selected(! ($filters['price'] ?? request('price')))>Todos</option>
                                <option value="with" @selected(($filters['price'] ?? request('price')) === 'with')>Com preço cadastrado</option>
                                <option value="without" @selected(($filters['price'] ?? request('price')) === 'without')>Sem preço cadastrado</option>
                            </select>
                        </div>
                        <div>
                            <label for="sort" class="block text-sm font-medium text-gray-700">Ordenar</label>
                            <select name="sort" id="sort" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="" @selected(! ($filters['sort'] ?? request('sort')))>Nome (A–Z)</option>
                                <option value="name_desc" @selected(($filters['sort'] ?? request('sort')) === 'name_desc')>Nome (Z–A)</option>
                                <option value="stock_asc" @selected(($filters['sort'] ?? request('sort')) === 'stock_asc')>Estoque ↑</option>
                                <option value="stock_desc" @selected(($filters['sort'] ?? request('sort')) === 'stock_desc')>Estoque ↓</option>
                                <option value="minimum_asc" @selected(($filters['sort'] ?? request('sort')) === 'minimum_asc')>Estoque mínimo ↑</option>
                            </select>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-xs font-semibold uppercase rounded-md hover:bg-gray-700">Filtrar</button>
                        @if (request()->hasAny(['q', 'stock_category', 'stock', 'price', 'sort']))
                            <a href="{{ route('ingredients.index') }}" class="text-sm text-indigo-600 hover:underline">Limpar filtros</a>
                        @endif
                    </form>

                    @if (request('stock') === 'low')
                        <p class="mb-4 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                            Mostrando itens com estoque atual ≤ estoque mínimo.
                        </p>
                    @endif

                    <div class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Valor total em estoque</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900">R$ {{ number_format($stockSummary['total_value'], 2, ',', '.') }}</p>
                            <p class="mt-1 text-xs text-gray-500">
                                @if (request()->hasAny(['q', 'stock_category', 'stock', 'price', 'sort']))
                                    Com os filtros atuais
                                @else
                                    Todos os itens com preço cadastrado
                                @endif
                            </p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Itens listados</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $stockSummary['items_count'] }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Sem preço de compra</p>
                            <p class="mt-1 text-2xl font-semibold {{ $stockSummary['unpriced_count'] > 0 ? 'text-amber-700' : 'text-gray-900' }}">{{ $stockSummary['unpriced_count'] }}</p>
                            @if ($stockSummary['unpriced_count'] > 0)
                                <p class="mt-1 text-xs text-amber-700">Não entram no valor total.</p>
                            @endif
                        </div>
                    </div>

                    <x-bulk-select :action="route('ingredients.bulk-destroy')" confirm="Excluir :count item(ns) de estoque? Isso remove também o vínculo nas fichas técnicas.">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <x-bulk-select-all />
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pacote</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Custo unit.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estoque atual</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estoque mínimo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($ingredients as $ingredient)
                                    @php $lineValue = $ingredient->stockValue(); @endphp
                                    <tr>
                                        <x-bulk-select-item :id="$ingredient->id" />
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $ingredient->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $ingredient->stockCategory?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $ingredient->formattedPackageCost() }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $ingredient->unitCost() > 0 ? $ingredient->formattedUnitCost() : '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ number_format($ingredient->current_stock, 2, ',', '.') }} {{ $ingredient->unit }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                            @if ($lineValue > 0)
                                                R$ {{ number_format($lineValue, 2, ',', '.') }}
                                            @else
                                                <span class="text-gray-400 font-normal">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ number_format($ingredient->minimum_stock, 2, ',', '.') }} {{ $ingredient->unit }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $ingredient->isLowStock() ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                                {{ $ingredient->isLowStock() ? 'Baixo' : 'OK' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right space-x-2">
                                            <a href="{{ route('ingredients.movement', $ingredient) }}" class="text-green-600 hover:text-green-800 text-sm">Movimentar</a>
                                            <a href="{{ route('ingredients.edit', $ingredient) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Editar</a>
                                            <form action="{{ route('ingredients.destroy', $ingredient) }}" method="POST" class="inline" onsubmit="return confirm('Excluir este item?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Excluir</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-4 py-8 text-center text-gray-500">Nenhum item cadastrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    </x-bulk-select>

                    <div class="mt-4">{{ $ingredients->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
