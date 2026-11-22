<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Cobrança — {{ $customer->name }}</title>
    @include('partials.ngrok-skip-warning')
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Courier New', monospace; font-size: 12px; padding: 8px; color: #000; }
        .ticket { width: 72mm; margin: 0 auto; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .large { font-size: 15px; font-weight: bold; }
        .muted { font-size: 11px; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .order-block { margin-bottom: 6px; }
        .item { margin: 2px 0; }
        .actions { margin: 16px auto; width: 72mm; text-align: center; }
        .actions a, .actions button {
            display: inline-block;
            margin: 4px;
            padding: 8px 12px;
            font-family: sans-serif;
            font-size: 13px;
            border: 1px solid #333;
            background: #fff;
            cursor: pointer;
            text-decoration: none;
            color: #000;
        }
        @media print {
            @page { size: 80mm auto; margin: 2mm; }
            .actions { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="center bold large">{{ $restaurantName }}</div>
        <div class="center bold">*** COBRANÇA ***</div>
        <div class="divider"></div>

        <div class="bold">Cliente: {{ $customer->name }}</div>
        @if ($customer->phone)
            <div>Tel: {{ $customer->phone }}</div>
        @endif
        @if ($customer->formattedDeliveryAddress())
            <div class="muted">{{ $customer->formattedDeliveryAddress() }}</div>
        @endif
        <div>Emitido: {{ now()->format('d/m/Y H:i') }}</div>
        <div>Pedidos abertos: {{ $orders->count() }}</div>

        <div class="divider"></div>

        @foreach ($orders as $order)
            <div class="order-block">
                <div class="bold">{{ $order->order_number }}</div>
                <div class="muted">
                    {{ $order->created_at?->format('d/m/Y H:i') }}
                    ·
                    {{ match ($order->type) {
                        'delivery' => 'Delivery',
                        'takeaway' => 'Retirada',
                        'dine_in' => 'Salão'.($order->comanda_number ? ' #'.str_pad((string) $order->comanda_number, 3, '0', STR_PAD_LEFT) : ''),
                        default => $order->type,
                    } }}
                </div>

                @foreach ($order->items as $item)
                    <div class="item">
                        <div class="row">
                            <span>{{ $item->quantity }}x {{ $item->displayName() }}</span>
                            <span>R$ {{ number_format((float) $item->subtotal, 2, ',', '.') }}</span>
                        </div>
                        @if ($item->notes)
                            <div class="muted">> {{ $item->notes }}</div>
                        @endif
                    </div>
                @endforeach

                @if ((float) $order->delivery_fee > 0)
                    <div class="row muted">
                        <span>Taxa de entrega</span>
                        <span>R$ {{ number_format((float) $order->delivery_fee, 2, ',', '.') }}</span>
                    </div>
                @endif
                @if ((float) $order->discount > 0)
                    <div class="row muted">
                        <span>Desconto</span>
                        <span>- R$ {{ number_format((float) $order->discount, 2, ',', '.') }}</span>
                    </div>
                @endif

                <div class="row bold">
                    <span>Subtotal pedido</span>
                    <span>R$ {{ number_format((float) $order->total, 2, ',', '.') }}</span>
                </div>
            </div>
            <div class="divider"></div>
        @endforeach

        <div class="row large">
            <span>TOTAL</span>
            <span>R$ {{ number_format($grandTotal, 2, ',', '.') }}</span>
        </div>
        <div class="divider"></div>
        <div class="center">Aguardando pagamento</div>
        <div class="center muted">Obrigado!</div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
        <a href="{{ route('customers.show', $customer) }}">Voltar ao perfil</a>
    </div>

    <script>
        window.addEventListener('load', () => {
            const params = new URLSearchParams(window.location.search);
            if (params.get('autoprint') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
