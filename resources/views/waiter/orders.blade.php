@extends('layouts.waiter')

@section('content')
    <div class="px-4 py-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Pedidos abertos</h2>
            <a href="{{ route('waiter.comandas.index') }}" class="text-sm text-emerald-600 font-semibold">Fechar comanda →</a>
        </div>

        <p class="text-xs text-gray-500 mb-4">
            Pedidos com mais de {{ \App\Models\Order::delayThresholdMinutes() }} min em preparo aparecem como <strong class="text-red-600">atrasados</strong>.
        </p>

        @php
            $statusLabels = [
                'pending' => ['label' => 'Recebido', 'class' => 'bg-yellow-100 text-yellow-800'],
                'preparing' => ['label' => 'Preparando', 'class' => 'bg-blue-100 text-blue-800'],
                'ready' => ['label' => 'Pronto', 'class' => 'bg-indigo-100 text-indigo-800'],
                'served' => ['label' => 'Entregue', 'class' => 'bg-teal-100 text-teal-800'],
            ];
        @endphp

        <div class="space-y-3">
            @forelse ($orders as $order)
                @php $status = $statusLabels[$order->status] ?? ['label' => $order->status, 'class' => 'bg-gray-100 text-gray-800']; @endphp
                <div @class([
                    'bg-white rounded-2xl border p-4',
                    'border-red-200 ring-1 ring-red-100' => $order->isDelayed(),
                    'border-gray-100' => ! $order->isDelayed(),
                ])>
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div>
                            <span class="font-bold text-lg">Comanda {{ $order->comanda_number ? str_pad((string) $order->comanda_number, 3, '0', STR_PAD_LEFT) : '—' }}</span>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $order->order_number }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-1.5 shrink-0">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $status['class'] }}">
                                {{ $status['label'] }}
                            </span>
                            <x-order-wait-time :order="$order" />
                        </div>
                    </div>
                    <p class="text-sm text-gray-500">
                        {{ $order->created_at->format('H:i') }} · {{ $order->items->sum('quantity') }} itens
                    </p>
                    <p class="text-sm font-semibold text-indigo-600 mt-1">R$ {{ number_format($order->total, 2, ',', '.') }}</p>
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        <x-order-serve-button :order="$order" />
                        @if ($order->comanda_number)
                            <a href="{{ route('waiter.comandas.show', $order->comanda_number) }}" class="text-xs text-emerald-600 font-semibold">
                                Ver conta da comanda →
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-12 text-gray-500">
                    <p class="text-lg">Nenhum pedido aberto no salão.</p>
                    <a href="{{ route('waiter.menu') }}" class="inline-block mt-4 text-indigo-600 font-semibold">Fazer pedido →</a>
                </div>
            @endforelse
        </div>
    </div>
@endsection
