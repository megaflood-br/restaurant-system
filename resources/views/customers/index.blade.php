<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">CRM — Clientes</h2>
            <button type="button" x-data="" @click="$dispatch('open-modal', 'new-customer')"
                class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Novo cliente
            </button>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <x-flash-messages />

                    <form method="GET" class="mb-6 flex flex-col sm:flex-row gap-3">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por nome, e-mail ou telefone..."
                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <select name="status" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos os status</option>
                            <option value="active" @selected(request('status') === 'active')>Ativos</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inativos</option>
                        </select>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            Filtrar
                        </button>
                    </form>

                    <x-bulk-select :action="route('customers.bulk-destroy')" confirm="Excluir :count cliente(s) selecionado(s)? Pedidos vinculados serão mantidos.">
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <x-bulk-select-all />
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contato</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pedidos</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse ($customers as $customer)
                                        <tr>
                                            <x-bulk-select-item :id="$customer->id" />
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-gray-900">{{ $customer->name }}</p>
                                                @if ($customer->city)
                                                    <p class="text-sm text-gray-500">{{ $customer->city }}{{ $customer->state ? ', '.$customer->state : '' }}</p>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">
                                                @if ($customer->phone)<p>{{ $customer->phone }}</p>@endif
                                                @if ($customer->email)<p class="text-gray-500">{{ $customer->email }}</p>@endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $customer->orders_count }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $customer->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                    {{ $customer->is_active ? 'Ativo' : 'Inativo' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                                <a href="{{ route('customers.show', $customer) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Perfil</a>
                                                <a href="{{ route('customers.edit', $customer) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Editar</a>
                                                <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="inline"
                                                    onsubmit="return confirm('Excluir o cliente {{ $customer->name }}? Pedidos vinculados serão mantidos sem o vínculo do cliente.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Excluir</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">Nenhum cliente cadastrado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="md:hidden space-y-3">
                            @forelse ($customers as $customer)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $customer->name }}</p>
                                            <p class="text-sm text-gray-500">{{ $customer->phone ?? $customer->email ?? '—' }}</p>
                                        </div>
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $customer->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $customer->is_active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-2">{{ $customer->orders_count }} pedido(s)</p>
                                    <div class="mt-3 flex gap-3 text-sm">
                                        <a href="{{ route('customers.show', $customer) }}" class="text-indigo-600">Perfil</a>
                                        <a href="{{ route('customers.edit', $customer) }}" class="text-indigo-600">Editar</a>
                                        <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="inline"
                                            onsubmit="return confirm('Excluir o cliente {{ $customer->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600">Excluir</button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center text-gray-500 py-8">Nenhum cliente cadastrado.</p>
                            @endforelse
                        </div>
                    </x-bulk-select>

                    <div class="mt-4">{{ $customers->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    <x-modal name="new-customer" maxWidth="2xl" focusable>
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Novo cliente</h3>
            <form method="POST" action="{{ route('customers.store') }}" class="space-y-4" autocomplete="off">
                @csrf
                @include('customers._form', ['customer' => null])
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                        Salvar cliente
                    </button>
                    <button type="button" @click="$dispatch('close-modal', 'new-customer')" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-gray-50">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </x-modal>
</x-app-layout>
