@props(['id'])

<td {{ $attributes->merge(['class' => 'px-4 py-3 w-10']) }}>
    <input type="checkbox"
           data-bulk-id
           value="{{ $id }}"
           x-model="selected"
           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
           aria-label="Selecionar item {{ $id }}">
</td>
