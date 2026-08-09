<div>
    <label for="stock_category_id" class="block text-sm font-medium text-gray-700">Categoria de estoque</label>
    <select name="stock_category_id" id="stock_category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">Sem categoria</option>
        @foreach ($stockCategories ?? [] as $category)
            <option value="{{ $category->id }}" @selected(old('stock_category_id', $ingredient->stock_category_id ?? '') == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
    @error('stock_category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="name" id="name" value="{{ old('name', $ingredient->name ?? '') }}" required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div x-data="{ unit: @js(old('unit', $ingredient->unit ?? 'kg')) }">
    <div>
        <label for="unit" class="block text-sm font-medium text-gray-700">Unidade de estoque</label>
        <select name="unit" id="unit" x-model="unit" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (['kg', 'g', 'L', 'ml', 'un'] as $unitOption)
                <option value="{{ $unitOption }}">{{ $unitOption }}</option>
            @endforeach
        </select>
        @error('unit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="package_size" class="block text-sm font-medium text-gray-700">
                Tamanho do pacote
                <span class="text-gray-500 font-normal" x-text="'(' + ({ kg: 'kg', g: 'kg', L: 'L', ml: 'L', un: 'un' }[unit] || 'un') + ')'"></span>
            </label>
            <input type="number" step="0.001" min="0.001" name="package_size" id="package_size"
                value="{{ old('package_size', $ingredient->package_size ?? '') }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Ex.: 5">
            <p class="mt-1 text-xs text-gray-500">Peso em kg ou volume em litros do pacote comprado.</p>
            @error('package_size') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="cost_price" class="block text-sm font-medium text-gray-700">Preço de custo do pacote (R$)</label>
            <input type="number" step="0.01" min="0" name="cost_price" id="cost_price"
                value="{{ old('cost_price', $ingredient->cost_price ?? '') }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Ex.: 49,90">
            @error('cost_price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

@if (isset($ingredient) && $ingredient->unitCost() > 0)
    <div class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-sm text-gray-700">
        Custo unitário calculado: <strong>{{ $ingredient->formattedUnitCost() }}</strong>
    </div>
@endif

@if (!isset($ingredient))
    <div>
        <label for="current_stock" class="block text-sm font-medium text-gray-700">Estoque inicial</label>
        <input type="number" step="0.01" min="0" name="current_stock" id="current_stock" value="{{ old('current_stock', 0) }}" required
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('current_stock') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
@endif

<div>
    <label for="minimum_stock" class="block text-sm font-medium text-gray-700">Estoque mínimo</label>
    <input type="number" step="0.01" min="0" name="minimum_stock" id="minimum_stock" value="{{ old('minimum_stock', $ingredient->minimum_stock ?? 0) }}" required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('minimum_stock') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
