<div>
    <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="name" id="name" value="{{ old('name', $category->name ?? '') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
</div>
<div>
    <label for="sort_order" class="block text-sm font-medium text-gray-700">Ordem de exibição</label>
    <input type="number" name="sort_order" id="sort_order" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
</div>
<div class="flex items-center gap-2">
    <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $category->is_active ?? true)) class="rounded border-gray-300 text-indigo-600">
    <label for="is_active" class="text-sm text-gray-700">Ativa</label>
</div>
