<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Fichas técnicas</h2>
            <a href="{{ route('recipes.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                Nova ficha
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <x-flash-messages />

                <x-bulk-select :action="route('recipes.bulk-destroy')" confirm="Excluir :count ficha(s) técnica(s) selecionada(s)?">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <x-bulk-select-all />
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ficha</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cardápio</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ingredientes</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rendimento</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Custo / porção</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($recipes as $recipe)
                                    <tr>
                                        <x-bulk-select-item :id="$recipe->id" />
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900">{{ $recipe->name }}</p>
                                            @if ($recipe->description)
                                                <p class="text-gray-500 text-xs">{{ Str::limit($recipe->description, 50) }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">
                                            {{ $recipe->product?->name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3">{{ $recipe->ingredients_count }}</td>
                                        <td class="px-4 py-3">{{ $recipe->yield_portions }} porções</td>
                                        <td class="px-4 py-3 font-medium">R$ {{ number_format($recipe->costPerPortion(), 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right space-x-2">
                                            <a href="{{ route('recipes.edit', $recipe) }}" class="text-indigo-600 hover:underline">Editar</a>
                                            <a href="{{ route('recipes.print', $recipe) }}" target="_blank" class="text-gray-700 hover:underline">Imprimir</a>
                                            <form action="{{ route('recipes.destroy', $recipe) }}" method="POST" class="inline" onsubmit="return confirm('Excluir ficha técnica?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">Excluir</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Nenhuma ficha técnica cadastrada.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-bulk-select>

                <div class="mt-4">{{ $recipes->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
