<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrintJob;
use App\Support\PaymentMethod;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrderPrinterService
{
    public function isNetworkConfigured(): bool
    {
        return config('printing.driver') === 'network'
            && filled(config('printing.network.host'));
    }

    public function usesAgentQueue(): bool
    {
        return config('printing.driver') === 'agent';
    }

    public function usesServerSidePrint(): bool
    {
        return (bool) config('printing.enabled')
            && in_array(config('printing.driver'), ['network', 'agent'], true);
    }

    public function shouldPrintOnCreate(): bool
    {
        return (bool) config('printing.enabled')
            && (bool) config('printing.auto_print_on_create');
    }

    public function shouldPrintOnPreparing(): bool
    {
        return (bool) config('printing.enabled')
            && (bool) config('printing.print_on_preparing', true);
    }

    public function maybePrintOnCreate(Order $order): bool
    {
        if (! $this->shouldPrintOnCreate()) {
            return false;
        }

        return $this->dispatchKitchenPrint($order);
    }

    public function maybePrintOnStatusChange(Order $order, string $previousStatus, string $newStatus): bool
    {
        if (! $this->shouldPrintOnPreparing()) {
            return false;
        }

        if ($newStatus !== 'preparing' || $previousStatus === 'preparing') {
            return false;
        }

        return $this->dispatchKitchenPrint($order);
    }

    public function dispatchKitchenPrint(Order $order): bool
    {
        if (! $this->usesServerSidePrint()) {
            return false;
        }

        $order->loadMissing('items.product', 'customer', 'deliveryArea', 'user');
        $text = $this->buildReceiptText($order, 'kitchen');

        if ($this->usesAgentQueue()) {
            $this->enqueueJob('kitchen', $text, $order->id);

            return true;
        }

        return $this->sendToNetworkPrinter($text);
    }

    public function dispatchComandaBill(array $bill): bool
    {
        if (! $this->usesServerSidePrint()) {
            return false;
        }

        $text = $this->buildComandaBillText($bill);

        if ($this->usesAgentQueue()) {
            $this->enqueueJob('comanda_bill', $text, null);

            return true;
        }

        return $this->sendToNetworkPrinter($text);
    }

    /** @deprecated Prefer dispatchKitchenPrint() */
    public function printOrder(Order $order, string $template = 'kitchen'): bool
    {
        if ($template !== 'kitchen') {
            if (! $this->usesServerSidePrint()) {
                return false;
            }

            $order->loadMissing('items.product', 'customer', 'deliveryArea', 'user');
            $text = $this->buildReceiptText($order, $template);

            if ($this->usesAgentQueue()) {
                $this->enqueueJob($template, $text, $order->id);

                return true;
            }

            return $this->sendToNetworkPrinter($text);
        }

        return $this->dispatchKitchenPrint($order);
    }

    /** @deprecated Prefer dispatchComandaBill() */
    public function printComandaBill(array $bill): bool
    {
        return $this->dispatchComandaBill($bill);
    }

    public function printTestPage(): bool
    {
        if (! $this->usesServerSidePrint()) {
            throw new RuntimeException(
                'Escolha o modo "Rede IP" ou "Agente local", salve e tente de novo.'
            );
        }

        $width = (int) config('printing.paper_width', 32);
        $host = (string) (config('printing.network.host') ?: 'agente-local');
        $port = (int) config('printing.network.port', 9100);

        $text = implode("\n", [
            str_repeat('=', $width),
            $this->center('TESTE DE IMPRESSAO', $width),
            $this->center((string) config('printing.restaurant_name'), $width),
            $this->center($this->usesAgentQueue() ? 'via agente local' : "{$host}:{$port}", $width),
            now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
            str_repeat('=', $width),
            $this->center('Se voce leu isto, OK!', $width),
            '',
        ]);

        if ($this->usesAgentQueue()) {
            $this->enqueueJob('test', $text, null);

            return true;
        }

        if (! $this->isNetworkConfigured()) {
            throw new RuntimeException(
                'Impressora de rede não configurada. Informe o IP da impressora, salve e teste novamente.'
            );
        }

        return $this->sendToNetworkPrinter($text);
    }

    public function enqueueJob(string $type, string $payload, ?int $orderId = null): PrintJob
    {
        return PrintJob::create([
            'type' => $type,
            'order_id' => $orderId,
            'payload' => $payload,
            'status' => PrintJob::STATUS_PENDING,
        ]);
    }

    public function buildReceiptText(Order $order, string $template = 'kitchen'): string
    {
        $width = (int) config('printing.paper_width', 32);
        $lines = [];
        $typeLabels = ['dine_in' => 'Salao', 'delivery' => 'Delivery', 'takeaway' => 'Retirada'];
        $hidePrices = $template === 'kitchen' && config('printing.kitchen_hide_prices', true);

        $lines[] = str_repeat('=', $width);
        $lines[] = $this->center((string) config('printing.restaurant_name'), $width);
        $lines[] = $template === 'kitchen' ? $this->center('*** COZINHA ***', $width) : $this->center('*** CLIENTE ***', $width);
        $lines[] = str_repeat('=', $width);
        $lines[] = 'Pedido: '.$order->order_number;
        $lines[] = 'Data: '.$order->created_at->format('d/m/Y H:i');
        $lines[] = 'Tipo: '.($typeLabels[$order->type] ?? $order->type);
        $lines[] = 'Pagamento: '.PaymentMethod::label($order->payment_method);

        if ($order->type === 'dine_in') {
            $lines[] = 'Comanda: '.($order->comanda_number ?? '-');
            if ($order->user) {
                $lines[] = 'Garcom: '.$order->user->name;
            }
        } else {
            $lines[] = 'Cliente: '.($order->displayCustomerName() ?? '-');
            if ($order->customer_phone) {
                $lines[] = 'Tel: '.$order->customer_phone;
            }
            if ($order->type === 'delivery') {
                if ($order->deliveryArea) {
                    $lines[] = 'Bairro: '.$order->deliveryArea->name;
                }

                $address = $this->deliveryAddressForPrint($order);
                $lines[] = str_repeat('-', $width);
                $lines[] = $this->center('*** ENTREGA ***', $width);
                if ($address !== '') {
                    foreach ($this->wrapLabelledText('Endereco', $address, $width) as $line) {
                        $lines[] = $line;
                    }
                } else {
                    $lines[] = 'Endereco: (nao informado)';
                }
            }
        }

        $lines[] = str_repeat('-', $width);
        $lines[] = $hidePrices ? 'ITENS' : 'ITENS / VALOR';
        $lines[] = str_repeat('-', $width);

        foreach ($order->items as $item) {
            $lines[] = $item->quantity.'x '.$item->displayName();

            if (! $hidePrices) {
                $lines[] = '   R$ '.number_format((float) $item->subtotal, 2, ',', '.');
            }

            if ($item->notes) {
                $lines[] = '   > '.$item->notes;
            }
        }

        if ($order->notes) {
            $lines[] = str_repeat('-', $width);
            $lines[] = 'OBS: '.$order->notes;
        }

        if (! $hidePrices) {
            $lines[] = str_repeat('-', $width);
            if ($order->delivery_fee > 0) {
                $lines[] = $this->padLine('Taxa entrega', 'R$ '.number_format((float) $order->delivery_fee, 2, ',', '.'), $width);
            }
            $lines[] = $this->padLine('TOTAL', 'R$ '.number_format((float) $order->total, 2, ',', '.'), $width);
        }

        $lines[] = str_repeat('=', $width);
        $lines[] = '';

        return implode("\n", array_map([$this, 'sanitizeLine'], $lines));
    }

    public function buildComandaBillText(array $bill): string
    {
        $width = (int) config('printing.paper_width', 32);
        $lines = [];

        $lines[] = str_repeat('=', $width);
        $lines[] = $this->center((string) config('printing.restaurant_name'), $width);
        $lines[] = $this->center('*** CONTA FECHADA ***', $width);
        $lines[] = str_repeat('=', $width);
        $lines[] = 'Comanda: '.($bill['comanda_number'] ?? '-');
        $lines[] = 'Pagamento: '.PaymentMethod::label($bill['payment_method'] ?? null);
        $lines[] = 'Data: '.now()->format('d/m/Y H:i');
        $lines[] = 'Pedidos: '.($bill['orders_count'] ?? 0);
        $lines[] = str_repeat('-', $width);
        $lines[] = 'ITENS / VALOR';
        $lines[] = str_repeat('-', $width);

        foreach ($bill['items'] as $item) {
            $lines[] = $item['quantity'].'x '.$item['name'];
            $lines[] = '   R$ '.number_format((float) $item['subtotal'], 2, ',', '.');

            if (! empty($item['notes'])) {
                $lines[] = '   > '.$item['notes'];
            }
        }

        $lines[] = str_repeat('-', $width);
        $lines[] = $this->padLine('TOTAL', 'R$ '.number_format((float) $bill['total'], 2, ',', '.'), $width);
        $lines[] = str_repeat('=', $width);
        $lines[] = $this->center('Obrigado!', $width);
        $lines[] = '';

        return implode("\n", array_map([$this, 'sanitizeLine'], $lines));
    }

    public function connectionFailureMessage(string $host, int $port, int $errno, string $errstr): string
    {
        $detail = trim($errstr) !== '' ? $errstr : "erro {$errno}";

        return "Nao foi possivel conectar a impressora em {$host}:{$port} ({$detail}). "
            .'O servidor do sistema precisa alcancar esse IP na rede local. '
            .'Se o painel roda na nuvem/VPS e a impressora so existe no Wi-Fi do restaurante (ex.: 192.168.1.100), '
            .'use o modo Agente local (recomendado) ou Navegador.';
    }

    /** Build raw ESC/POS bytes for an agent or direct socket send. */
    public function buildEscPosPayload(string $text): string
    {
        $payload = "\x1B\x40";
        $payload .= "\x1B\x74\x10";
        $payload .= "\x1B\x61\x00";
        $payload .= str_replace(["\r\n", "\r"], "\n", $text);
        if (! str_ends_with($payload, "\n")) {
            $payload .= "\n";
        }
        $payload .= "\n\n\n";
        $payload .= "\x1D\x56\x41\x03";

        return $payload;
    }

    private function sendToNetworkPrinter(string $text): bool
    {
        $host = trim((string) config('printing.network.host'));
        $port = (int) config('printing.network.port', 9100);
        $timeout = (int) config('printing.network.timeout', 5);

        if ($host === '') {
            throw new RuntimeException('IP da impressora nao configurado.');
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (! $socket) {
            Log::error('Printer connection failed', compact('host', 'port', 'errno', 'errstr'));

            throw new RuntimeException($this->connectionFailureMessage($host, $port, (int) $errno, (string) $errstr));
        }

        stream_set_timeout($socket, max(1, $timeout));

        $payload = $this->buildEscPosPayload($text);
        $written = @fwrite($socket, $payload);
        $meta = stream_get_meta_data($socket);
        fclose($socket);

        if ($written === false || $written === 0 || ($meta['timed_out'] ?? false)) {
            Log::error('Printer write failed', [
                'host' => $host,
                'port' => $port,
                'written' => $written,
                'timed_out' => $meta['timed_out'] ?? false,
            ]);

            throw new RuntimeException("Conectou em {$host}:{$port}, mas falhou ao enviar os dados para impressao.");
        }

        return true;
    }

    public function deliveryAddressForPrint(Order $order): string
    {
        if (filled($order->delivery_address)) {
            return trim((string) $order->delivery_address);
        }

        $order->loadMissing('customer');
        $customer = $order->customer;

        if (! $customer) {
            return '';
        }

        $parts = array_filter([
            $customer->address,
            $customer->neighborhood,
            $customer->city,
            $customer->state,
            $customer->zip_code,
        ], fn ($part) => filled($part));

        return implode(', ', $parts);
    }

    /** @return list<string> */
    public function wrapLabelledText(string $label, string $text, int $width): array
    {
        $prefix = $label.': ';
        $text = trim($text);

        if ($text === '') {
            return [$prefix.'-'];
        }

        $firstWidth = max(8, $width - strlen($this->sanitizeLine($prefix)));
        $chunks = $this->wrapText($text, $firstWidth, max(8, $width));
        $lines = [];

        foreach ($chunks as $index => $chunk) {
            $lines[] = $index === 0 ? $prefix.$chunk : $chunk;
        }

        return $lines;
    }

    /** @return list<string> */
    private function wrapText(string $text, int $firstWidth, int $width): array
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);
        $words = explode(' ', $text);
        $lines = [];
        $current = '';
        $limit = $firstWidth;

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (strlen($this->sanitizeLine($candidate)) <= $limit) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            $current = $word;
            $limit = $width;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines !== [] ? $lines : [$text];
    }

    private function center(string $text, int $width): string
    {
        $text = $this->sanitizeLine($text);

        if (strlen($text) >= $width) {
            return substr($text, 0, $width);
        }

        $padding = (int) floor(($width - strlen($text)) / 2);

        return str_repeat(' ', max(0, $padding)).$text;
    }

    private function padLine(string $left, string $right, int $width): string
    {
        $left = $this->sanitizeLine($left);
        $right = $this->sanitizeLine($right);
        $spaces = $width - strlen($left) - strlen($right);

        if ($spaces < 1) {
            return $left.' '.$right;
        }

        return $left.str_repeat(' ', $spaces).$right;
    }

    private function sanitizeLine(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;

        return preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text;
    }
}
