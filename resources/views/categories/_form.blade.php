<div>
    <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="name" id="name" value="{{ old('name', $category->name ?? '') }}" required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="description" class="block text-sm font-medium text-gray-700">Descrição</label>
    <textarea name="description" id="description" rows="3"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $category->description ?? '') }}</textarea>
    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <p class="block text-sm font-medium text-gray-700">Dias da semana no cardápio</p>
    <p class="mt-1 text-xs text-gray-500">Deixe todos desmarcados para aparecer todos os dias. Ex.: só “Segunda” = cardápio de segunda.</p>
    @php
        $selectedDays = collect(old('available_days', isset($category) ? ($category->available_days ?? []) : []))->map(fn ($d) => (string) $d);
        $weekdayLabels = $weekdayLabels ?? \App\Support\WeeklyMenuImages::labels();
    @endphp
    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
        @foreach ($weekdayLabels as $day => $label)
            <label class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">
                <input type="checkbox" name="available_days[]" value="{{ $day }}"
                    @checked($selectedDays->contains($day))
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                {{ $label }}
            </label>
        @endforeach
    </div>
    @error('available_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    @error('available_days.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="flex items-center gap-2">
    <input type="checkbox" name="is_active" id="is_active" value="1"
        @checked(old('is_active', $category->is_active ?? true)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
    <label for="is_active" class="text-sm text-gray-700">Categoria ativa</label>
</div>
