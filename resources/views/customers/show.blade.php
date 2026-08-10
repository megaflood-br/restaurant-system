<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $customer->name }}</h2>
                <p class="text-sm text-gray-500 mt-1">Perfil do cliente</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('customers.comanda', $customer) }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
                    Novo pedido
                </a>
                <a href="{{ route('customers.edit', $customer) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                    Editar
                </a>
                <form method="POST" action="{{ route('customers.destroy', $customer) }}"
                    onsubmit="return confirm('Excluir o cliente {{ $customer->name }}? Pedidos vinculados serão mantidos sem o vínculo do cliente.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
                        Excluir
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Total de pedidos</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['orders_count'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Total gasto</p>
                    <p class="text-3xl font-bold text-green-600">R$ {{ number_format($stats['total_spent'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Ticket médio</p>
                    <p class="text-3xl font-bold text-gray-900">R$ {{ number_format($stats['average_ticket'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Último pedido</p>
                    <p class="text-lg font-bold text-gray-900">
                        {{ $stats['last_order'] ? $stats['last_order']->created_at->format('d/m/Y') : '—' }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Dados do cliente</h3>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500">Telefone</dt>
                            <dd class="font-medium text-gray-900">{{ $customer->phone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">E-mail</dt>
                            <dd class="font-medium text-gray-900">{{ $customer->email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Nascimento</dt>
                            <dd class="font-medium text-gray-900">{{ $customer->birth_date?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Endereço</dt>
                            <dd class="font-medium text-gray-900">
                                @if ($customer->address)
                                    {{ $customer->address }}<br>
                                    {{ $customer->neighborhood }}{{ $customer->city ? ' — '.$customer->city : '' }}{{ $customer->state ? '/'.$customer->state : '' }}
                                    @if ($customer->zip_code)<br>CEP {{ $customer->zip_code }}@endif
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        @if ($customer->notes)
                            <div>
                                <dt class="text-gray-500">Observações</dt>
                                <dd class="text-gray-900">{{ $customer->notes }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-gray-500">Status</dt>
                            <dd>
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $customer->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $customer->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Histórico de pedidos</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pedido</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($customer->orders as $order)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('orders.show', $order) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">{{ $order->order_number }}</a>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="px-4 py-3 text-sm font-medium">R$ {{ number_format($order->total, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3"><x-order-status-badge :status="$order->status" /></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">Nenhum pedido registrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($customer->phone)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">WhatsApp recente</h3>
                    <div class="space-y-3">
                        @forelse ($customer->whatsappMessages as $msg)
                            <div class="border rounded-lg p-3 {{ $msg->direction === 'inbound' ? 'bg-blue-50 border-blue-100' : 'bg-green-50 border-green-100' }}">
                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                    <span>{{ $msg->direction === 'inbound' ? 'Recebida' : 'Enviada' }} · {{ $msg->status }}</span>
                                    <span>{{ $msg->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <p class="text-sm text-gray-800">{{ $msg->message }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nenhuma mensagem WhatsApp registrada.</p>
                        @endforelse
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Registrar interação</h3>
                    <form method="POST" action="{{ route('customers.interactions.store', $customer) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700">Tipo</label>
                            <select name="type" id="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($interactionTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="content" class="block text-sm font-medium text-gray-700">Conteúdo</label>
                            <textarea name="content" id="content" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            @error('content') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            Registrar
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Timeline de interações</h3>
                    <div class="space-y-4">
                        @forelse ($customer->interactions as $interaction)
                            <div class="border-l-4 border-indigo-400 pl-4 py-2">
                                <div class="flex justify-between items-start">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $interactionTypes[$interaction->type] ?? $interaction->type }}
                                    </span>
                                    <span class="text-xs text-gray-400">{{ $interaction->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <p class="mt-2 text-sm text-gray-800">{{ $interaction->content }}</p>
                                @if ($interaction->user)
                                    <p class="mt-1 text-xs text-gray-500">por {{ $interaction->user->name }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nenhuma interação registrada.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <a href="{{ route('customers.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Voltar aos clientes</a>
        </div>
    </div>
</x-app-layout>
