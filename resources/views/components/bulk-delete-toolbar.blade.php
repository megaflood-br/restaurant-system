@props([
    'action',
    'confirm' => 'Excluir :count item(ns) selecionado(s)? Esta ação não pode ser desfeita.',
])

<form method="POST" action="{{ $action }}" class="mb-4"
      @submit="
        selected = uniqueSelected();
        if (selected.length === 0) { $event.preventDefault(); return; }
        if (!confirm(@js($confirm).replace(':count', String(selected.length)))) { $event.preventDefault(); }
      ">
    @csrf
    @method('DELETE')

    <template x-for="id in uniqueSelected()" :key="id">
        <input type="hidden" name="ids[]" :value="id">
    </template>

    <div x-show="uniqueSelected().length > 0" x-cloak
         class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
        <p class="text-sm text-red-900">
            <span class="font-semibold" x-text="uniqueSelected().length"></span>
            selecionado(s)
        </p>
        <button type="submit"
                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
            Excluir selecionados
        </button>
    </div>
</form>
