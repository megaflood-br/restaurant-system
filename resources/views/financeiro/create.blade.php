<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $type === 'saida' ? 'Nova saída' : 'Nova entrada' }}
            </h2>
            <a href="{{ route('financeiro.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">Voltar ao caixa</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <x-flash-messages />

                    <form method="POST" action="{{ route('financeiro.store') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                            <div class="flex gap-4">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="radio" name="type" value="entrada" class="text-emerald-600"
                                        @checked(old('type', $type) === 'entrada')
                                        onchange="window.location='{{ route('financeiro.create', ['type' => 'entrada']) }}'">
                                    Entrada
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="radio" name="type" value="saida" class="text-rose-600"
                                        @checked(old('type', $type) === 'saida')
                                        onchange="window.location='{{ route('financeiro.create', ['type' => 'saida']) }}'">
                                    Saída
                                </label>
                            </div>
                        </div>

                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Categoria</label>
                            <select name="category" id="category" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @php
                                    $categories = old('type', $type) === 'saida' ? $saidaCategories : $entradaCategories;
                                @endphp
                                @foreach ($categories as $value => $label)
                                    <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('category')
                                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700">Valor (R$)</label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="amount" required
                                value="{{ old('amount') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('amount')
                                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700">Forma de pagamento</label>
                            <select name="payment_method" id="payment_method"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">—</option>
                                @foreach ($paymentMethods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Descrição</label>
                            <input type="text" name="description" id="description" maxlength="255"
                                value="{{ old('description') }}"
                                placeholder="Ex.: compra de gás, sangria para o cofre..."
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="occurred_at" class="block text-sm font-medium text-gray-700">Data/hora</label>
                            <input type="datetime-local" name="occurred_at" id="occurred_at"
                                value="{{ old('occurred_at', now()->format('Y-m-d\\TH:i')) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                                Salvar lançamento
                            </button>
                            <a href="{{ route('financeiro.index') }}" class="text-sm text-gray-600 hover:text-gray-800">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
