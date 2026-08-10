@php
    $serverSidePrint = in_array(config('printing.driver'), ['network', 'agent'], true)
        && (config('printing.driver') === 'agent' || filled(config('printing.network.host')));
    $linkClass = $linkClass ?? 'text-gray-600 hover:text-gray-800 text-sm';
    $buttonClass = $buttonClass ?? $linkClass;
@endphp

@if ($serverSidePrint)
    <form method="POST" action="{{ route('orders.print.network', $order) }}" class="inline">
        @csrf
        <button type="submit" class="{{ $buttonClass }}">Imprimir</button>
    </form>
@else
    <a href="{{ route('orders.print', $order) }}" target="_blank" class="{{ $linkClass }}">Imprimir</a>
@endif
