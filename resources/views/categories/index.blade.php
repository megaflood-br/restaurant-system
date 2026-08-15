<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Categorias</h2>
            <a href="{{ route('categories.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Nova categoria
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <x-flash-messages />

                    <x-bulk-select :action="route('categories.bulk-destroy')" confirm="Excluir :count categoria(s) selecionada(s)? Categorias com produtos serão ignoradas.">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <x-bulk-select-all />
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dias</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produtos</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse ($categories as $category)
                                        <tr>
                                            <x-bulk-select-item :id="$category->id" />
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-gray-900">{{ $category->name }}</p>
                                                @if ($category->description)
                                                    <p class="text-sm text-gray-500">{{ Str::limit($category->description, 60) }}</p>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $category->availableDaysLabel() }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $category->products_count }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                    {{ $category->is_active ? 'Ativa' : 'Inativa' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right space-x-2">
                                                <a href="{{ route('categories.edit', $category) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Editar</a>
                                                <form action="{{ route('categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('Excluir esta categoria?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Excluir</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">Nenhuma categoria cadastrada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-bulk-select>

                    <div class="mt-4">{{ $categories->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
