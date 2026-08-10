@props([
    'products',
    'customers',
    'selectedCustomer' => null,
    'comandaNumber' => null,
    'modal' => false,
])

<form method="POST" action="{{ route('orders.store') }}"
    x-data="{
        type: '{{ old('type', $selectedCustomer ? 'delivery' : 'dine_in') }}',
        customerId: '{{ old('customer_id', $selectedCustomer?->id ?? '') }}',
        items: [{ variantId: '' }],
        addItem() { this.items.push({ variantId: '' }); },
        removeItem(index) { this.items.splice(index, 1); },
        syncVariant(index, event) {
            const option = event.target.selectedOptions[0];
            this.items[index].variantId = option?.dataset?.variantId || '';
        }
    }"
    class="space-y-6">
    @csrf

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="type" class="block text-sm font-medium text-gray-700">Tipo de pedido</label>
            <select name="type" id="type" x-model="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="dine_in">Salão</option>
                <option value="delivery">Delivery</option>
                <option value="takeaway">Retirada</option>
            </select>
        </div>
        <div x-show="type === 'dine_in'">
            <label for="comanda_number" class="block text-sm font-medium text-gray-700">Número da comanda</label>
            <input type="number" min="1" max="999" name="comanda_number" id="comanda_number"
                value="{{ old('comanda_number', $comandaNumber ?? '') }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div x-show="type !== 'dine_in'" class="md:col-span-2">
            <label for="customer_id" class="block text-sm font-medium text-gray-700">Cliente cadastrado</label>
            <select name="customer_id" id="customer_id" x-model="customerId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Selecionar cliente ou preencher manualmente...</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(old('customer_id', $selectedCustomer?->id) == $customer->id)>
                        {{ $customer->name }}@if($customer->phone) — {{ $customer->phone }}@endif
                    </option>
                @endforeach
            </select>
        </div>
        <div x-show="type !== 'dine_in' && !customerId">
            <label for="customer_name" class="block text-sm font-medium text-gray-700">Nome do cliente</label>
            <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div x-show="type !== 'dine_in' && !customerId">
            <label for="customer_phone" class="block text-sm font-medium text-gray-700">Telefone</label>
            <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div>
        <label for="notes" class="block text-sm font-medium text-gray-700">Observações do pedido</label>
        <textarea name="notes" id="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
    </div>

    <div>
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-lg font-semibold text-gray-800">Itens do pedido</h3>
            <button type="button" @click="addItem()" class="text-sm text-indigo-600 hover:text-indigo-800">+ Adicionar item</button>
        </div>

        <template x-for="(item, index) in items" :key="index">
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 mb-3 p-4 bg-gray-50 rounded-lg">
                <div class="sm:col-span-6">
                    <label class="block text-sm font-medium text-gray-700">Produto</label>
                    <select :name="`items[${index}][product_id]`" required @change="syncVariant(index, $event)"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Selecione...</option>
                        @foreach ($products as $product)
                            @if ($product->hasVariants())
                                @foreach ($product->variants as $variant)
                                    <option value="{{ $product->id }}" data-variant-id="{{ $variant->id }}">
                                        {{ $product->name }} ({{ $variant->label }}) — R$ {{ number_format($variant->price, 2, ',', '.') }}
                                    </option>
                                @endforeach
                            @else
                                <option value="{{ $product->id }}" data-variant-id="">
                                    {{ $product->name }} — R$ {{ number_format($product->price, 2, ',', '.') }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    <input type="hidden" :name="`items[${index}][variant_id]`" x-model="item.variantId">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Qtd</label>
                    <input type="number" min="1" :name="`items[${index}][quantity]`" value="1" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700">Obs.</label>
                    <input type="text" :name="`items[${index}][notes]`" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="sm:col-span-1 flex items-end">
                    <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="text-red-600 hover:text-red-800 text-sm pb-2">Remover</button>
                </div>
            </div>
        </template>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
            Criar pedido
        </button>
        @if ($modal)
            <button type="button" @click="$dispatch('close-modal', 'new-order')" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                Cancelar
            </button>
        @else
            <a href="{{ route('orders.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                Cancelar
            </a>
        @endif
    </div>
</form>
