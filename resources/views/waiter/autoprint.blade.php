<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imprimindo {{ $order->order_number }}</title>
    @include('partials.ngrok-skip-warning')
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
        .ticket { width: 72mm; max-width: 100%; margin: 0 auto; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .large { font-size: 16px; font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .item { margin-bottom: 6px; }
        .item-notes { font-size: 11px; padding-left: 8px; }
        .total { font-size: 14px; font-weight: bold; margin-top: 8px; }
        .notes { background: #f3f4f6; padding: 6px; margin: 8px 0; }
        @media print {
            body { padding: 0; }
            @page { size: 80mm auto; margin: 2mm; }
        }
    </style>
</head>
<body>
    @php
        $typeLabels = ['dine_in' => 'Salão', 'delivery' => 'Delivery', 'takeaway' => 'Retirada'];
        $hidePrices = config('printing.kitchen_hide_prices', false);
        $printAddress = filled($order->delivery_address)
            ? $order->delivery_address
            : trim(collect([
                $order->customer?->address,
                $order->customer?->neighborhood,
                $order->customer?->city,
                $order->customer?->state,
                $order->customer?->zip_code,
            ])->filter()->implode(', '));
    @endphp

    <div class="ticket">
        <div class="center bold large">{{ config('printing.restaurant_name') }}</div>
        <div class="center bold">*** COZINHA ***</div>
        <div class="divider"></div>

        <div><strong>Pedido:</strong> {{ $order->order_number }}</div>
        <div><strong>Data:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</div>
        <div><strong>Tipo:</strong> {{ $typeLabels[$order->type] ?? $order->type }}</div>
        @if ($order->type === 'dine_in')
            <div class="large">Comanda: {{ $order->comanda_number ? str_pad((string) $order->comanda_number, 3, '0', STR_PAD_LEFT) : '—' }}</div>
            @if ($order->user)
                <div><strong>Garçom:</strong> {{ $order->user->name }}</div>
            @endif
        @endif

        <div class="divider"></div>
        <div class="bold">{{ $hidePrices ? 'ITENS' : 'ITENS / VALOR' }}</div>
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
        <div class="center bold">*** ENTREGA / PAGAMENTO ***</div>
        <div class="divider"></div>

        @if ($order->type !== 'dine_in')
            <div><strong>Cliente:</strong> {{ $order->displayCustomerName() ?? '—' }}</div>
            @if ($order->customer_phone)
                <div><strong>Tel:</strong> {{ $order->customer_phone }}</div>
            @endif
        @endif

        @if ($order->type === 'delivery')
            @if ($order->deliveryArea)
                <div><strong>Bairro:</strong> {{ $order->deliveryArea->name }}</div>
            @endif
            <div class="large"><strong>Endereço:</strong> {{ $printAddress !== '' ? $printAddress : '(não informado)' }}</div>
        @elseif ($order->type === 'takeaway')
            <div>Retirada no local</div>
        @else
            <div>Consumo no salão</div>
        @endif

        <div class="large"><strong>Pagamento:</strong> {{ \App\Support\PaymentMethod::label($order->payment_method) }}</div>

        <div class="divider"></div>
        <div class="center" style="margin-top:8px;">{{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

    <script>
        const returnUrl = @json($returnUrl ?? route('waiter.success'));
        const isPopup = Boolean(window.opener && !window.opener.closed);
        let redirected = false;

        function goToSuccess() {
            if (redirected) {
                return;
            }

            redirected = true;

            if (isPopup) {
                window.close();
                return;
            }

            window.location.replace(returnUrl);
        }

        window.addEventListener('load', () => {
            window.print();
            setTimeout(goToSuccess, 800);
        });

        window.addEventListener('afterprint', goToSuccess);
    </script>
</body>
</html>
