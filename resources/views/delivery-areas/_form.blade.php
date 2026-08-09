<div>
    <label for="name" class="block text-sm font-medium text-gray-700">Nome da faixa</label>
    <input type="text" name="name" id="name" value="{{ old('name', $deliveryArea->name ?? '') }}" required
        placeholder="Ex.: Até 3 km"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('name')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label for="min_km" class="block text-sm font-medium text-gray-700">Distância mínima (km)</label>
        <input type="number" name="min_km" id="min_km" step="0.1" min="0" value="{{ old('min_km', isset($deliveryArea) ? number_format($deliveryArea->min_km, 1, '.', '') : '0') }}" required
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('min_km')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="max_km" class="block text-sm font-medium text-gray-700">Distância máxima (km)</label>
        <input type="number" name="max_km" id="max_km" step="0.1" min="0" value="{{ old('max_km', isset($deliveryArea) && $deliveryArea->max_km !== null ? number_format($deliveryArea->max_km, 1, '.', '') : '') }}"
            placeholder="Vazio = sem limite superior"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('max_km')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div>
    <label for="fee" class="block text-sm font-medium text-gray-700">Taxa de entrega (R$)</label>
    <input type="number" name="fee" id="fee" step="0.01" min="0" value="{{ old('fee', isset($deliveryArea) ? number_format($deliveryArea->fee, 2, '.', '') : '0.00') }}" required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('fee')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="sort_order" class="block text-sm font-medium text-gray-700">Ordem de exibição</label>
    <input type="number" name="sort_order" id="sort_order" min="0" value="{{ old('sort_order', $deliveryArea->sort_order ?? 0) }}"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    <p class="mt-1 text-xs text-gray-500">Menor número aparece primeiro no checkout.</p>
    @error('sort_order')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="flex items-center gap-2">
    <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $deliveryArea->is_active ?? true))
        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
    <label for="is_active" class="text-sm text-gray-700">Ativa</label>
</div>
