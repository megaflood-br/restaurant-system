@extends('layouts.waiter')

@section('content')
    <div class="px-4 py-8 text-center">
        <div class="w-20 h-20 mx-auto rounded-full bg-emerald-100 flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 mb-1">Comanda fechada!</h2>
        <p class="text-gray-500 mb-6">
            Comanda {{ str_pad((string) $closed['comanda_number'], 3, '0', STR_PAD_LEFT) }} · {{ $closed['orders_count'] }} pedido(s) encerrados
        </p>

        <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
            <p class="text-sm text-gray-500">Total da conta</p>
            <p class="text-3xl font-bold text-emerald-600">R$ {{ number_format($closed['total'], 2, ',', '.') }}</p>
            @if (! empty($closed['payment_method']))
                <p class="text-sm text-gray-500 mt-2">
                    Pagamento: <strong class="text-gray-800">{{ \App\Support\PaymentMethod::label($closed['payment_method']) }}</strong>
                </p>
            @endif
        </div>

        <div class="space-y-3">
            <a href="{{ route('waiter.menu') }}"
                class="block w-full rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 transition">
                Voltar ao cardápio
            </a>
            <a href="{{ route('waiter.comandas.index') }}"
                class="block w-full rounded-2xl bg-white border border-gray-200 text-gray-700 font-semibold py-3 transition">
                Ver comandas abertas
            </a>
        </div>
    </div>
@endsection
