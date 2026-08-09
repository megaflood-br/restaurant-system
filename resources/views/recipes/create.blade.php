<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Nova ficha técnica</h2></x-slot>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <x-flash-messages />
                <form method="POST" action="{{ route('recipes.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @include('recipes._form', ['recipe' => null])
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase rounded-md hover:bg-indigo-700">Salvar ficha</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
