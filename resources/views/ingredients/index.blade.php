<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Itens de estoque</h2>
            <div class="flex gap-2">
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
                        <div>
                            <label for="stock_category" class="block text-sm font-medium text-gray-700">Filtrar por categoria</label>
                            <select name="stock_category" id="stock_category" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">Todas</option>
                                @foreach ($stockCategories as $category)
                                    <option value="{{ $category->id }}" @selected(request('stock_category') == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-xs font-semibold uppercase rounded-md hover:bg-gray-700">Filtrar</button>
                        @if (request('stock_category'))
                            <a href="{{ route('ingredients.index') }}" class="text-sm text-indigo-600 hover:underline">Limpar filtro</a>
                        @endif
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pacote</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Custo unit.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estoque atual</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estoque mínimo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($ingredients as $ingredient)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $ingredient->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $ingredient->stockCategory?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $ingredient->formattedPackageCost() }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $ingredient->unitCost() > 0 ? $ingredient->formattedUnitCost() : '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ number_format($ingredient->current_stock, 2, ',', '.') }} {{ $ingredient->unit }}</td>
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
                                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">Nenhum item cadastrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $ingredients->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
