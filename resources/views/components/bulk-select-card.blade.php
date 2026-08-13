@props(['id', 'class' => ''])

<label {{ $attributes->class(['inline-flex items-center gap-2 text-sm text-gray-600', $class]) }}>
    <input type="checkbox"
           data-bulk-id
           value="{{ $id }}"
           x-model="selected"
           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
           aria-label="Selecionar item {{ $id }}">
    <span class="sr-only">Selecionar</span>
</label>
