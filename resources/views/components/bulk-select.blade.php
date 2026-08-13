@props([
    'action',
    'confirm' => 'Excluir :count item(ns) selecionado(s)? Esta ação não pode ser desfeita.',
])

<div
    x-data="{
        selected: [],
        pageIds() {
            return [...new Set(Array.from(this.$root.querySelectorAll('[data-bulk-id]')).map((el) => el.value))];
        },
        uniqueSelected() {
            return [...new Set(this.selected)];
        },
        isAllSelected() {
            const ids = this.pageIds();
            return ids.length > 0 && ids.every((id) => this.uniqueSelected().includes(id));
        },
        toggleAll(event) {
            this.selected = event.target.checked ? this.pageIds() : [];
        },
    }"
    {{ $attributes }}
>
    <x-bulk-delete-toolbar :action="$action" :confirm="$confirm" />
    {{ $slot }}
</div>
