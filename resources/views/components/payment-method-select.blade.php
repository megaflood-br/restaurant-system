@props(['selected' => null, 'compact' => false, 'required' => true])

@php
    $methods = \App\Support\PaymentMethod::labels();
    $current = old('payment_method', $selected);
@endphp

<fieldset {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <legend class="block text-sm font-medium text-gray-700 mb-2">
        Forma de pagamento{{ $required ? ' *' : '' }}
    </legend>
    <div @class([
        'grid gap-2',
        'grid-cols-2 sm:grid-cols-3' => ! $compact,
        'grid-cols-2' => $compact,
    ])>
        @if (! $required)
            <label class="cursor-pointer">
                <input type="radio" name="payment_method" value="" class="peer sr-only"
                    @checked($current === null || $current === '')>
                <span @class([
                    'flex items-center justify-center text-center rounded-xl border-2 px-3 py-3 text-sm font-semibold transition',
                    'peer-checked:border-gray-400 peer-checked:bg-gray-50 peer-checked:text-gray-800 border-gray-200 text-gray-500 hover:border-gray-300',
                    'py-4 text-base' => $compact,
                ])>
                    Não informar
                </span>
            </label>
        @endif
        @foreach ($methods as $value => $label)
            <label class="cursor-pointer">
                <input type="radio" name="payment_method" value="{{ $value }}" class="peer sr-only"
                    @if ($required) required @endif
                    @checked($current === $value)>
                <span @class([
                    'flex items-center justify-center text-center rounded-xl border-2 px-3 py-3 text-sm font-semibold transition',
                    'peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-800 border-gray-200 text-gray-700 hover:border-gray-300',
                    'py-4 text-base' => $compact,
                ])>
                    {{ $label }}
                </span>
            </label>
        @endforeach
    </div>
    @error('payment_method')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</fieldset>
