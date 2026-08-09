<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Conta Comanda {{ $comanda }}</title>
    @include('partials.ngrok-skip-warning')
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Courier New', monospace; font-size: 12px; padding: 8px; }
        .ticket { width: 72mm; margin: 0 auto; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .large { font-size: 16px; font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        @media print { @page { size: 80mm auto; margin: 2mm; } }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="center bold large">{{ config('printing.restaurant_name') }}</div>
        <div class="center bold">*** CONTA FECHADA ***</div>
        <div class="divider"></div>
        <div class="large">Comanda: {{ str_pad((string) $comanda, 3, '0', STR_PAD_LEFT) }}</div>
        <div>Data: {{ now()->format('d/m/Y H:i') }}</div>
        <div>Pedidos: {{ $bill['orders_count'] }}</div>
        @if (! empty($bill['payment_method']))
            <div>Pagamento: {{ \App\Support\PaymentMethod::label($bill['payment_method']) }}</div>
        @endif
        <div class="divider"></div>
        @foreach ($bill['items'] as $item)
            <div>{{ $item['quantity'] }}x {{ $item['name'] }}</div>
            <div>R$ {{ number_format($item['subtotal'], 2, ',', '.') }}</div>
            @if ($item['notes'])
                <div>> {{ $item['notes'] }}</div>
            @endif
        @endforeach
        <div class="divider"></div>
        <div class="large">TOTAL: R$ {{ number_format($bill['total'], 2, ',', '.') }}</div>
        <div class="divider"></div>
        <div class="center">Obrigado!</div>
    </div>
    <script>
        const successUrl = @json(route('waiter.comandas.closed'));
        function goToSuccess() { window.location.replace(successUrl); }
        window.addEventListener('load', () => { window.print(); setTimeout(goToSuccess, 800); });
        window.addEventListener('afterprint', goToSuccess);
    </script>
</body>
</html>
