<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cardápio</h2>
            <a href="{{ route('products.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Novo produto
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <x-flash-messages />

                    <x-bulk-select :action="route('products.bulk-destroy')" confirm="Excluir :count produto(s) do cardápio?">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <x-bulk-select-all />
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Foto</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produto</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preço</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">CMV</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ficha</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Disponível</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($products as $product)
                                    <tr>
                                        <x-bulk-select-item :id="$product->id" />
                                        <td class="px-4 py-3">
                                            @if ($product->image_url)
                                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-14 w-14 object-cover rounded-lg border border-gray-200">
                                            @else
                                                <div class="h-14 w-14 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-400 text-xs">Sem foto</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900">{{ $product->name }}</p>
                                            @if ($product->description)
                                                <p class="text-sm text-gray-500">{{ Str::limit($product->description, 60) }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $product->categories->pluck('name')->join(', ') ?: '—' }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                            @if ($product->hasVariants())
                                                <span>R$ {{ $product->priceLabel() }}</span>
                                                <p class="text-xs text-gray-500">{{ $product->variants->count() }} variações</p>
                                            @else
                                                R$ {{ number_format($product->price, 2, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            @if ($product->hasVariants())
                                                @foreach ($product->variants as $variant)
                                                    <div class="text-xs">
                                                        {{ $variant->label }}:
                                                        @if ($variant->recipe)
                                                            R$ {{ number_format($variant->foodCost(), 2, ',', '.') }}
                                                        @else
                                                            —
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @elseif ($product->recipe)
                                                R$ {{ number_format($product->foodCost(), 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            @if ($product->hasVariants())
                                                <div class="space-y-1">
                                                    @foreach ($product->variants as $variant)
                                                        <div class="text-xs">
                                                            <span class="font-medium">{{ $variant->label }}:</span>
                                                            @if ($variant->recipe)
                                                                <a href="{{ route('recipes.edit', $variant->recipe) }}" class="text-indigo-600 hover:underline">{{ $variant->recipe->name }}</a>
                                                            @else
                                                                <span class="text-amber-600">Sem ficha</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif ($product->recipe)
                                                <a href="{{ route('recipes.edit', $product->recipe) }}" class="text-indigo-600 hover:underline">{{ $product->recipe->name }}</a>
                                            @else
                                                <span class="text-amber-600">Sem vínculo</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $product->is_available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $product->is_available ? 'Sim' : 'Não' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right space-x-2">
                                            <a href="{{ route('products.edit', $product) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Editar</a>
                                            <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Excluir este produto?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Excluir</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">Nenhum produto cadastrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    </x-bulk-select>

                    <div class="mt-4">{{ $products->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
