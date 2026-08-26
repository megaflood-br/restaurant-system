@props([
    'products',
    'selectedCustomer' => null,
    'comandaNumber' => null,
    'modal' => false,
])

@php
    $initialCustomerLabel = $selectedCustomer
        ? $selectedCustomer->name.($selectedCustomer->phone ? ' — '.$selectedCustomer->phone : '')
        : '';
@endphp

<form method="POST" action="{{ route('orders.store') }}"
    x-data="{
        type: '{{ old('type', $selectedCustomer ? 'delivery' : 'dine_in') }}',
        customerId: '{{ old('customer_id', $selectedCustomer?->id ?? '') }}',
        customerSearchQuery: @js($initialCustomerLabel),
        customerResults: [],
        showCustomerDropdown: false,
        customerSearchLoading: false,
        customerSearchTimer: null,
        customerSearchUrl: @js(route('customers.search')),
        showNewCustomer: false,
        savingCustomer: false,
        customerError: '',
        newCustomer: { name: '', phone: '', email: '', address: '', neighborhood: '', city: '', state: '', zip_code: '' },
        items: [{ variantId: '' }],
        deliveryQuote: null,
        deliveryQuoteLoading: false,
        deliveryQuoteError: null,
        deliveryFeeInput: @js(old('delivery_fee', '')),
        deliveryFeeTouched: @js(filled(old('delivery_fee'))),
        quoteUrlTemplate: @js(route('customers.delivery-quote', ['customer' => '__CUSTOMER__'])),
        addItem() { this.items.push({ variantId: '' }); },
        removeItem(index) { this.items.splice(index, 1); },
        syncVariant(index, event) {
            const option = event.target.selectedOptions[0];
            this.items[index].variantId = option?.dataset?.variantId || '';
        },
        openNewCustomer() {
            this.showNewCustomer = true;
            this.customerError = '';
            this.customerId = '';
            this.customerSearchQuery = '';
            this.customerResults = [];
            this.showCustomerDropdown = false;
        },
        cancelNewCustomer() {
            this.showNewCustomer = false;
            this.customerError = '';
            this.newCustomer = { name: '', phone: '', email: '', address: '', neighborhood: '', city: '', state: '', zip_code: '' };
        },
        selectCustomer(customer) {
            this.customerId = String(customer.id);
            this.customerSearchQuery = customer.label;
            this.customerResults = [];
            this.showCustomerDropdown = false;
        },
        clearCustomer() {
            this.customerId = '';
            this.customerSearchQuery = '';
            this.customerResults = [];
        },
        onCustomerSearchInput() {
            clearTimeout(this.customerSearchTimer);
            if (this.showNewCustomer) {
                return;
            }
            if (this.customerId && this.customerSearchQuery.trim() === '') {
                this.clearCustomer();
            }
            if (this.customerSearchQuery.trim().length < 2) {
                this.customerResults = [];
                this.showCustomerDropdown = false;
                return;
            }
            this.customerSearchTimer = setTimeout(() => this.fetchCustomers(), 250);
        },
        async fetchCustomers() {
            const q = this.customerSearchQuery.trim();
            if (q.length < 2) {
                return;
            }
            this.customerSearchLoading = true;
            try {
                const res = await fetch(this.customerSearchUrl + '?search=' + encodeURIComponent(q), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const json = await res.json();
                this.customerResults = json.data || [];
                this.showCustomerDropdown = this.customerResults.length > 0;
            } catch (e) {
                this.customerResults = [];
                this.showCustomerDropdown = false;
            } finally {
                this.customerSearchLoading = false;
            }
        },
        async saveNewCustomer() {
            this.customerError = '';
            if (!this.newCustomer.name.trim()) {
                this.customerError = 'Informe o nome do cliente.';
                return;
            }
            this.savingCustomer = true;
            try {
                const res = await fetch(@js(route('customers.quick-store')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(this.newCustomer),
                });
                const json = await res.json();
                if (!res.ok) {
                    const firstError = json.errors ? Object.values(json.errors).flat()[0] : null;
                    this.customerError = firstError || json.message || 'Não foi possível cadastrar o cliente.';
                    return;
                }
                const created = json.data;
                this.selectCustomer(created);
                this.cancelNewCustomer();
            } catch (e) {
                this.customerError = 'Falha de conexão ao cadastrar o cliente.';
            } finally {
                this.savingCustomer = false;
            }
        },
        async refreshDeliveryQuote() {
            this.deliveryQuote = null;
            this.deliveryQuoteError = null;

            if (this.type !== 'delivery' || !this.customerId) {
                return;
            }

            this.deliveryQuoteLoading = true;

            try {
                const response = await fetch(this.quoteUrlTemplate.replace('__CUSTOMER__', this.customerId), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await response.json();

                if (data.ok) {
                    this.deliveryQuote = data;
                    if (!this.deliveryFeeTouched && data.delivery_fee != null) {
                        this.deliveryFeeInput = String(data.delivery_fee);
                    }
                } else {
                    this.deliveryQuoteError = data.message || 'Não foi possível calcular a taxa.';
                    if (data.delivery_address) {
                        this.deliveryQuote = {
                            delivery_address: data.delivery_address,
                            delivery_fee: null,
                            distance_km: data.distance_km ?? null,
                            delivery_area_name: null,
                        };
                    }
                }
            } catch (e) {
                this.deliveryQuoteError = 'Erro ao consultar a taxa de entrega.';
            } finally {
                this.deliveryQuoteLoading = false;
            }
        },
        onDeliveryFeeInput() {
            this.deliveryFeeTouched = true;
        },
        init() {
            this.$watch('type', (value) => {
                if (value !== 'delivery') {
                    this.deliveryFeeInput = '';
                    this.deliveryFeeTouched = false;
                }
                this.refreshDeliveryQuote();
            });
            this.$watch('customerId', () => this.refreshDeliveryQuote());
            this.refreshDeliveryQuote();
        },
    }"
    class="space-y-6">
    @csrf
    <input type="hidden" name="customer_id" x-model="customerId">

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
        <div x-show="type !== 'dine_in'" class="md:col-span-2 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1 relative" @click.away="showCustomerDropdown = false">
                    <label for="customer_search" class="block text-sm font-medium text-gray-700">Cliente cadastrado</label>
                    <input type="text" id="customer_search" x-model="customerSearchQuery"
                        @input="customerId = ''; onCustomerSearchInput()"
                        @focus="onCustomerSearchInput()"
                        :disabled="showNewCustomer"
                        autocomplete="off"
                        placeholder="Buscar por nome, telefone ou e-mail..."
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                    <p class="mt-1 text-xs text-gray-500" x-show="customerSearchLoading" x-cloak>Buscando...</p>
                    <div x-show="showCustomerDropdown && customerResults.length > 0" x-cloak
                        class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg max-h-56 overflow-y-auto">
                        <template x-for="customer in customerResults" :key="customer.id">
                            <button type="button" @click="selectCustomer(customer)"
                                class="block w-full text-left px-3 py-2 text-sm text-gray-800 hover:bg-indigo-50 border-b border-gray-100 last:border-0"
                                x-text="customer.label"></button>
                        </template>
                    </div>
                    <p class="mt-1 text-xs text-indigo-700" x-show="customerId" x-cloak>
                        Cliente selecionado.
                        <button type="button" @click="clearCustomer()" class="underline hover:text-indigo-900">Limpar</button>
                    </p>
                </div>
                <button type="button" x-show="!showNewCustomer" @click="openNewCustomer()"
                    class="inline-flex items-center justify-center px-4 py-2 bg-white border border-indigo-300 text-indigo-700 text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-50 whitespace-nowrap">
                    + Novo cliente
                </button>
            </div>

            <div x-show="showNewCustomer" x-cloak class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-medium text-indigo-950">Cadastrar novo cliente</p>
                    <button type="button" @click="cancelNewCustomer()" class="text-xs text-indigo-700 hover:underline">Cancelar</button>
                </div>
                <p class="text-xs text-indigo-900/80">O cliente será salvo no CRM e já ficará selecionado neste pedido.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Nome *</label>
                        <input type="text" x-model="newCustomer.name" autocomplete="name"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Telefone</label>
                        <input type="text" x-model="newCustomer.phone" autocomplete="tel"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">E-mail</label>
                        <input type="email" x-model="newCustomer.email" autocomplete="email"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Endereço</label>
                        <input type="text" x-model="newCustomer.address" autocomplete="street-address"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bairro</label>
                        <input type="text" x-model="newCustomer.neighborhood"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Cidade</label>
                        <input type="text" x-model="newCustomer.city" autocomplete="address-level2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">UF</label>
                        <input type="text" maxlength="2" x-model="newCustomer.state" autocomplete="address-level1"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">CEP</label>
                        <input type="text" x-model="newCustomer.zip_code" autocomplete="postal-code"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <p class="text-sm text-red-600" x-show="customerError" x-text="customerError"></p>

                <button type="button" @click="saveNewCustomer()" :disabled="savingCustomer"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700 disabled:opacity-50">
                    <span x-show="!savingCustomer">Salvar cliente</span>
                    <span x-show="savingCustomer" x-cloak>Salvando…</span>
                </button>
            </div>
        </div>
        <div x-show="type !== 'dine_in' && !customerId && !showNewCustomer">
            <label for="customer_name" class="block text-sm font-medium text-gray-700">Nome do cliente</label>
            <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div x-show="type !== 'dine_in' && !customerId && !showNewCustomer">
            <label for="customer_phone" class="block text-sm font-medium text-gray-700">Telefone</label>
            <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div x-show="type === 'delivery'" x-cloak class="max-w-xs">
        <label for="delivery_fee" class="block text-sm font-medium text-gray-700">Taxa de entrega (R$)</label>
        <input type="number" step="0.01" min="0" name="delivery_fee" id="delivery_fee"
            x-model="deliveryFeeInput"
            @input="onDeliveryFeeInput()"
            placeholder="Automático"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <p class="mt-1 text-xs text-gray-500">Deixe em branco para calcular pelo endereço do cliente. Preencha para usar um valor manual.</p>
    </div>

    <div class="max-w-xs">
        <label for="discount" class="block text-sm font-medium text-gray-700">Desconto (R$)</label>
        <input type="number" step="0.01" min="0" name="discount" id="discount"
            value="{{ old('discount', '') }}"
            placeholder="0,00"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <p class="mt-1 text-xs text-gray-500">Opcional. Use para abater a taxa de entrega ou dar desconto no total. A taxa continua registrada para o motoboy.</p>
    </div>

    <div class="max-w-sm">
        <label for="ordered_at" class="block text-sm font-medium text-gray-700">Data/hora do pedido</label>
        <input type="datetime-local" name="ordered_at" id="ordered_at"
            value="{{ old('ordered_at') }}"
            max="{{ now()->format('Y-m-d\TH:i') }}"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <p class="mt-1 text-xs text-gray-500">Opcional. Deixe em branco para agora. Use para lançar pedido retroativo (entra no dia escolhido em Pedidos e Financeiro).</p>
        @error('ordered_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div x-show="type === 'delivery' && customerId" x-cloak class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
        <template x-if="deliveryQuoteLoading">
            <p>Calculando taxa de entrega...</p>
        </template>
        <template x-if="!deliveryQuoteLoading && deliveryQuote && deliveryQuote.delivery_fee !== null && deliveryQuote.delivery_fee !== undefined">
            <div class="space-y-1">
                <p class="font-medium">
                    Taxa de entrega:
                    <span x-text="'R$ ' + Number(deliveryQuote.delivery_fee).toFixed(2).replace('.', ',')"></span>
                    <span class="font-normal text-indigo-700" x-show="deliveryQuote.delivery_area_name" x-text="'(' + deliveryQuote.delivery_area_name + ')'"></span>
                </p>
                <p class="text-indigo-800" x-show="deliveryQuote.distance_km != null" x-text="'Distância aprox.: ' + deliveryQuote.distance_km + ' km'"></p>
                <p class="text-indigo-700" x-text="deliveryQuote.delivery_address"></p>
            </div>
        </template>
        <template x-if="!deliveryQuoteLoading && deliveryQuoteError">
            <div class="space-y-1">
                <p class="font-medium text-amber-800" x-text="deliveryQuoteError"></p>
                <p class="text-indigo-700" x-show="deliveryQuote?.delivery_address" x-text="deliveryQuote.delivery_address"></p>
            </div>
        </template>
    </div>

    <div>
        <label for="notes" class="block text-sm font-medium text-gray-700">Observações do pedido</label>
        <textarea name="notes" id="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
    </div>

    <x-payment-method-select :required="false" :selected="old('payment_method')" />

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
