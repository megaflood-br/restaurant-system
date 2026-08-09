<div>
    <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="name" id="name" value="{{ old('name', $category->name ?? '') }}" required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="description" class="block text-sm font-medium text-gray-700">Descrição</label>
    <textarea name="description" id="description" rows="3"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $category->description ?? '') }}</textarea>
    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="flex items-center gap-2">
    <input type="checkbox" name="is_active" id="is_active" value="1"
        @checked(old('is_active', $category->is_active ?? true)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
    <label for="is_active" class="text-sm text-gray-700">Categoria ativa</label>
</div>
