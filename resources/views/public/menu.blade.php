@extends('layouts.menu')

@section('hero')
    @include('public.partials.menu-hero')
@endsection

@section('content')
    <div x-data="{ activeCategory: '{{ $categories->first()?->id }}' }">
        {{-- Category tabs --}}
        <div class="sticky top-0 z-30 bg-gray-50/95 backdrop-blur border-b border-gray-100 px-4 py-3">
            <div class="flex gap-2 overflow-x-auto hide-scrollbar">
                @foreach ($categories as $category)
                    <button type="button"
                        @click="activeCategory = '{{ $category->id }}'; document.getElementById('cat-{{ $category->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        :class="activeCategory === '{{ $category->id }}' ? 'menu-tab-active' : 'bg-white text-gray-700 border border-gray-200'"
                        class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition">
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Products --}}
        <div class="px-4 py-4 space-y-8">
            @foreach ($categories as $category)
                <section id="cat-{{ $category->id }}">
                    <h2 class="text-xl font-bold text-gray-900 mb-1">{{ $category->name }}</h2>
                    @if ($category->description)
                        <p class="text-sm text-gray-500 mb-4">{{ $category->description }}</p>
                    @endif

                    <div class="space-y-4">
                        @foreach ($category->products as $product)
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex"
                                x-data="{ qty: 1, notes: '', open: false }">
                                @if ($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                                        class="w-28 h-28 object-cover shrink-0">
                                @else
                                    <div class="w-28 h-28 bg-gray-100 shrink-0 flex items-center justify-center text-gray-300 text-xs">Sem foto</div>
                                @endif

                                <div class="flex-1 p-3 flex flex-col justify-between min-w-0">
                                    <div>
                                        <h3 class="font-semibold text-gray-900 leading-tight">{{ $product->name }}</h3>
                                        @if ($product->description)
                                            <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $product->description }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between mt-2 gap-2">
                                        <span class="menu-text font-bold">R$ {{ number_format($product->price, 2, ',', '.') }}</span>
                                        <button type="button" @click="open = true"
                                            class="rounded-full menu-bg menu-bg-hover text-white text-xs font-semibold px-4 py-2 transition">
                                            Adicionar
                                        </button>
                                    </div>
                                </div>

                                {{-- Modal --}}
                                <template x-teleport="body">
                                <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-4 bg-black/50"
                                    @keydown.escape.window="open = false">
                                    <div @click.away="open = false" class="bg-white rounded-2xl w-full max-w-sm overflow-hidden shadow-xl">
                                        @if ($product->image_url)
                                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-48 object-cover">
                                        @endif
                                        <div class="p-5 space-y-4">
                                            <div>
                                                <h3 class="text-lg font-bold">{{ $product->name }}</h3>
                                                @if ($product->description)
                                                    <p class="text-sm text-gray-500 mt-1">{{ $product->description }}</p>
                                                @endif
                                                <p class="menu-text font-bold text-lg mt-2">R$ {{ number_format($product->price, 2, ',', '.') }}</p>
                                            </div>

                                            <form method="POST" action="{{ route('public.cart.add') }}" class="space-y-4"
                                                @submit.prevent="
                                                    fetch(@js(route('public.cart.add')), {
                                                        method: 'POST',
                                                        body: new FormData($event.target),
                                                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                                    }).then(r => r.json()).then(() => {
                                                        open = false;
                                                        window.location.reload();
                                                    }).catch(() => alert('Erro ao adicionar'))
                                                ">
                                                @csrf
                                                <input type="hidden" name="product_id" value="{{ $product->id }}">

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Quantidade</label>
                                                    <div class="flex items-center gap-4">
                                                        <button type="button" @click="qty = Math.max(1, qty - 1)" class="w-10 h-10 rounded-full bg-gray-100 font-bold text-lg">−</button>
                                                        <input type="number" name="quantity" x-model="qty" min="1" max="99" class="w-16 text-center rounded-lg border-gray-300 font-bold text-lg">
                                                        <button type="button" @click="qty = Math.min(99, qty + 1)" class="w-10 h-10 rounded-full bg-gray-100 font-bold text-lg">+</button>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Observações (opcional)</label>
                                                    <input type="text" name="notes" x-model="notes" placeholder="Ex: sem cebola"
                                                        class="w-full rounded-lg border-gray-300 text-sm">
                                                </div>

                                                <button type="submit" class="w-full rounded-xl menu-bg menu-bg-hover text-white font-semibold py-3 transition">
                                                    Adicionar — R$ {{ number_format($product->price, 2, ',', '.') }}
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
@endsection
