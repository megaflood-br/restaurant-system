<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Usuários</h2>
            <a href="{{ route('users.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Novo usuário
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <x-flash-messages />

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Perfil</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($users as $user)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            {{ $user->name }}
                                            @if ($user->id === auth()->id())
                                                <span class="text-xs text-gray-400">(você)</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $user->email }}</td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                                'bg-indigo-100 text-indigo-800' => $user->isAdmin(),
                                                'bg-emerald-100 text-emerald-800' => $user->isWaiter(),
                                            ])>
                                                {{ \App\Support\UserRole::label($user->role) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right space-x-2">
                                            <a href="{{ route('users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Editar</a>
                                            @if ($user->id !== auth()->id())
                                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline"
                                                    onsubmit="return confirm('Excluir {{ $user->name }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">Excluir</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">Nenhum usuário cadastrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $users->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
