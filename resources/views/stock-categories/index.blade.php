<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Categorias de estoque</h2>
            <a href="{{ route('stock-categories.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                Nova categoria
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <x-flash-messages />
                <p class="text-sm text-gray-500 mb-4">Organize itens como alimentos, limpeza, gás, embalagens, etc.</p>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Itens</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ordem</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($categories as $category)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                                <td class="px-4 py-3">{{ $category->ingredients_count }}</td>
                                <td class="px-4 py-3">{{ $category->sort_order }}</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <a href="{{ route('stock-categories.edit', $category) }}" class="text-indigo-600 hover:underline">Editar</a>
                                    <form action="{{ route('stock-categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('Excluir categoria?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Nenhuma categoria cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $categories->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
