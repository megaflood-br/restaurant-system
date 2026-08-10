<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\WhatsAppMessage;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppBotService
{
    public function __construct(
        private readonly WhatsAppService $whatsAppService,
    ) {}

    public function process(string $phone, string $text, ?string $pushName = null): void
    {
        if (! config('evolution.auto_reply') || ! config('evolution.enabled')) {
            return;
        }

        $command = mb_strtolower(trim($text));
        $customer = $this->resolveCustomer($phone, $pushName);

        if ($this->matches($command, ['oi', 'olá', 'ola', 'ajuda', 'help', 'menu', 'inicio', 'início', '0'])) {
            $this->clearSession($phone);
            $this->reply($phone, $this->welcomeMessage(), $customer);

            return;
        }

        if ($this->matches($command, ['cardapio', 'cardápio', '1'])) {
            $this->startOrdering($phone, $customer);

            return;
        }

        if ($this->matches($command, ['status', '3'])) {
            $this->sendOrderStatus($phone, $customer);

            return;
        }

        if ($this->matches($command, ['cancelar', 'sair', 'cancel'])) {
            $this->clearSession($phone);
            $this->reply($phone, "Pedido cancelado. Envie *CARDAPIO* quando quiser pedir novamente.", $customer);

            return;
        }

        if ($this->matches($command, ['finalizar', 'fechar', 'concluir', '2'])) {
            $this->handleFinalize($phone, $customer);

            return;
        }

        if ($this->matches($command, ['confirmar', 'sim', 'ok'])) {
            $session = $this->getSession($phone);

            if (($session['state'] ?? '') === 'confirming') {
                $this->createOrder($phone, $customer, $session);

                return;
            }
        }

        if ($this->tryAddItems($phone, $text, $customer)) {
            return;
        }

        $this->reply($phone, $this->welcomeMessage(), $customer);
    }

    private function welcomeMessage(): string
    {
        return implode("\n", [
            '🍽️ *Bem-vindo ao Restaurant System!*',
            '',
            'Comandos disponíveis:',
            '• *CARDAPIO* — ver produtos',
            '• *1 2* — pedir (código + quantidade)',
            '• *FINALIZAR* — concluir pedido',
            '• *STATUS* — último pedido',
            '• *CANCELAR* — cancelar pedido atual',
            '',
            'Exemplo: envie *CARDAPIO*, depois *1 2* (2x item 1) e *FINALIZAR*.',
        ]);
    }

    private function startOrdering(string $phone, ?Customer $customer): void
    {
        $this->setSession($phone, [
            'state' => 'ordering',
            'cart' => [],
        ]);

        $this->reply($phone, $this->buildMenuMessage(), $customer);
    }

    private function buildMenuMessage(): string
    {
        $products = $this->menuProducts();
        $lines = ['📋 *CARDÁPIO*', ''];

        $currentCategory = null;

        foreach ($products as $index => $product) {
            $categoryName = $product->category->name;

            if ($categoryName !== $currentCategory) {
                $currentCategory = $categoryName;
                $lines[] = "*{$categoryName}*";
            }

            $number = $index + 1;
            $price = number_format((float) $product->price, 2, ',', '.');
            $lines[] = "{$number}. {$product->name} — R$ {$price}";
        }

        $lines[] = '';
        $lines[] = 'Para pedir, envie: *CODIGO QUANTIDADE*';
        $lines[] = 'Ex: *1 2* (2x item 1)';
        $lines[] = 'Quando terminar, envie *FINALIZAR*';

        return implode("\n", $lines);
    }

    private function tryAddItems(string $phone, string $text, ?Customer $customer): bool
    {
        $session = $this->getSession($phone);

        if (($session['state'] ?? '') !== 'ordering') {
            if ($this->parseItemLines($text) !== []) {
                $this->startOrdering($phone, $customer);
                $session = $this->getSession($phone);
            } else {
                return false;
            }
        }

        $items = $this->parseItemLines($text);

        if ($items === []) {
            return false;
        }

        $products = $this->menuProducts();
        $cart = $session['cart'] ?? [];
        $added = [];

        foreach ($items as $item) {
            $product = $products->get($item['index']);

            if (! $product) {
                $this->reply($phone, "Item *{$item['index']}* não encontrado. Envie *CARDAPIO* para ver os códigos.", $customer);

                return true;
            }

            $productId = $product->id;
            $found = false;

            foreach ($cart as &$cartItem) {
                if ($cartItem['product_id'] === $productId) {
                    $cartItem['quantity'] += $item['quantity'];
                    $found = true;
                    break;
                }
            }
            unset($cartItem);

            if (! $found) {
                $cart[] = [
                    'product_id' => $productId,
                    'quantity' => $item['quantity'],
                ];
            }

            $added[] = "{$item['quantity']}x {$product->name}";
        }

        $this->setSession($phone, [
            'state' => 'ordering',
            'cart' => $cart,
        ]);

        $this->reply($phone, implode("\n", [
            '✅ Adicionado ao pedido:',
            ...array_map(fn ($line) => "• {$line}", $added),
            '',
            $this->cartSummary($cart),
            '',
            'Continue enviando itens ou digite *FINALIZAR*.',
        ]), $customer);

        return true;
    }

    private function handleFinalize(string $phone, ?Customer $customer): void
    {
        $session = $this->getSession($phone);
        $cart = $session['cart'] ?? [];

        if ($cart === []) {
            $this->reply($phone, "Seu carrinho está vazio. Envie *CARDAPIO* para começar.", $customer);

            return;
        }

        $this->setSession($phone, [
            'state' => 'confirming',
            'cart' => $cart,
        ]);

        $this->reply($phone, implode("\n", [
            '🛒 *Resumo do pedido*',
            '',
            $this->cartSummary($cart, detailed: true),
            '',
            'Responda *CONFIRMAR* para enviar ou *CANCELAR* para desistir.',
        ]), $customer);
    }

    private function createOrder(string $phone, ?Customer $customer, array $session): void
    {
        $cart = $session['cart'] ?? [];

        if ($cart === []) {
            $this->reply($phone, "Nenhum item no pedido. Envie *CARDAPIO* para começar.", $customer);

            return;
        }

        try {
            $order = DB::transaction(function () use ($cart, $customer, $phone) {
                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'customer_id' => $customer?->id,
                    'type' => 'takeaway',
                    'customer_name' => $customer?->name ?? 'Cliente WhatsApp',
                    'customer_phone' => $customer?->phone ?? PhoneNumber::formatDisplay($phone) ?? $phone,
                    'notes' => 'Pedido via WhatsApp',
                    'status' => 'pending',
                    'user_id' => null,
                ]);

                $total = 0;

                foreach ($cart as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $subtotal = $product->price * $item['quantity'];

                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price,
                        'subtotal' => $subtotal,
                    ]);

                    $total += $subtotal;
                }

                $order->update(['total' => $total]);

                return $order->fresh('items.product');
            });

            try {
                app(OrderPrinterService::class)->dispatchKitchenPrint($order);
            } catch (\Throwable) {
                // best-effort
            }

            $this->clearSession($phone);

            $this->reply($phone, implode("\n", [
                "✅ *Pedido confirmado!*",
                '',
                "Número: *{$order->order_number}*",
                'Total: R$ '.number_format((float) $order->total, 2, ',', '.'),
                'Status: Pendente',
                '',
                'Acompanhe com *STATUS* a qualquer momento.',
                'Obrigado pela preferência! 🍽️',
            ]), $customer, $order);
        } catch (\Throwable $exception) {
            Log::error('WhatsApp order creation failed', ['error' => $exception->getMessage()]);
            $this->reply($phone, 'Não foi possível criar o pedido. Tente novamente ou entre em contato conosco.', $customer);
        }
    }

    private function sendOrderStatus(string $phone, ?Customer $customer): void
    {
        $query = Order::query()->latest();

        if ($customer) {
            $query->where('customer_id', $customer->id);
        } else {
            $normalized = PhoneNumber::normalize($phone);
            $query->where('customer_phone', 'like', '%'.substr($normalized ?? $phone, -8).'%');
        }

        $order = $query->first();

        if (! $order) {
            $this->reply($phone, 'Nenhum pedido encontrado. Envie *CARDAPIO* para fazer seu primeiro pedido.', $customer);

            return;
        }

        $statusLabels = [
            'pending' => 'Pendente',
            'preparing' => 'Preparando',
            'ready' => 'Pronto',
            'served' => 'Entregue',
            'delivered' => 'Conta fechada',
            'cancelled' => 'Cancelado',
        ];

        $this->reply($phone, implode("\n", [
            "📦 *Pedido {$order->order_number}*",
            'Status: '.($statusLabels[$order->status] ?? $order->status),
            'Total: R$ '.number_format((float) $order->total, 2, ',', '.'),
            'Data: '.$order->created_at->format('d/m/Y H:i'),
        ]), $customer, $order);
    }

    private function cartSummary(array $cart, bool $detailed = false): string
    {
        $products = Product::whereIn('id', collect($cart)->pluck('product_id'))->get()->keyBy('id');
        $lines = [];
        $total = 0;

        foreach ($cart as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                continue;
            }

            $subtotal = $product->price * $item['quantity'];
            $total += $subtotal;
            $price = number_format((float) $product->price, 2, ',', '.');

            if ($detailed) {
                $lines[] = "• {$item['quantity']}x {$product->name} — R$ {$price} = R$ ".number_format($subtotal, 2, ',', '.');
            } else {
                $lines[] = "• {$item['quantity']}x {$product->name}";
            }
        }

        $lines[] = '';
        $lines[] = '*Total: R$ '.number_format($total, 2, ',', '.').'*';

        return implode("\n", $lines);
    }

    /** @return array<int, array{index: int, quantity: int}> */
    private function parseItemLines(string $text): array
    {
        $items = [];
        $lines = preg_split('/[\n,;]+/', $text) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s*[xX*×]?\s*(\d+)$/', $line, $matches)) {
                $items[] = ['index' => (int) $matches[1], 'quantity' => max(1, (int) $matches[2])];
            } elseif (preg_match('/^(\d+)$/', $line, $matches)) {
                $items[] = ['index' => (int) $matches[1], 'quantity' => 1];
            }
        }

        return $items;
    }

    private function menuProducts(): Collection
    {
        return Product::with('category')
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn ($product) => $product->category->name.'|'.$product->name)
            ->values();
    }

    private function resolveCustomer(string $phone, ?string $pushName): ?Customer
    {
        $customer = WhatsAppMessage::findCustomerByPhone($phone);

        if ($customer || ! $pushName) {
            return $customer;
        }

        return Customer::create([
            'name' => $pushName,
            'phone' => PhoneNumber::formatDisplay($phone) ?? $phone,
            'is_active' => true,
            'notes' => 'Cadastrado automaticamente via WhatsApp',
        ]);
    }

    private function reply(string $phone, string $message, ?Customer $customer = null, ?Order $order = null): void
    {
        try {
            $this->whatsAppService->sendToPhone($phone, $message, $customer, $order, null, logInteraction: false);
        } catch (\Throwable $exception) {
            Log::error('WhatsApp bot reply failed', [
                'phone' => $phone,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function getSession(string $phone): array
    {
        return Cache::get($this->sessionKey($phone), []);
    }

    private function setSession(string $phone, array $data): void
    {
        Cache::put(
            $this->sessionKey($phone),
            $data,
            now()->addMinutes(config('evolution.session_ttl_minutes', 30))
        );
    }

    private function clearSession(string $phone): void
    {
        Cache::forget($this->sessionKey($phone));
    }

    private function sessionKey(string $phone): string
    {
        return 'whatsapp_session:'.(PhoneNumber::normalize($phone) ?? $phone);
    }

    /** @param  array<int, string>  $commands */
    private function matches(string $command, array $commands): bool
    {
        return in_array($command, $commands, true);
    }
}
