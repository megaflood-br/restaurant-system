@php
    /** @var \App\Models\Customer|null $customer */
    $customer = $customer ?? null;
@endphp

<div>
    <label for="customer_name" class="block text-sm font-medium text-gray-700">Nome *</label>
    <input type="text" name="name" id="customer_name" value="{{ old('name', $customer?->name ?? '') }}" required
        autocomplete="name"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label for="customer_email" class="block text-sm font-medium text-gray-700">E-mail</label>
        <input type="email" name="email" id="customer_email" value="{{ old('email', $customer?->email ?? '') }}"
            autocomplete="email"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="customer_phone" class="block text-sm font-medium text-gray-700">Telefone</label>
        <input type="text" name="phone" id="customer_phone" value="{{ old('phone', $customer?->phone ?? '') }}"
            autocomplete="tel"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<div>
    <label for="customer_birth_date" class="block text-sm font-medium text-gray-700">Data de nascimento</label>
    <input type="date" name="birth_date" id="customer_birth_date"
        value="{{ old('birth_date', $customer?->birth_date?->format('Y-m-d') ?? '') }}"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('birth_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="customer_address" class="block text-sm font-medium text-gray-700">Endereço</label>
    <input type="text" name="address" id="customer_address" value="{{ old('address', $customer?->address ?? '') }}"
        autocomplete="street-address"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label for="customer_neighborhood" class="block text-sm font-medium text-gray-700">Bairro</label>
        <input type="text" name="neighborhood" id="customer_neighborhood" value="{{ old('neighborhood', $customer?->neighborhood ?? '') }}"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('neighborhood') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="customer_city" class="block text-sm font-medium text-gray-700">Cidade</label>
        <input type="text" name="city" id="customer_city" value="{{ old('city', $customer?->city ?? '') }}"
            autocomplete="address-level2"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="customer_state" class="block text-sm font-medium text-gray-700">UF</label>
        <input type="text" name="state" id="customer_state" maxlength="2" value="{{ old('state', $customer?->state ?? '') }}"
            autocomplete="address-level1"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<div>
    <label for="customer_zip_code" class="block text-sm font-medium text-gray-700">CEP</label>
    <input type="text" name="zip_code" id="customer_zip_code" value="{{ old('zip_code', $customer?->zip_code ?? '') }}"
        autocomplete="postal-code"
        class="mt-1 block w-full md:w-1/3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('zip_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="customer_notes" class="block text-sm font-medium text-gray-700">Observações</label>
    <textarea name="notes" id="customer_notes" rows="3"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $customer?->notes ?? '') }}</textarea>
    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="flex items-center gap-2">
    <input type="checkbox" name="is_active" id="customer_is_active" value="1"
        @checked(old('is_active', $customer?->is_active ?? true)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
    <label for="customer_is_active" class="text-sm text-gray-700">Cliente ativo</label>
</div>
