@props([])

<th {{ $attributes->merge(['class' => 'px-4 py-3 w-10']) }}>
    <input type="checkbox"
           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
           :checked="isAllSelected()"
           @click="toggleAll($event)"
           title="Selecionar todos nesta página"
           aria-label="Selecionar todos nesta página">
</th>
