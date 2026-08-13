@extends('layouts.waiter')

@section('content')
    @php
        $customerOptions = $customers->map(fn ($customer) => [
            'id' => (string) $customer->id,
            'label' => $customer->name.($customer->phone ? ' — '.$customer->phone : ''),
        ])->values()->all();
    @endphp

    <div class="px-4 py-4 pb-6" x-data="{
        search: '',
        customerOptions: @js($customerOptions),
        openFor: null,
        customerId: '',
        openUrl: '',
        matches(number) {
            if (!this.search.trim()) return true;
            return String(number).includes(this.search.trim()) ||
                   String(number).padStart(3, '0').includes(this.search.trim());
        },
        openModal(number, url) {
            this.openFor = number;
            this.openUrl = url;
            this.customerId = '';
        },
        closeModal() {
            this.openFor = null;
            this.openUrl = '';
            this.customerId = '';
        },
    }">
        <div class="mb-5">
            <input type="search" x-model="search" inputmode="numeric"
                placeholder="Digite o nº da comanda..."
                class="w-full rounded-xl border-0 bg-white shadow-sm ring-1 ring-gray-200 px-4 py-3.5 text-base placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500">
        </div>

        <div class="flex flex-wrap gap-3 mb-5 text-xs text-gray-600">
            <span class="inline-flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-sm bg-emerald-500"></span> Em andamento
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-sm bg-amber-400"></span> Pronta p/ fechar
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-sm bg-slate-500"></span> Livre
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-sm ring-2 ring-red-400"></span> Pedido atrasado
            </span>
        </div>

        @if (session('error'))
            <div class="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700 mb-4">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800 mb-4">{{ session('success') }}</div>
        @endif

        @if ($overview['counts']['occupied'] > 0)
            <section class="mb-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">
                    Comandas em uso ({{ $overview['counts']['occupied'] }})
                </h2>

                <div class="grid grid-cols-4 gap-2">
                    @foreach ($overview['active'] as $comanda)
                        <a href="{{ route('waiter.comandas.show', $comanda['number']) }}"
                            x-show="matches({{ $comanda['number'] }})"
                            x-cloak
                            class="relative aspect-square rounded-lg bg-emerald-500 hover:bg-emerald-600 active:scale-95 transition shadow-sm flex flex-col items-center justify-center text-white px-1 @if($comanda['has_delayed']) ring-2 ring-red-400 ring-offset-1 @endif">
                            <span class="absolute top-1 right-1.5 text-[9px] font-semibold bg-black/15 rounded px-1 py-0.5 leading-none">
                                {{ $comanda['elapsed_label'] }}
                            </span>
                            <span class="text-xl font-bold leading-none">{{ $comanda['label'] }}</span>
                            <span class="text-[10px] font-medium mt-1 opacity-90">
                                R$ {{ number_format($comanda['total'], 0, ',', '.') }}
                            </span>
                        </a>
                    @endforeach

                    @foreach ($overview['ready'] as $comanda)
                        <a href="{{ route('waiter.comandas.show', $comanda['number']) }}"
                            x-show="matches({{ $comanda['number'] }})"
                            x-cloak
                            class="relative aspect-square rounded-lg bg-amber-400 hover:bg-amber-500 active:scale-95 transition shadow-sm flex flex-col items-center justify-center text-white px-1">
                            <svg class="absolute top-1.5 left-1.5 w-3.5 h-3.5 opacity-80" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="absolute top-1 right-1.5 text-[9px] font-semibold bg-black/15 rounded px-1 py-0.5 leading-none">
                                {{ $comanda['elapsed_label'] }}
                            </span>
                            <span class="text-xl font-bold leading-none">{{ $comanda['label'] }}</span>
                            <span class="text-[10px] font-medium mt-1 opacity-90">
                                R$ {{ number_format($comanda['total'], 0, ',', '.') }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <h2 class="text-sm font-semibold text-gray-700 mb-3">
                Comandas livres ({{ $overview['counts']['free'] }})
            </h2>

            @if ($overview['counts']['free'] > 0)
                <div class="grid grid-cols-4 gap-2">
                    @foreach ($overview['free'] as $comanda)
                        <button type="button"
                            x-show="matches({{ $comanda['number'] }})"
                            x-cloak
                            @click="openModal({{ $comanda['number'] }}, @js(route('waiter.comandas.open', $comanda['number'])))"
                            class="w-full aspect-square rounded-lg bg-slate-500 hover:bg-slate-600 active:scale-95 transition shadow-sm flex flex-col items-center justify-center text-white">
                            <span class="text-[10px] font-bold uppercase tracking-wide opacity-90">Entregar</span>
                            <span class="text-xl font-bold leading-none">{{ $comanda['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 text-center py-6 bg-white rounded-xl ring-1 ring-gray-100">
                    Todas as {{ $overview['total_comandas'] }} comandas estão em uso.
                </p>
            @endif
        </section>

        <div x-show="openFor !== null" x-cloak
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
            @keydown.escape.window="closeModal()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeModal()"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl p-5 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        Abrir comanda <span x-text="String(openFor).padStart(3, '0')"></span>
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">Vincular cliente é opcional.</p>
                </div>

                <form method="POST" :action="openUrl" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Cliente</label>
                        <select name="customer_id" x-model="customerId"
                            class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Sem cliente</option>
                            <template x-for="customer in customerOptions" :key="customer.id">
                                <option :value="customer.id" x-text="customer.label"></option>
                            </template>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="closeModal()"
                            class="px-4 py-3 rounded-xl bg-white border border-gray-300 text-sm font-semibold text-gray-700">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-4 py-3 rounded-xl bg-indigo-600 text-sm font-semibold text-white">
                            Abrir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
