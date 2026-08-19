<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Novo pedido</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @include('orders._form', [
                    'products' => $products,
                    'selectedCustomer' => $selectedCustomer,
                    'comandaNumber' => $comandaNumber,
                ])
            </div>
        </div>
    </div>
</x-app-layout>
