@extends('layouts.menu')

@section('page-title', 'Checkout')

@section('content')
    @php
        $areasJson = $deliveryAreas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'fee' => (float) $a->fee])->values();
        $subtotal = $total;
    @endphp

    <div class="px-4 py-6" x-data="{
        type: '{{ old('type', $orderType) }}',
        subtotal: {{ $subtotal }},
        areas: @js($areasJson),
        deliveryAreaId: '{{ old('delivery_area_id', '') }}',
        get selectedArea() {
            return this.areas.find(a => String(a.id) === String(this.deliveryAreaId)) || null;
        },
        get deliveryFee() {
            return this.type === 'delivery' && this.selectedArea ? this.selectedArea.fee : 0;
        },
        get grandTotal() {
            return this.subtotal + this.deliveryFee;
        },
        formatMoney(value) {
            return 'R$ ' + Number(value).toFixed(2).replace('.', ',');
        }
    }">
        <h2 class="text-xl font-bold mb-6">Finalizar pedido</h2>

        <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-6 space-y-2">
            @foreach ($items as $item)
                <div class="flex justify-between text-sm">
                    <span>{{ $item['quantity'] }}x {{ $item['name'] }}</span>
                    <span class="font-medium">R$ {{ number_format($item['subtotal'], 2, ',', '.') }}</span>
                </div>
            @endforeach
            <div class="border-t border-gray-100 pt-2 flex justify-between text-sm">
                <span class="text-gray-600">Subtotal</span>
                <span class="font-medium" x-text="formatMoney(subtotal)">R$ {{ number_format($subtotal, 2, ',', '.') }}</span>
            </div>
            <div x-show="type === 'delivery' && deliveryFee > 0" x-cloak class="flex justify-between text-sm">
                <span class="text-gray-600">Taxa de entrega</span>
                <span class="font-medium" x-text="formatMoney(deliveryFee)"></span>
            </div>
            <div class="border-t border-gray-100 pt-2 flex justify-between font-bold">
                <span>Total</span>
                <span class="text-orange-600" x-text="formatMoney(grandTotal)">R$ {{ number_format($subtotal, 2, ',', '.') }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('public.checkout.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Como você vai receber?</label>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['dine_in' => 'Salão', 'takeaway' => 'Retirada', 'delivery' => 'Delivery'] as $value => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="{{ $value }}" x-model="type" class="sr-only peer"
                                @if($value === 'delivery' && $deliveryAreas->isEmpty()) disabled @endif>
                            <span @class([
                                'block text-center rounded-xl border-2 px-3 py-3 text-sm font-semibold transition',
                                'border-gray-200 peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-700',
                                'opacity-40 cursor-not-allowed' => $value === 'delivery' && $deliveryAreas->isEmpty(),
                            ])>
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
                @if ($deliveryAreas->isEmpty())
                    <p class="mt-2 text-xs text-amber-600">Delivery indisponível no momento — nenhuma região cadastrada.</p>
                @endif
            </div>

            <div x-show="type === 'dine_in'" x-cloak>
                <label for="comanda_number" class="block text-sm font-medium text-gray-700">Número da comanda *</label>
                <input type="number" name="comanda_number" id="comanda_number" min="1" max="999"
                    :required="type === 'dine_in'"
                    :disabled="type !== 'dine_in'"
                    value="{{ old('comanda_number', $comandaNumber) }}"
                    placeholder="Ex: 042"
                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                <p class="mt-1 text-xs text-gray-500">Informe o número da comanda que você recebeu na entrada.</p>
            </div>

            <div x-show="type === 'delivery'" x-cloak class="space-y-4">
                <div>
                    <label for="delivery_area_id" class="block text-sm font-medium text-gray-700">Bairro / região *</label>
                    <select name="delivery_area_id" id="delivery_area_id" x-model="deliveryAreaId"
                        :required="type === 'delivery'"
                        :disabled="type !== 'delivery'"
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                        <option value="">Selecione seu bairro</option>
                        @foreach ($deliveryAreas as $area)
                            <option value="{{ $area->id }}" @selected(old('delivery_area_id') == $area->id)>
                                {{ $area->name }} — R$ {{ number_format($area->fee, 2, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="delivery_address" class="block text-sm font-medium text-gray-700">Endereço completo *</label>
                    <input type="text" name="delivery_address" id="delivery_address" value="{{ old('delivery_address') }}"
                        placeholder="Rua, número, complemento"
                        :required="type === 'delivery'"
                        :disabled="type !== 'delivery'"
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                </div>
            </div>

            <div x-show="type !== 'dine_in'" x-cloak class="space-y-4">
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700">Seu nome *</label>
                    <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}"
                        :required="type !== 'dine_in'"
                        :disabled="type === 'dine_in'"
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                </div>
                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-gray-700">WhatsApp / Telefone *</label>
                    <input type="tel" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}" placeholder="(11) 99999-9999"
                        :required="type !== 'dine_in'"
                        :disabled="type === 'dine_in'"
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                </div>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700">Observações do pedido</label>
                <textarea name="notes" id="notes" rows="2" placeholder="Alguma observação geral?"
                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">{{ old('notes') }}</textarea>
            </div>

            @if ($errors->any())
                <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <button type="submit" class="w-full rounded-2xl bg-orange-500 hover:bg-orange-600 text-white font-bold py-4 text-lg shadow-lg shadow-orange-500/30 transition">
                Confirmar pedido
            </button>
        </form>
    </div>
@endsection
