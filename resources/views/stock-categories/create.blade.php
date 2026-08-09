<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Nova categoria de estoque</h2></x-slot>
    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('stock-categories.store') }}" class="space-y-6">
                    @csrf
                    @include('stock-categories._form')
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase rounded-md hover:bg-indigo-700">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
