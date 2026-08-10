<div class="rounded-lg bg-amber-50 border border-amber-100 p-4 text-sm text-amber-900">
    A foto exibida no cardápio digital vem da <strong>ficha técnica</strong> vinculada.
    @if (!empty($product?->recipe))
        <a href="{{ route('recipes.edit', $product->recipe) }}" class="text-indigo-600 hover:underline font-medium">Editar foto na ficha</a>
        @if ($product->recipe->image_url)
            <img src="{{ $product->recipe->image_url }}" alt="{{ $product->name }}" class="mt-3 h-32 w-32 object-cover rounded-lg border border-amber-200">
        @else
            <p class="mt-2 text-amber-800">Esta ficha ainda não tem foto cadastrada.</p>
        @endif
    @elseif (!empty($product) && $product->hasVariants())
        <p class="mt-1">Produtos com variações usam a ficha de cada tamanho abaixo.</p>
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

@php
    $initialVariants = old('variants');
    if ($initialVariants === null && !empty($product) && $product->hasVariants()) {
        $initialVariants = $product->variants->map(fn ($variant) => [
            'id' => $variant->id,
            'label' => $variant->label,
            'price' => (float) $variant->price,
            'recipe_id' => $variant->recipe_id,
            'is_available' => $variant->is_available,
        ])->values()->all();
    }
    $initialVariants = $initialVariants ?? [];
    $hasVariantsInitial = old('has_variants', !empty($product) && $product->hasVariants());
    $recipeOptions = ($recipes ?? collect())->map(fn ($recipe) => [
        'id' => $recipe->id,
        'name' => $recipe->name,
    ])->values();
@endphp

<div class="rounded-lg border border-gray-200 p-4 space-y-4"
    x-data="{
        hasVariants: @js((bool) $hasVariantsInitial),
        variants: @js($initialVariants),
        recipes: @js($recipeOptions),
        addVariant(label = '') {
            this.variants.push({ id: null, label, price: '', recipe_id: '', is_available: true });
        },
        removeVariant(index) {
            this.variants.splice(index, 1);
        },
        addPreset(label) {
            if (this.variants.some(v => v.label === label)) return;
            this.addVariant(label);
        }
    }">
    <div class="flex items-center gap-2">
        <input type="checkbox" name="has_variants" id="has_variants" value="1"
            x-model="hasVariants"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
        <label for="has_variants" class="text-sm font-medium text-gray-800">Este produto tem variações (tamanho P, M, G…)</label>
    </div>

    <div x-show="hasVariants" x-cloak class="space-y-4">
        <div class="flex flex-wrap gap-2">
            <span class="text-xs text-gray-500 self-center">Atalhos:</span>
            @foreach (['P', 'M', 'G'] as $size)
                <button type="button" @click="addPreset('{{ $size }}')"
                    class="rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                    + {{ $size }}
                </button>
            @endforeach
            <button type="button" @click="addVariant('')"
                class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                + Variação
            </button>
        </div>

        <template x-if="variants.length === 0">
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-100 rounded-md px-3 py-2">
                Adicione ao menos uma variação com tamanho, preço e ficha técnica.
            </p>
        </template>

        <div class="space-y-3">
            <template x-for="(variant, index) in variants" :key="index">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <input type="hidden" :name="`variants[${index}][id]`" x-model="variant.id">

                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tamanho / rótulo</label>
                        <input type="text" :name="`variants[${index}][label]`" x-model="variant.label" required
                            placeholder="Ex: P, M, G"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Preço (R$)</label>
                        <input type="number" step="0.01" min="0" :name="`variants[${index}][price]`" x-model="variant.price" required
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>

                    <div class="md:col-span-5">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Ficha técnica</label>
                        <select :name="`variants[${index}][recipe_id]`" x-model="variant.recipe_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Sem ficha</option>
                            <template x-for="recipe in recipes" :key="recipe.id">
                                <option :value="recipe.id" x-text="recipe.name"></option>
                            </template>
                        </select>
                    </div>

                    <div class="md:col-span-2 flex items-center gap-2">
                        <input type="hidden" :name="`variants[${index}][is_available]`" value="0">
                        <input type="checkbox" :name="`variants[${index}][is_available]`" value="1"
                            x-model="variant.is_available"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-xs text-gray-600">Disponível</span>
                    </div>

                    <div class="md:col-span-1 flex justify-end">
                        <button type="button" @click="removeVariant(index)"
                            class="text-red-600 hover:text-red-800 text-xs font-medium">
                            Remover
                        </button>
                    </div>
                </div>
            </template>
        </div>

        @error('variants') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @if ($errors->has('variants.*'))
            <p class="text-sm text-red-600">Verifique os campos de cada variação.</p>
        @endif

        <p class="text-xs text-gray-500">
            Cada variação pode ter preço e ficha técnica próprios.
            <a href="{{ route('recipes.index') }}" class="text-indigo-600 hover:underline">Gerenciar fichas</a>
        </p>
    </div>

    <div x-show="!hasVariants" x-cloak class="space-y-4">
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
            <p class="mt-1 text-xs text-gray-500">Vincule uma ficha para baixa automática de estoque.</p>
            @error('recipe_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="price" class="block text-sm font-medium text-gray-700">Preço de venda (R$)</label>
            <input type="number" step="0.01" min="0" name="price" id="price" value="{{ old('price', $product->price ?? '') }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

<div class="flex items-center gap-2">
    <input type="checkbox" name="is_available" id="is_available" value="1"
        @checked(old('is_available', $product->is_available ?? true)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
    <label for="is_available" class="text-sm text-gray-700">Disponível no cardápio</label>
</div>
