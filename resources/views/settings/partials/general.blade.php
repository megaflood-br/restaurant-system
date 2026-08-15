<form method="POST" action="{{ route('settings.general.update') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="app_name" class="block text-sm font-medium text-gray-700">Nome do estabelecimento</label>
            <input type="text" name="app_name" id="app_name" value="{{ old('app_name', $general['app_name']) }}" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="cnpj" class="block text-sm font-medium text-gray-700">CNPJ</label>
            <input type="text" name="cnpj" id="cnpj" value="{{ old('cnpj', $general['cnpj']) }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div>
        <label for="app_url" class="block text-sm font-medium text-gray-700">URL pública do sistema</label>
        <input type="url" name="app_url" id="app_url" value="{{ old('app_url', $general['app_url']) }}" required
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label for="address" class="block text-sm font-medium text-gray-700">Endereço do restaurante</label>
        <input type="text" name="address" id="address" value="{{ old('address', $general['address']) }}"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <p class="mt-1 text-xs text-gray-500">Usado para cálculo de entrega por km no WhatsApp.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="opening_time" class="block text-sm font-medium text-gray-700">Horário de abertura</label>
            <input type="time" name="opening_time" id="opening_time" value="{{ old('opening_time', $general['opening_time']) }}" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="closing_time" class="block text-sm font-medium text-gray-700">Horário de fechamento</label>
            <input type="time" name="closing_time" id="closing_time" value="{{ old('closing_time', $general['closing_time']) }}" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Dias de funcionamento</label>
        <p class="text-xs text-gray-500 mb-3">Usado no WhatsApp para agendar o próximo expediente (ex.: sábado à noite → segunda, se domingo estiver desmarcado).</p>
        <div class="flex flex-wrap gap-3">
            @php
                $selectedDays = old('open_days', $general['open_days'] ?? []);
            @endphp
            @foreach ($general['weekday_labels'] as $dayKey => $dayLabel)
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="open_days[]" value="{{ $dayKey }}"
                        @checked(in_array($dayKey, $selectedDays, true))
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    {{ $dayLabel }}
                </label>
            @endforeach
        </div>
        @error('open_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="delivery_origin_lat" class="block text-sm font-medium text-gray-700">Latitude (origem entrega)</label>
            <input type="text" name="delivery_origin_lat" id="delivery_origin_lat" value="{{ old('delivery_origin_lat', $general['delivery_origin_lat']) }}"
                placeholder="-23.550520"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="delivery_origin_lng" class="block text-sm font-medium text-gray-700">Longitude (origem entrega)</label>
            <input type="text" name="delivery_origin_lng" id="delivery_origin_lng" value="{{ old('delivery_origin_lng', $general['delivery_origin_lng']) }}"
                placeholder="-46.633308"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Logo</label>
        @if ($general['logo_url'])
            <img src="{{ $general['logo_url'] }}" alt="Logo" class="mb-3 h-20 w-20 object-cover rounded-lg border border-gray-200">
            <label class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-indigo-600"> Remover logo
            </label>
        @endif
        <input type="file" name="logo_image" accept="image/jpeg,image/png,image/webp"
            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700">
    </div>

    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
        Salvar
    </button>
</form>
