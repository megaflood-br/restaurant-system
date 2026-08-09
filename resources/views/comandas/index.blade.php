<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Comandas abertas</h2>
            <a href="{{ route('waiter.comandas.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                Abrir visão garçom →
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6" x-data="{
                    search: '',
                    matches(number) {
                        if (!this.search.trim()) return true;
                        return String(number).includes(this.search.trim()) ||
                               String(number).padStart(3, '0').includes(this.search.trim());
                    }
                }">
                    <x-flash-messages />

                    <div class="mb-6 flex flex-wrap items-end gap-3">
                        <form method="POST" action="{{ route('comandas.open.manual') }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <div>
                                <label for="open_comanda_number" class="block text-sm font-medium text-gray-700">Abrir comanda</label>
                                <input type="number" name="comanda_number" id="open_comanda_number" min="1" max="9999" required
                                    placeholder="Nº"
                                    class="mt-1 block w-28 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700">
                                Abrir
                            </button>
                        </form>
                    </div>

                    <div class="mb-6">
                        <input type="search" x-model="search" placeholder="Buscar comanda..."
                            class="block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="flex flex-wrap gap-4 mb-6 text-sm text-gray-600">
                        <span class="inline-flex items-center gap-2">
                            <span class="w-4 h-4 rounded bg-emerald-500"></span> Em andamento
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="w-4 h-4 rounded bg-amber-400"></span> Pronta p/ fechar
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="w-4 h-4 rounded bg-slate-400"></span> Livre
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="w-4 h-4 rounded ring-2 ring-red-400"></span> Pedido atrasado
                        </span>
                    </div>

                    @if ($overview['counts']['occupied'] > 0)
                        <section class="mb-8">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                                Em uso ({{ $overview['counts']['occupied'] }})
                            </h3>
                            <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-10 lg:grid-cols-12 gap-2">
                                @foreach ($overview['active'] as $item)
                                    <a href="{{ route('comandas.show', $item['number']) }}"
                                        x-show="matches({{ $item['number'] }})"
                                        x-cloak
                                        class="relative aspect-square rounded-lg bg-emerald-500 hover:bg-emerald-600 transition flex flex-col items-center justify-center text-white text-sm @if($item['has_delayed']) ring-2 ring-red-400 ring-offset-1 @endif">
                                        <span class="absolute top-1 right-1 text-[10px] font-medium bg-black/20 rounded px-1">{{ $item['elapsed_label'] }}</span>
                                        <span class="font-bold text-lg">{{ $item['label'] }}</span>
                                        <span class="text-[10px] opacity-90">R$ {{ number_format($item['total'], 0, ',', '.') }}</span>
                                    </a>
                                @endforeach
                                @foreach ($overview['ready'] as $item)
                                    <a href="{{ route('comandas.show', $item['number']) }}"
                                        x-show="matches({{ $item['number'] }})"
                                        x-cloak
                                        class="relative aspect-square rounded-lg bg-amber-400 hover:bg-amber-500 transition flex flex-col items-center justify-center text-white text-sm">
                                        <span class="absolute top-1 right-1 text-[10px] font-medium bg-black/20 rounded px-1">{{ $item['elapsed_label'] }}</span>
                                        <span class="font-bold text-lg">{{ $item['label'] }}</span>
                                        <span class="text-[10px] opacity-90">R$ {{ number_format($item['total'], 0, ',', '.') }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @else
                        <p class="text-gray-500 text-sm mb-8">Nenhuma comanda aberta no momento.</p>
                    @endif

                    <section>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">
                            Livres ({{ $overview['counts']['free'] }})
                        </h3>
                        @if ($overview['counts']['free'] > 0)
                            <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-10 lg:grid-cols-12 gap-2">
                                @foreach ($overview['free'] as $item)
                                    <form method="POST" action="{{ route('comandas.open', $item['number']) }}"
                                        x-show="matches({{ $item['number'] }})"
                                        x-cloak>
                                        @csrf
                                        <button type="submit"
                                            class="w-full aspect-square rounded-lg bg-slate-500 hover:bg-slate-600 transition flex flex-col items-center justify-center text-white text-sm">
                                            <span class="text-[10px] font-bold uppercase tracking-wide opacity-90">Abrir</span>
                                            <span class="font-bold text-lg">{{ $item['label'] }}</span>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500">Todas as {{ $overview['total_comandas'] }} comandas estão em uso.</p>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
