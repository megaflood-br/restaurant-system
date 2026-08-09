<div class="rounded-lg bg-amber-50 border border-amber-100 p-4 text-sm text-amber-900">
    A foto exibida no cardápio digital vem da <strong>ficha técnica</strong> vinculada.
    @if (!empty($product?->recipe))
        <a href="{{ route('recipes.edit', $product->recipe) }}" class="text-indigo-600 hover:underline font-medium">Editar foto na ficha</a>
        @if ($product->recipe->image_url)
            <img src="{{ $product->recipe->image_url }}" alt="{{ $product->name }}" class="mt-3 h-32 w-32 object-cover rounded-lg border border-amber-200">
        @else
            <p class="mt-2 text-amber-800">Esta ficha ainda não tem foto cadastrada.</p>
        @endif
    @elseif (!empty($product))
        <a href="{{ route('recipes.index') }}" class="text-indigo-600 hover:underline font-medium">Vincule uma ficha técnica</a> para definir a foto.
    @endif
</div>

<div>
    <label for="category_id" class="block text-sm font-medium text-gray-700">Categoria</label>
    <select name="category_id" id="category_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">Selecione...</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? '') == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
    @error('category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="name" id="name" value="{{ old('name', $product->name ?? '') }}" required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="description" class="block text-sm font-medium text-gray-700">Descrição</label>
    <textarea name="description" id="description" rows="3"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $product->description ?? '') }}</textarea>
    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="recipe_id" class="block text-sm font-medium text-gray-700">Ficha técnica (opcional)</label>
    <select name="recipe_id" id="recipe_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">Sem ficha vinculada</option>
        @foreach ($recipes ?? [] as $recipeOption)
            <option value="{{ $recipeOption->id }}" @selected(old('recipe_id', $product->recipe_id ?? '') == $recipeOption->id)>
                {{ $recipeOption->name }}
            </option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">Vincule uma ficha para baixa automática de estoque. <a href="{{ route('recipes.index') }}" class="text-indigo-600 hover:underline">Gerenciar fichas</a></p>
    @error('recipe_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="price" class="block text-sm font-medium text-gray-700">Preço de venda (R$)</label>
    <input type="number" step="0.01" min="0" name="price" id="price" value="{{ old('price', $product->price ?? '') }}" required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="flex items-center gap-2">
    <input type="checkbox" name="is_available" id="is_available" value="1"
        @checked(old('is_available', $product->is_available ?? true)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
    <label for="is_available" class="text-sm text-gray-700">Disponível no cardápio</label>
</div>
