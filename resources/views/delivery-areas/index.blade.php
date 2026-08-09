<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Regiões de entrega</h2>
            <a href="{{ route('delivery-areas.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Nova região
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <x-flash-messages />

                    <p class="text-sm text-gray-600 mb-6">
                        Configure faixas de distância em quilômetros e a taxa de entrega de cada uma. O bot do WhatsApp calcula a taxa com base no endereço do cliente e na localização do restaurante (Configurações → Geral).
                    </p>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Faixa</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Distância (km)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taxa</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ordem</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pedidos</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($deliveryAreas as $area)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $area->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $area->rangeLabel() }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">R$ {{ number_format($area->fee, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $area->sort_order }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $area->orders_count }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $area->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ $area->is_active ? 'Ativa' : 'Inativa' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right space-x-2">
                                            <a href="{{ route('delivery-areas.edit', $area) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Editar</a>
                                            <form action="{{ route('delivery-areas.destroy', $area) }}" method="POST" class="inline" onsubmit="return confirm('Excluir esta região?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Excluir</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                            Nenhuma faixa cadastrada. Cadastre distâncias em km para habilitar cálculo de entrega.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $deliveryAreas->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
