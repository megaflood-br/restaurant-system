@extends('layouts.waiter')

@section('content')
    @if (!($comandaNumber ?? null))
        <div class="px-4 py-4">
            <div class="bg-white rounded-2xl border border-indigo-100 p-4 shadow-sm">
                <h2 class="font-bold text-gray-900 mb-3">Informe a comanda do cliente</h2>
                <form method="POST" action="{{ route('waiter.comanda') }}" class="flex gap-2 mb-4">
                    @csrf
                    <input type="number" name="comanda_number" min="1" max="999" required placeholder="Nº"
                        class="flex-1 rounded-xl border-gray-300 text-center text-2xl font-bold focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit" class="rounded-xl bg-indigo-600 text-white font-bold px-6 hover:bg-indigo-700 transition">
                        OK
                    </button>
                </form>
                <p class="text-xs text-gray-500 mb-2">Atalho — comandas livres:</p>
                <div class="grid grid-cols-5 gap-2">
                    @for ($i = 1; $i <= 20; $i++)
                        <form method="POST" action="{{ route('waiter.comanda') }}">
                            @csrf
                            <input type="hidden" name="comanda_number" value="{{ $i }}">
                            <button type="submit" class="w-full aspect-square rounded-xl bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 font-bold text-sm transition">
                                {{ str_pad((string) $i, 3, '0', STR_PAD_LEFT) }}
                            </button>
                        </form>
                    @endfor
                </div>
            </div>
        </div>
    @endif

    <div x-data="{ activeCategory: '{{ $categories->first()?->id }}' }">
        <div class="sticky top-[120px] z-30 bg-slate-50/95 backdrop-blur border-b border-gray-100 px-4 py-3">
            <div class="flex gap-2 overflow-x-auto hide-scrollbar">
                @foreach ($categories as $category)
                    <button type="button"
                        @click="activeCategory = '{{ $category->id }}'; document.getElementById('cat-{{ $category->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        :class="activeCategory === '{{ $category->id }}' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-200'"
                        class="shrink-0 rounded-full px-4 py-2.5 text-sm font-semibold transition">
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="px-4 py-4 space-y-8 pb-4">
            @foreach ($categories as $category)
                <section id="cat-{{ $category->id }}">
                    <h2 class="text-xl font-bold text-gray-900 mb-3">{{ $category->name }}</h2>

                    <div class="space-y-3">
                        @foreach ($category->products as $product)
                            @php
                                $variantPayload = $product->hasVariants()
                                    ? $product->variants->map(fn ($variant) => [
                                        'id' => $variant->id,
                                        'label' => $variant->label,
                                        'price' => (float) $variant->price,
                                        'price_label' => number_format($variant->price, 2, ',', '.'),
                                    ])->values()
                                    : collect();
                            @endphp
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex"
                                x-data="{
                                    qty: 1,
                                    notes: '',
                                    open: false,
                                    hasVariants: @js($product->hasVariants()),
                                    variants: @js($variantPayload),
                                    selectedVariantId: @js($product->hasVariants() ? $product->variants->first()?->id : null),
                                    selectedPriceLabel() {
                                        if (!this.hasVariants) return @js(number_format($product->price, 2, ',', '.'));
                                        const variant = this.variants.find(v => v.id === this.selectedVariantId);
                                        return variant ? variant.price_label : '0,00';
                                    }
                                }">
                                @if ($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                                        class="w-24 h-24 rounded-l-2xl object-cover shrink-0">
                                @else
                                    <div class="w-24 h-24 bg-gray-100 shrink-0 flex items-center justify-center text-gray-300 text-xs rounded-l-2xl">Sem foto</div>
                                @endif

                                <div class="flex-1 p-3 flex flex-col justify-between min-w-0">
                                    <div>
                                        <h3 class="font-semibold text-gray-900 leading-tight">{{ $product->name }}</h3>
                                        <p class="text-indigo-600 font-bold mt-1">
                                            @if ($product->hasVariants())
                                                R$ {{ $product->priceLabel() }}
                                            @else
                                                R$ {{ number_format($product->price, 2, ',', '.') }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 mt-2 justify-end">
                                        <button type="button" @click="open = true"
                                            class="rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-5 py-2.5 transition active:scale-95">
                                            Adicionar
                                        </button>
                                    </div>
                                </div>

                                <template x-teleport="body">
                                    <div x-show="open" x-cloak
                                        class="fixed inset-0 z-[100] flex items-end justify-center bg-black/50"
                                        @keydown.escape.window="open = false">
                                        <div class="bg-white rounded-t-3xl w-full max-w-lg shadow-xl max-h-[85vh] overflow-y-auto"
                                            @click.outside="open = false">
                                            @if ($product->image_url)
                                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-40 object-cover">
                                            @endif
                                            <div class="p-5 space-y-4">
                                                <div class="flex justify-between items-start">
                                                    <div>
                                                        <h3 class="text-xl font-bold">{{ $product->name }}</h3>
                                                        <p class="text-indigo-600 font-bold text-lg mt-1" x-text="'R$ ' + selectedPriceLabel()">
                                                            R$ {{ $product->hasVariants() ? $product->priceLabel() : number_format($product->price, 2, ',', '.') }}
                                                        </p>
                                                    </div>
                                                    <button type="button" @click="open = false" class="text-gray-400 text-2xl leading-none p-1">&times;</button>
                                                </div>

                                                <form method="POST" action="{{ route('waiter.cart.add') }}" class="space-y-4"
                                                    @submit.prevent="
                                                        if (hasVariants && !selectedVariantId) { alert('Selecione o tamanho.'); return; }
                                                        fetch(@js(route('waiter.cart.add')), {
                                                            method: 'POST',
                                                            body: new FormData($event.target),
                                                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                                        }).then(r => r.json()).then(() => { open = false; window.location.reload(); }).catch(() => alert('Erro ao adicionar'))
                                                    ">
                                                    @csrf
                                                    @if ($comandaNumber ?? null)
                                                        <input type="hidden" name="comanda_number" value="{{ $comandaNumber }}">
                                                    @endif
                                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                                    <input type="hidden" name="variant_id" x-bind:value="hasVariants ? selectedVariantId : ''">

                                                    <div x-show="hasVariants" x-cloak>
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">Tamanho</label>
                                                        <div class="flex flex-wrap gap-2">
                                                            <template x-for="variant in variants" :key="variant.id">
                                                                <button type="button"
                                                                    @click="selectedVariantId = variant.id"
                                                                    :class="selectedVariantId === variant.id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'"
                                                                    class="rounded-full px-4 py-2 text-sm font-semibold transition">
                                                                    <span x-text="variant.label"></span>
                                                                    <span class="opacity-80" x-text="' · R$ ' + variant.price_label"></span>
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantidade</label>
                                                        <div class="flex items-center justify-center gap-6">
                                                            <button type="button" @click="qty = Math.max(1, qty - 1)"
                                                                class="w-14 h-14 rounded-2xl bg-gray-100 font-bold text-2xl active:bg-gray-200">−</button>
                                                            <input type="number" name="quantity" x-model="qty" min="1" max="99"
                                                                class="w-16 text-center rounded-xl border-gray-300 font-bold text-2xl">
                                                            <button type="button" @click="qty = Math.min(99, qty + 1)"
                                                                class="w-14 h-14 rounded-2xl bg-gray-100 font-bold text-2xl active:bg-gray-200">+</button>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Observação</label>
                                                        <input type="text" name="notes" x-model="notes" placeholder="Ex: sem cebola, bem passado"
                                                            class="w-full rounded-xl border-gray-300 text-base py-3">
                                                    </div>

                                                    <button type="submit"
                                                        class="w-full rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 text-lg transition active:scale-[0.98]">
                                                        Adicionar ao pedido
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    @if (($comandaNumber ?? null) && $categories->isNotEmpty())
        <x-product-picker-modal
            :categories="$categories"
            :comanda="$comandaNumber"
            :add-url="route('waiter.cart.add')"
            :store-url="route('waiter.store')"
            :summary-url="route('waiter.cart.summary')"
            picker-id="waiter-menu"
        />
    @endif
@endsection
