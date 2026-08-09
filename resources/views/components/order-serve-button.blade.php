@props(['order'])

@if ($order->canBeMarkedServed())
    <form method="POST" action="{{ route('waiter.orders.serve', $order) }}" class="inline">
        @csrf
        @method('PATCH')
        <button type="submit"
            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold px-3 py-2 transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Entregue ao cliente
        </button>
    </form>
@elseif (in_array($order->status, ['pending', 'preparing'], true) && $order->type === 'dine_in')
    <p class="text-xs text-gray-400 italic">Aguardando cozinha marcar como <strong class="text-indigo-600">Pronto</strong></p>
@endif
