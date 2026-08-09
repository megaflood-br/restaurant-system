<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imprimir {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 8px;
        }
        .ticket {
            width: 72mm;
            max-width: 100%;
            margin: 0 auto;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .large { font-size: 16px; font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .item { margin-bottom: 6px; }
        .item-notes { font-size: 11px; padding-left: 8px; }
        .total { font-size: 14px; font-weight: bold; margin-top: 8px; }
        .notes { background: #f3f4f6; padding: 6px; margin: 8px 0; }
        .no-print {
            width: 72mm;
            margin: 12px auto;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .no-print button, .no-print a {
            flex: 1;
            min-width: 120px;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 13px;
        }
        .btn-print { background: #4f46e5; color: #fff; }
        .btn-back { background: #e5e7eb; color: #111; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            @page { size: 80mm auto; margin: 2mm; }
        }
    </style>
</head>
<body>
    @php
        $typeLabels = ['dine_in' => 'Salão', 'delivery' => 'Delivery', 'takeaway' => 'Retirada'];
        $hidePrices = $template === 'kitchen' && config('printing.kitchen_hide_prices', true);
    @endphp

    <div class="ticket">
        <div class="center bold large">{{ config('printing.restaurant_name') }}</div>
        <div class="center bold">{{ $template === 'kitchen' ? '*** COZINHA ***' : '*** VIA CLIENTE ***' }}</div>
        <div class="divider"></div>

        <div><strong>Pedido:</strong> {{ $order->order_number }}</div>
        <div><strong>Data:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</div>
        <div><strong>Tipo:</strong> {{ $typeLabels[$order->type] ?? $order->type }}</div>

        @if ($order->type === 'dine_in')
            <div class="large">Comanda: {{ $order->comanda_number ? str_pad((string) $order->comanda_number, 3, '0', STR_PAD_LEFT) : '—' }}</div>
            @if ($order->user)
                <div><strong>Garçom:</strong> {{ $order->user->name }}</div>
            @endif
        @else
            <div><strong>Cliente:</strong> {{ $order->displayCustomerName() ?? '—' }}</div>
            @if ($order->customer_phone)
                <div><strong>Tel:</strong> {{ $order->customer_phone }}</div>
            @endif
            @if ($order->type === 'delivery')
                @if ($order->deliveryArea)
                    <div><strong>Bairro:</strong> {{ $order->deliveryArea->name }}</div>
                @endif
                @if ($order->delivery_address)
                    <div><strong>Endereço:</strong> {{ $order->delivery_address }}</div>
                @endif
            @endif
        @endif

        <div class="divider"></div>

        @foreach ($order->items as $item)
            <div class="item">
                <div class="large">{{ $item->quantity }}x {{ $item->displayName() }}</div>
                @if (! $hidePrices)
                    <div>R$ {{ number_format($item->unit_price, 2, ',', '.') }} = R$ {{ number_format($item->subtotal, 2, ',', '.') }}</div>
                @endif
                @if ($item->notes)
                    <div class="item-notes">> {{ $item->notes }}</div>
                @endif
            </div>
        @endforeach

        @if ($order->notes)
            <div class="notes"><strong>Obs:</strong> {{ $order->notes }}</div>
        @endif

        @if (! $hidePrices)
            <div class="divider"></div>
            @if ($order->delivery_fee > 0)
                <div>Taxa entrega: R$ {{ number_format($order->delivery_fee, 2, ',', '.') }}</div>
            @endif
            <div class="total">TOTAL: R$ {{ number_format($order->total, 2, ',', '.') }}</div>
        @endif

        <div class="divider"></div>
        <div class="center" style="margin-top:8px;">{{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

    <div class="no-print">
        <button type="button" class="btn-print" onclick="window.print()">Imprimir</button>
        <a href="{{ route('orders.print', ['order' => $order, 'template' => $template === 'kitchen' ? 'receipt' : 'kitchen']) }}" class="btn-back">
            {{ $template === 'kitchen' ? 'Via cliente' : 'Via cozinha' }}
        </a>
        <a href="{{ route('orders.show', $order) }}" class="btn-back">Voltar</a>
    </div>

    @if ($autoprint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
