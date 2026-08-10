<?php

namespace App\Services;

use App\Models\Order;
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

    public function printOrder(Order $order, string $template = 'kitchen'): bool
    {
        if (! config('printing.enabled')) {
            return false;
        }

        $order->loadMissing('items.product', 'customer', 'deliveryArea', 'user');

        if ($this->isNetworkConfigured()) {
            return $this->sendToNetworkPrinter($this->buildReceiptText($order, $template));
        }

        return false;
    }

    public function printTestPage(): bool
    {
        if (! $this->isNetworkConfigured()) {
            throw new RuntimeException(
                'Impressora de rede não configurada. Em Configurações → Impressão, escolha o modo "Rede IP", informe o IP e salve.'
            );
        }

        $width = (int) config('printing.paper_width', 32);
        $host = (string) config('printing.network.host');
        $port = (int) config('printing.network.port', 9100);

        $text = implode("\n", [
            str_repeat('=', $width),
            $this->center('TESTE DE IMPRESSAO', $width),
            $this->center((string) config('printing.restaurant_name'), $width),
            $this->center("{$host}:{$port}", $width),
            now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
            str_repeat('=', $width),
            $this->center('Se voce leu isto, OK!', $width),
            '',
        ]);

        return $this->sendToNetworkPrinter($text);
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
                if ($order->delivery_address) {
                    $lines[] = 'Endereco: '.$order->delivery_address;
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

    public function printComandaBill(array $bill): bool
    {
        if (! config('printing.enabled') || ! $this->isNetworkConfigured()) {
            return false;
        }

        return $this->sendToNetworkPrinter($this->buildComandaBillText($bill));
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
            .'use o modo Navegador ou hospede o sistema na mesma rede da impressora.';
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

        // ESC/POS raw payload for EPSON-compatible 80mm printers (e.g. 80-VI-UL).
        $payload = "\x1B\x40"; // Initialize
        $payload .= "\x1B\x74\x10"; // Code page 16 (WPC1252 / common on these units)
        $payload .= "\x1B\x61\x00"; // Left align
        $payload .= str_replace(["\r\n", "\r"], "\n", $text);
        if (! str_ends_with($payload, "\n")) {
            $payload .= "\n";
        }
        $payload .= "\n\n\n";
        $payload .= "\x1D\x56\x41\x03"; // Partial cut with feed (GS V A n)

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
