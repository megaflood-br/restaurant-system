<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\WhatsAppMessage;
use App\Support\PaymentMethod;
use App\Support\PhoneNumber;
use App\Support\WeeklyMenuImages;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationalWhatsAppBotService
{
    public function __construct(
        private readonly WhatsAppService $whatsAppService,
        private readonly DeliveryFeeService $deliveryFeeService,
    ) {}

    public function process(string $phone, string $text, ?string $pushName = null, array $payload = []): void
    {
        if (! config('whatsapp_agent.enabled') || ! config('evolution.enabled')) {
            return;
        }

        $customer = $this->resolveCustomer($phone, $pushName);
        $command = mb_strtolower(trim($text));

        if ($this->matchesIntent($command, ['cardapio', 'cardápio', 'menu'])) {
            $this->sendMenuImage($phone, $customer);

            return;
        }

        if ($this->matchesIntent($command, ['cancelar', 'sair', 'cancel'])) {
            $this->clearSession($phone);
            $this->replyText($phone, $this->message('cancel_message'), $customer);

            return;
        }

        if ($this->matchesIntent($command, ['status'])) {
            $this->sendOrderStatus($phone, $customer);

            return;
        }

        $session = $this->getSession($phone);
        $state = $session['state'] ?? 'welcome';

        if ($state === 'welcome' || $this->matchesIntent($command, ['oi', 'olá', 'ola', 'help', 'inicio', 'início', 'bom dia', 'boa tarde', 'boa noite'])) {
            $this->handleWelcome($phone, $text, $customer);

            return;
        }

        match ($state) {
            'ordering' => $this->handleOrdering($phone, $text, $customer),
            'extras' => $this->handleExtras($phone, $text, $customer),
            'address' => $this->handleAddress($phone, $text, $customer),
            'payment' => $this->handlePayment($phone, $text, $customer),
            'pix_wait' => $this->handlePixWait($phone, $text, $customer, $payload),
            default => $this->handleWelcome($phone, $text, $customer),
        };
    }

    private function handleWelcome(string $phone, string $text, ?Customer $customer): void
    {
        $this->replyText($phone, $this->render($this->message('welcome_message')), $customer);
        $this->sendMenuImage($phone, $customer, sendFollowup: false);
        $this->replyText($phone, $this->message('menu_followup_message'), $customer);

        $this->setSession($phone, [
            'state' => 'ordering',
            'cart' => [],
        ]);

        if ($this->parseProductsFromText($text) !== []) {
            $this->handleOrdering($phone, $text, $customer);
        }
    }

    private function handleOrdering(string $phone, string $text, ?Customer $customer): void
    {
        $session = $this->getSession($phone);
        $command = mb_strtolower(trim($text));

        if ($this->matchesIntent($command, ['só isso', 'so isso', 'pronto', 'finalizar', 'continuar', 'fechar', 'acabou', 'só', 'so', 'nao', 'não', 'n'])) {
            if (($session['cart'] ?? []) === []) {
                $this->replyText($phone, 'Seu pedido ainda está vazio. Me diga o que você gostaria de pedir!', $customer);

                return;
            }

            $this->setSession($phone, array_merge($session, ['state' => 'extras']));
            $this->replyText($phone, $this->message('extras_message'), $customer);

            return;
        }

        $parsed = $this->parseProductsFromText($text);

        if ($parsed === []) {
            $this->replyText($phone, 'Não encontrei esse item no cardápio. Confira a imagem do cardápio ou me diga o nome do prato. Quando terminar, digite *pronto*.', $customer);

            return;
        }

        $cart = $session['cart'] ?? [];

        foreach ($parsed as $item) {
            $found = false;

            foreach ($cart as &$cartItem) {
                if ($cartItem['product_id'] === $item['product_id']) {
                    $cartItem['quantity'] += $item['quantity'];
                    $found = true;
                    break;
                }
            }
            unset($cartItem);

            if (! $found) {
                $cart[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ];
            }
        }

        $this->setSession($phone, [
            'state' => 'ordering',
            'cart' => $cart,
        ]);

        $addedLines = collect($parsed)
            ->map(fn (array $item) => "{$item['quantity']}x {$item['name']}")
            ->implode(', ');

        $this->replyText($phone, $this->render($this->message('order_added_message'), [
            'items' => $addedLines.' 🍽️',
        ]), $customer);
    }

    private function handleExtras(string $phone, string $text, ?Customer $customer): void
    {
        $session = $this->getSession($phone);

        $this->setSession($phone, array_merge($session, [
            'state' => 'address',
            'extras_notes' => trim($text),
        ]));

        $this->replyText($phone, $this->message('address_message'), $customer);
    }

    private function handleAddress(string $phone, string $text, ?Customer $customer): void
    {
        $session = $this->getSession($phone);
        $command = mb_strtolower(trim($text));

        if ($this->matchesIntent($command, ['retirada', 'retirar', 'balcão', 'balcao', 'buscar', 'pegar'])) {
            $this->setSession($phone, array_merge($session, [
                'state' => 'payment',
                'order_type' => 'takeaway',
                'delivery_address' => null,
                'delivery_fee' => 0,
                'delivery_area_id' => null,
                'distance_km' => null,
            ]));

            $this->sendPaymentSummary($phone, $customer);

            return;
        }

        $quote = $this->deliveryFeeService->quoteForAddress(trim($text));

        if ($quote === null) {
            $this->replyText($phone, 'Não consegui calcular a entrega para esse endereço. Verifique se está completo (rua, número, bairro, cidade) ou digite *retirada* para buscar no balcão.', $customer);

            return;
        }

        $fee = number_format($quote['delivery_fee'], 2, ',', '.');
        $km = number_format($quote['distance_km'], 1, ',', '.');

        $this->replyText($phone, $this->render($this->message('delivery_quote_message'), [
            'distance_km' => $km,
            'delivery_fee' => $fee,
        ]), $customer);

        $this->setSession($phone, array_merge($session, [
            'state' => 'payment',
            'order_type' => 'delivery',
            'delivery_address' => trim($text),
            'delivery_fee' => $quote['delivery_fee'],
            'delivery_area_id' => $quote['delivery_area_id'],
            'distance_km' => $quote['distance_km'],
        ]));

        $this->sendPaymentSummary($phone, $customer);
    }

    private function handlePayment(string $phone, string $text, ?Customer $customer): void
    {
        $method = PaymentMethod::normalize($text);

        if ($method === null) {
            $this->replyText($phone, 'Forma de pagamento não reconhecida. Responda com *Pix*, *dinheiro*, *cartão de crédito* ou *cartão de débito*.', $customer);

            return;
        }

        $session = $this->getSession($phone);
        $session['payment_method'] = $method;

        if ($method === 'pix') {
            $pixKey = config('whatsapp_agent.pix_key');

            if (! filled($pixKey)) {
                $this->replyText($phone, 'Chave Pix não configurada. Entre em contato conosco para finalizar o pedido.', $customer);

                return;
            }

            $this->setSession($phone, array_merge($session, ['state' => 'pix_wait']));
            $this->replyText($phone, $this->render($this->message('pix_message'), [
                'pix_key' => $pixKey,
            ]), $customer);

            return;
        }

        $this->setSession($phone, $session);
        $this->createOrder($phone, $customer, $session);
    }

    private function handlePixWait(string $phone, string $text, ?Customer $customer, array $payload): void
    {
        $hasImage = data_get($payload, 'message.imageMessage') !== null
            || data_get($payload, 'message.documentMessage') !== null
            || mb_strtolower(trim($text)) === '[imagem]';

        $command = mb_strtolower(trim($text));
        $looksLikeProof = $hasImage
            || $this->matchesIntent($command, ['paguei', 'comprovante', 'pix feito', 'enviado', 'feito', 'ok', 'pronto']);

        if (! $looksLikeProof) {
            $this->replyText($phone, 'Assim que fizer o pagamento Pix, envie o comprovante (foto ou mensagem) para confirmarmos seu pedido.', $customer);

            return;
        }

        $session = $this->getSession($phone);
        $this->createOrder($phone, $customer, $session);
    }

    private function createOrder(string $phone, ?Customer $customer, array $session): void
    {
        $cart = $session['cart'] ?? [];

        if ($cart === []) {
            $this->replyText($phone, 'Seu pedido está vazio. Envie *oi* para começar novamente.', $customer);

            return;
        }

        try {
            $order = DB::transaction(function () use ($cart, $customer, $phone, $session) {
                $deliveryFee = (float) ($session['delivery_fee'] ?? 0);
                $orderType = $session['order_type'] ?? 'takeaway';
                $notes = $this->buildOrderNotes($session);

                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'customer_id' => $customer?->id,
                    'type' => $orderType,
                    'delivery_area_id' => $session['delivery_area_id'] ?? null,
                    'delivery_fee' => $deliveryFee,
                    'delivery_address' => $orderType === 'delivery'
                        ? ($session['delivery_address'] ?? null)
                        : null,
                    'customer_name' => $customer?->name ?? 'Cliente WhatsApp',
                    'customer_phone' => $customer?->phone ?? PhoneNumber::formatDisplay($phone) ?? $phone,
                    'payment_method' => $session['payment_method'] ?? null,
                    'notes' => $notes,
                    'status' => 'pending',
                    'user_id' => null,
                ]);

                $itemsTotal = 0;

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

                    $itemsTotal += $subtotal;
                }

                $order->update(['total' => $itemsTotal + $deliveryFee]);

                if ($customer && $orderType === 'delivery' && filled($session['delivery_address'] ?? null)) {
                    $customer->update(['address' => $session['delivery_address']]);
                }

                return $order->fresh('items.product');
            });

            if (config('printing.enabled') && config('printing.driver') === 'network') {
                try {
                    app(OrderPrinterService::class)->printOrder($order, 'kitchen');
                } catch (\Throwable) {
                    // best-effort
                }
            }

            $this->clearSession($phone);

            $total = number_format((float) $order->total, 2, ',', '.');
            $estimated = (string) config('whatsapp_agent.estimated_minutes', 45);
            $template = $this->message('confirmed_message');

            if (($session['payment_method'] ?? '') !== 'pix') {
                $template = str_replace('Comprovante recebido e ', '', $template);
            }

            $this->replyText($phone, $this->render($template, [
                'order_number' => $order->order_number,
                'total' => $total,
                'estimated_minutes' => $estimated,
            ]), $customer, $order);
        } catch (\Throwable $exception) {
            Log::error('Conversational WhatsApp order creation failed', ['error' => $exception->getMessage()]);
            $this->replyText($phone, 'Não foi possível criar o pedido. Tente novamente ou entre em contato conosco.', $customer);
        }
    }

    private function sendPaymentSummary(string $phone, ?Customer $customer): void
    {
        $session = $this->getSession($phone);

        $this->replyText($phone, $this->render($this->message('payment_message'), [
            'summary' => $this->buildSummary($session),
        ]), $customer);
    }

    private function sendMenuImage(string $phone, ?Customer $customer, bool $sendFollowup = true): void
    {
        $url = $this->menuImageUrl();

        if ($url) {
            try {
                $this->whatsAppService->sendImageToPhone($phone, $url, null, $customer, null, null, logInteraction: false);
            } catch (\Throwable $exception) {
                Log::warning('Failed to send WhatsApp menu image', ['error' => $exception->getMessage()]);
            }
        }

        if ($sendFollowup) {
            $this->replyText($phone, $this->message('menu_followup_message'), $customer);
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
            $this->replyText($phone, $this->message('status_not_found_message'), $customer);

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

        $this->replyText($phone, implode("\n", [
            "📦 *Pedido {$order->order_number}*",
            'Status: '.($statusLabels[$order->status] ?? $order->status),
            'Total: R$ '.number_format((float) $order->total, 2, ',', '.'),
            'Data: '.$order->created_at->format('d/m/Y H:i'),
        ]), $customer, $order);
    }

    /** @return array<int, array{product_id: int, quantity: int, name: string}> */
    private function parseProductsFromText(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $items = [];
        $segments = preg_split('/[\n,;]+/', $text) ?: [$text];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $segment = preg_replace('/^(me\s+(vê|ve|manda|quero|gostaria\s+de)\s+)/iu', '', $segment) ?? $segment;
            $quantity = 1;
            $productQuery = $segment;

            if (preg_match('/^(\d+)\s*[xX×]?\s*(.+)$/u', $segment, $matches)) {
                $quantity = max(1, (int) $matches[1]);
                $productQuery = trim($matches[2]);
            }

            $product = $this->matchProduct($productQuery);

            if ($product) {
                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'name' => $product->name,
                ];
            }
        }

        if ($items !== []) {
            return $items;
        }

        foreach ($this->menuProducts()->sortByDesc(fn (Product $product) => mb_strlen($product->name)) as $product) {
            if (mb_stripos($text, $product->name) === false) {
                continue;
            }

            $quantity = 1;
            $pattern = '/(\d+)\s*[xX×]?\s*'.preg_quote($product->name, '/').'/iu';

            if (preg_match($pattern, $text, $matches)) {
                $quantity = max(1, (int) $matches[1]);
            }

            $items[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'name' => $product->name,
            ];
        }

        return $items;
    }

    private function matchProduct(string $query): ?Product
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return null;
        }

        $products = $this->menuProducts();
        $best = null;
        $bestLength = 0;

        foreach ($products as $product) {
            $name = mb_strtolower($product->name);

            if ($name === $query) {
                return $product;
            }

            if (mb_stripos($query, $name) !== false || mb_stripos($name, $query) !== false) {
                $length = mb_strlen($name);

                if ($length > $bestLength) {
                    $best = $product;
                    $bestLength = $length;
                }
            }
        }

        return $best;
    }

    private function buildSummary(array $session): string
    {
        $cart = $session['cart'] ?? [];
        $lines = ['*Pedido:*', $this->cartSummary($cart, detailed: true), ''];

        $orderType = $session['order_type'] ?? 'takeaway';

        if ($orderType === 'delivery') {
            $lines[] = '*Entrega:* '.($session['delivery_address'] ?? '—');
            $lines[] = 'Taxa de entrega: R$ '.number_format((float) ($session['delivery_fee'] ?? 0), 2, ',', '.');
        } else {
            $lines[] = '*Entrega:* Retirada no balcão';
        }

        if (filled($session['extras_notes'] ?? null)) {
            $lines[] = '*Observações:* '.$session['extras_notes'];
        }

        $total = $this->cartTotal($cart) + (float) ($session['delivery_fee'] ?? 0);
        $lines[] = '';
        $lines[] = '*Total (com a taxa): R$ '.number_format($total, 2, ',', '.').'*';

        return implode("\n", $lines);
    }

    private function buildOrderNotes(array $session): string
    {
        $parts = ['Pedido via WhatsApp'];

        if (filled($session['extras_notes'] ?? null)) {
            $parts[] = 'Obs: '.$session['extras_notes'];
        }

        if (($session['order_type'] ?? '') === 'takeaway') {
            $parts[] = 'Retirada no balcão';
        }

        return implode(' | ', $parts);
    }

    private function cartSummary(array $cart, bool $detailed = false): string
    {
        $products = Product::whereIn('id', collect($cart)->pluck('product_id'))->get()->keyBy('id');
        $lines = [];

        foreach ($cart as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                continue;
            }

            $subtotal = $product->price * $item['quantity'];

            if ($detailed) {
                $price = number_format((float) $product->price, 2, ',', '.');
                $lines[] = "• {$item['quantity']}x {$product->name} — R$ {$price} = R$ ".number_format($subtotal, 2, ',', '.');
            } else {
                $lines[] = "• {$item['quantity']}x {$product->name}";
            }
        }

        return implode("\n", $lines);
    }

    private function cartTotal(array $cart): float
    {
        $products = Product::whereIn('id', collect($cart)->pluck('product_id'))->get()->keyBy('id');
        $total = 0;

        foreach ($cart as $item) {
            $product = $products->get($item['product_id']);

            if ($product) {
                $total += $product->price * $item['quantity'];
            }
        }

        return $total;
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

    private function menuImageUrl(): ?string
    {
        return WeeklyMenuImages::urlForToday();
    }

    private function message(string $key): string
    {
        $value = config("whatsapp_agent.{$key}");

        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        static $defaults = null;
        $defaults ??= require config_path('whatsapp_agent.php');

        return (string) ($defaults[$key] ?? '');
    }

    /** @param  array<string, string>  $replacements */
    private function render(string $template, array $replacements = []): string
    {
        $replacements = array_merge([
            'restaurant_name' => $this->restaurantName(),
        ], $replacements);

        return str_replace(
            array_map(fn ($key) => '{'.$key.'}', array_keys($replacements)),
            array_values($replacements),
            $template
        );
    }

    private function restaurantName(): string
    {
        return filled(config('whatsapp_agent.restaurant_name'))
            ? (string) config('whatsapp_agent.restaurant_name')
            : (string) config('app.name', 'Restaurant System');
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

    private function replyText(string $phone, string $message, ?Customer $customer = null, ?Order $order = null): void
    {
        try {
            $this->whatsAppService->sendToPhone($phone, $message, $customer, $order, null, logInteraction: false);
        } catch (\Throwable $exception) {
            Log::error('Conversational WhatsApp bot reply failed', [
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

    /** @param  array<int, string>  $intents */
    private function matchesIntent(string $command, array $intents): bool
    {
        foreach ($intents as $intent) {
            if ($command === mb_strtolower($intent)) {
                return true;
            }
        }

        return false;
    }
}
