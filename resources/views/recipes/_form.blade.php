@php
    $initialRecipe = old('recipe', $recipeLines ?? [['ingredient_id' => '', 'quantity' => '']]);
    $yieldPortions = (int) old('yield_portions', $recipe->yield_portions ?? 1);
@endphp

<div x-data="recipeForm(@js($initialRecipe), @js($ingredientMeta), @js($yieldPortions))" class="space-y-6">
    <div>
        <label for="image" class="block text-sm font-medium text-gray-700">Foto</label>
        @if (!empty($recipe?->image_url))
            <div class="mt-2 mb-3">
                <img src="{{ $recipe->image_url }}" alt="{{ $recipe->name }}" class="h-32 w-32 object-cover rounded-lg border border-gray-200">
            </div>
            <div class="flex items-center gap-2 mb-2">
                <input type="checkbox" name="remove_image" id="remove_image" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <label for="remove_image" class="text-sm text-gray-700">Remover foto atual</label>
            </div>
        @endif
        <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp"
            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
        @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nome da ficha</label>
            <input type="text" name="name" id="name" value="{{ old('name', $recipe->name ?? '') }}" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="yield_portions" class="block text-sm font-medium text-gray-700">Rendimento (porções)</label>
            <input type="number" name="yield_portions" id="yield_portions" min="1" max="9999" x-model.number="yieldPortions" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('yield_portions') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">Descrição</label>
        <textarea name="description" id="description" rows="2"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $recipe->description ?? '') }}</textarea>
        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="product_id" class="block text-sm font-medium text-gray-700">Vincular ao cardápio (opcional)</label>
        <select name="product_id" id="product_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Sem vínculo</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected(old('product_id', $recipe->product_id ?? '') == $product->id)>
                    {{ $product->name }} @if($product->category) ({{ $product->category->name }}) @endif
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">Ao vincular, o estoque baixa automaticamente quando este item for vendido.</p>
        @error('product_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="preparation_method" class="block text-sm font-medium text-gray-700">Modo de preparo</label>
        <textarea name="preparation_method" id="preparation_method" rows="6"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            placeholder="Descreva o passo a passo da preparação...">{{ old('preparation_method', $recipe->preparation_method ?? '') }}</textarea>
        @error('preparation_method') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center gap-2">
        <input type="checkbox" name="is_active" id="is_active" value="1"
            @checked(old('is_active', $recipe->is_active ?? true)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
        <label for="is_active" class="text-sm text-gray-700">Ficha ativa</label>
    </div>

    <div class="border-t border-gray-200 pt-6 space-y-4">
        <div>
            <h3 class="text-lg font-medium text-gray-900">Ingredientes</h3>
            <p class="text-sm text-gray-500 mt-1">Quantidades para a receita completa. Pesos em gramas, líquidos em ml, demais em unidades.</p>
        </div>

        <template x-for="(line, index) in lines" :key="index">
            <div class="flex flex-wrap items-end gap-3 p-4 bg-gray-50 rounded-lg">
                <div class="flex-1 min-w-[220px]">
                    <label class="block text-sm font-medium text-gray-700" x-text="'Ingrediente ' + (index + 1)"></label>
                    <select
                        :name="'recipe[' + index + '][ingredient_id]'"
                        x-model="line.ingredient_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                        <option value="">Selecione...</option>
                        @foreach ($stockItems as $groupName => $items)
                            <optgroup label="{{ $groupName }}">
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="w-32">
                    <label class="block text-sm font-medium text-gray-700">
                        Qtd.
                        <span class="text-gray-500 font-normal" x-show="line.ingredient_id" x-text="'(' + unitLabel(line.ingredient_id) + ')'"></span>
                    </label>
                    <input
                        type="number"
                        step="any"
                        min="0.01"
                        :name="'recipe[' + index + '][quantity]'"
                        x-model="line.quantity"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                </div>
                <div class="w-28 text-right">
                    <p class="text-xs text-gray-500">Custo</p>
                    <p class="text-sm font-semibold text-gray-900" x-text="formatMoney(lineCost(line))"></p>
                </div>
                <button type="button" @click="removeLine(index)" class="px-3 py-2 text-sm text-red-600 hover:text-red-800">
                    Remover
                </button>
            </div>
        </template>

        <button type="button" @click="addLine()" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
            + Adicionar ingrediente
        </button>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-gray-200">
            <div class="rounded-lg bg-indigo-50 p-4">
                <p class="text-xs uppercase text-indigo-700 font-semibold">Custo total</p>
                <p class="text-xl font-bold text-indigo-900" x-text="formatMoney(totalCost())"></p>
            </div>
            <div class="rounded-lg bg-green-50 p-4">
                <p class="text-xs uppercase text-green-700 font-semibold">Custo / porção</p>
                <p class="text-xl font-bold text-green-900" x-text="formatMoney(costPerPortion())"></p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4">
                <p class="text-xs uppercase text-gray-600 font-semibold">Rendimento</p>
                <p class="text-xl font-bold text-gray-900"><span x-text="yieldPortions"></span> porções</p>
            </div>
        </div>
    </div>
</div>
