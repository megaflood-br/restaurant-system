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
        $hidePrices = config('printing.kitchen_hide_prices', true);
    @endphp

    <div class="ticket">
        <div class="center bold large">{{ config('printing.restaurant_name') }}</div>
        <div class="center bold">*** COZINHA ***</div>
        <div class="divider"></div>

        <div><strong>Pedido:</strong> {{ $order->order_number }}</div>
        <div><strong>Data:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</div>
        <div><strong>Tipo:</strong> {{ $typeLabels[$order->type] ?? $order->type }}</div>
        <div class="large">Comanda: {{ $order->comanda_number ? str_pad((string) $order->comanda_number, 3, '0', STR_PAD_LEFT) : '—' }}</div>
        @if ($order->user)
            <div><strong>Garçom:</strong> {{ $order->user->name }}</div>
        @endif

        <div class="divider"></div>

        @foreach ($order->items as $item)
            <div class="item">
                <div class="large">{{ $item->quantity }}x {{ $item->displayName() }}</div>
                @if ($item->notes)
                    <div class="item-notes">> {{ $item->notes }}</div>
                @endif
            </div>
        @endforeach

        @if ($order->notes)
            <div class="notes"><strong>Obs:</strong> {{ $order->notes }}</div>
        @endif

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
