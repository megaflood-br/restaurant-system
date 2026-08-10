@props([
    'categories',
    'comanda' => null,
    'addUrl',
    'storeUrl',
    'summaryUrl' => null,
    'returnUrl' => null,
    'accent' => 'indigo',
    'autoOpen' => false,
    'pickerId' => 'default',
])

@php
    $accentClasses = match ($accent) {
        'orange' => [
            'btn' => 'bg-orange-500 hover:bg-orange-600',
            'tab_active' => 'bg-orange-500 text-white',
            'price' => 'text-orange-600',
        ],
        default => [
            'btn' => 'bg-indigo-600 hover:bg-indigo-700',
            'tab_active' => 'bg-indigo-600 text-white',
            'price' => 'text-indigo-600',
        ],
    };
    $productsJson = $categories->flatMap(fn ($cat) => $cat->products->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'price' => (float) $p->displayPrice(),
        'price_label' => $p->priceLabel(),
        'has_variants' => $p->hasVariants(),
        'variants' => $p->hasVariants()
            ? $p->variants->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->label,
                'price' => (float) $v->price,
                'price_label' => number_format($v->price, 2, ',', '.'),
            ])->values()->all()
            : [],
        'image_url' => $p->image_url,
        'category_id' => $cat->id,
    ]))->values();
    $pickerConfig = [
        'comanda' => $comanda,
        'products' => $productsJson,
        'activeCategory' => $categories->first()?->id,
        'addUrl' => $addUrl,
        'storeUrl' => $storeUrl,
        'summaryUrl' => $summaryUrl,
        'returnUrl' => $returnUrl,
        'autoOpen' => $autoOpen,
        'csrf' => csrf_token(),
        'pickerId' => $pickerId,
    ];
@endphp

<div
    x-data="productPickerModal(@js($pickerConfig))"
    @open-product-picker.window="if ($event.detail === pickerId) openPicker()"
    x-cloak
>
    {{-- Lista de produtos --}}
    <div x-show="open" x-cloak
        class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center bg-black/50 p-0 sm:p-4"
        @keydown.escape.window="open = false"
        @click.self="open = false">
        <div class="bg-white w-full sm:max-w-lg sm:rounded-2xl shadow-xl max-h-[92vh] flex flex-col"
            @click.stop>
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
                <div>
                    <h3 class="font-bold text-gray-900">Adicionar produtos</h3>
                    @if ($comanda)
                        <p class="text-xs text-gray-500">Comanda {{ str_pad((string) $comanda, 3, '0', STR_PAD_LEFT) }}</p>
                    @endif
                </div>
                <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none p-1">&times;</button>
            </div>

            <div class="px-4 py-2 border-b border-gray-100 overflow-x-auto shrink-0">
                <div class="flex gap-2">
                    @foreach ($categories as $category)
                        <button type="button" @click="activeCategory = {{ $category->id }}"
                            :class="activeCategory === {{ $category->id }} ? '{{ $accentClasses['tab_active'] }}' : 'bg-gray-100 text-gray-700'"
                            class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold transition">
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="overflow-y-auto flex-1 px-4 py-3 space-y-2 min-h-[200px]">
                @foreach ($categories as $category)
                    @foreach ($category->products as $product)
                        <div x-show="activeCategory === {{ $category->id }}" x-cloak
                            class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                            @if ($product->image_url)
                                <img src="{{ $product->image_url }}" alt="" class="w-14 h-14 rounded-lg object-cover shrink-0">
                            @else
                                <div class="w-14 h-14 rounded-lg bg-gray-200 shrink-0"></div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-gray-900 truncate">{{ $product->name }}</p>
                                <p class="text-sm font-bold {{ $accentClasses['price'] }}">
                                    @if ($product->hasVariants())
                                        R$ {{ $product->priceLabel() }}
                                    @else
                                        R$ {{ number_format($product->price, 2, ',', '.') }}
                                    @endif
                                </p>
                            </div>
                            <button type="button" @click="openProduct({{ $product->id }})"
                                class="shrink-0 rounded-xl {{ $accentClasses['btn'] }} text-white text-xs font-bold px-4 py-2.5">
                                Adicionar
                            </button>
                        </div>
                    @endforeach
                @endforeach
            </div>

            <div class="border-t border-gray-100 px-4 py-3 shrink-0 bg-white sm:rounded-b-2xl">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm text-gray-600"><span x-text="cartCount">0</span> item(ns) no pedido</span>
                    <span class="font-bold {{ $accentClasses['price'] }}" x-text="formatMoney(cartTotal)">R$ 0,00</span>
                </div>
                <button type="button" @click="submitOrder()" :disabled="cartCount === 0 || submitting"
                    class="w-full rounded-xl {{ $accentClasses['btn'] }} disabled:opacity-50 text-white font-bold py-3.5 transition">
                    <span x-show="!submitting">Enviar à cozinha</span>
                    <span x-show="submitting">Enviando...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Quantidade / observação --}}
    <div x-show="productOpen" x-cloak
        class="fixed inset-0 z-[110] flex items-end sm:items-center justify-center bg-black/50 p-0 sm:p-4"
        @keydown.escape.window="productOpen = false">
        <div class="bg-white w-full sm:max-w-sm sm:rounded-2xl shadow-xl p-5 space-y-4" @click.stop>
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-lg font-bold" x-text="selectedProduct?.name"></h3>
                    <p class="font-bold {{ $accentClasses['price'] }} mt-1" x-text="selectedProduct ? 'R$ ' + selectedPriceLabel() : ''"></p>
                </div>
                <button type="button" @click="productOpen = false" class="text-gray-400 text-2xl leading-none">&times;</button>
            </div>
            <div x-show="selectedProduct?.has_variants" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tamanho</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="variant in (selectedProduct?.variants || [])" :key="variant.id">
                        <button type="button"
                            @click="selectedVariantId = variant.id"
                            :class="selectedVariantId === variant.id ? '{{ $accentClasses['tab_active'] }}' : 'bg-gray-100 text-gray-700'"
                            class="rounded-full px-3 py-1.5 text-xs font-semibold transition">
                            <span x-text="variant.label"></span>
                            <span class="opacity-80" x-text="' · R$ ' + variant.price_label"></span>
                        </button>
                    </template>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Quantidade</label>
                <div class="flex items-center justify-center gap-4">
                    <button type="button" @click="qty = Math.max(1, qty - 1)" class="w-12 h-12 rounded-xl bg-gray-100 font-bold text-xl">−</button>
                    <input type="number" x-model.number="qty" min="1" max="99" class="w-16 text-center rounded-xl border-gray-300 font-bold text-xl">
                    <button type="button" @click="qty = Math.min(99, qty + 1)" class="w-12 h-12 rounded-xl bg-gray-100 font-bold text-xl">+</button>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observação</label>
                <input type="text" x-model="notes" placeholder="Ex: sem cebola"
                    class="w-full rounded-xl border-gray-300 text-sm py-2.5">
            </div>
            <button type="button" @click="addProduct()" :disabled="adding"
                class="w-full rounded-xl {{ $accentClasses['btn'] }} disabled:opacity-50 text-white font-bold py-3.5">
                <span x-show="!adding">Confirmar</span>
                <span x-show="adding">Adicionando...</span>
            </button>
        </div>
    </div>
</div>
