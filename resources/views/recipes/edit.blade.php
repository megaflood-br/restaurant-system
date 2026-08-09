<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar ficha — {{ $recipe->name }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('recipes.print', $recipe) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">Imprimir</a>
                <a href="{{ route('recipes.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">Voltar</a>
            </div>
        </div>
    </x-slot>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <x-flash-messages />
                <form method="POST" action="{{ route('recipes.update', $recipe) }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf @method('PUT')
                    @include('recipes._form')
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase rounded-md hover:bg-indigo-700">Salvar ficha</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
