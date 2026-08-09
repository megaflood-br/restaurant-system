<div>
    <label for="name" class="block text-sm font-medium text-gray-700">Nome *</label>
    <input type="text" name="name" id="name" value="{{ old('name', $customer->name ?? '') }}" required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label for="email" class="block text-sm font-medium text-gray-700">E-mail</label>
        <input type="email" name="email" id="email" value="{{ old('email', $customer->email ?? '') }}"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="phone" class="block text-sm font-medium text-gray-700">Telefone</label>
        <input type="text" name="phone" id="phone" value="{{ old('phone', $customer->phone ?? '') }}"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<div>
    <label for="birth_date" class="block text-sm font-medium text-gray-700">Data de nascimento</label>
    <input type="date" name="birth_date" id="birth_date" value="{{ old('birth_date', isset($customer) && $customer->birth_date ? $customer->birth_date->format('Y-m-d') : '') }}"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('birth_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="address" class="block text-sm font-medium text-gray-700">Endereço</label>
    <input type="text" name="address" id="address" value="{{ old('address', $customer->address ?? '') }}"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label for="neighborhood" class="block text-sm font-medium text-gray-700">Bairro</label>
        <input type="text" name="neighborhood" id="neighborhood" value="{{ old('neighborhood', $customer->neighborhood ?? '') }}"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('neighborhood') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="city" class="block text-sm font-medium text-gray-700">Cidade</label>
        <input type="text" name="city" id="city" value="{{ old('city', $customer->city ?? '') }}"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="state" class="block text-sm font-medium text-gray-700">UF</label>
        <input type="text" name="state" id="state" maxlength="2" value="{{ old('state', $customer->state ?? '') }}"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<div>
    <label for="zip_code" class="block text-sm font-medium text-gray-700">CEP</label>
    <input type="text" name="zip_code" id="zip_code" value="{{ old('zip_code', $customer->zip_code ?? '') }}"
        class="mt-1 block w-full md:w-1/3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('zip_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
    <textarea name="notes" id="notes" rows="3"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $customer->notes ?? '') }}</textarea>
    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="flex items-center gap-2">
    <input type="checkbox" name="is_active" id="is_active" value="1"
        @checked(old('is_active', $customer->is_active ?? true)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
    <label for="is_active" class="text-sm text-gray-700">Cliente ativo</label>
</div>
