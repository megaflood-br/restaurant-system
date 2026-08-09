@props(['order'])

@php
    $delayed = $order->isDelayed();
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
    'bg-red-100 text-red-700 ring-1 ring-red-200' => $delayed,
    'bg-gray-100 text-gray-600' => ! $delayed,
]) }}>
    @if ($delayed)
        <span aria-hidden="true">⚠</span>
        <span>Atrasado · {{ $order->waitingLabel() }}</span>
    @else
        <span>{{ $order->waitingLabel() }}</span>
    @endif
</span>
