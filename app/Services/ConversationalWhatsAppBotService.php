<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WhatsAppMessage;
use App\Support\OrderSchedule;
use App\Support\PaymentMethod;
use App\Support\PhoneNumber;
use App\Support\ProductSellable;
use App\Support\ProductVariants;
use App\Support\SideOptions;
use App\Support\WeeklyMenuImages;
use App\Support\WhatsAppBotPause;
use App\Support\WhatsAppMenuIntent;
use Carbon\Carbon;
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

        if ($this->wantsBotResume($command)) {
            WhatsAppBotPause::resume($phone);
            $this->clearSession($phone);
            WhatsAppBotPause::forgetAiHistory($phone);
            $this->replyText($phone, $this->message('bot_resumed_message'), $customer, sentByBot: true);

            return;
        }

        if ($this->wantsHumanAgent($command)) {
            $this->handoffToHuman($phone, $customer);

            return;
        }

        if (WhatsAppBotPause::isPaused($phone)) {
            return;
        }

        if (WhatsAppMenuIntent::matches($command)) {
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

        if (($session['state'] ?? '') === 'pix_wait') {
            $this->handlePixWait($phone, $text, $customer, $payload);

            return;
        }

        if (config('whatsapp_agent.use_openai')) {
            $handled = app(OpenAiWhatsAppAgentService::class)->handle($phone, $text, $pushName, $payload);

            if ($handled) {
                return;
            }
        }

        $state = $session['state'] ?? 'welcome';

        if ($state === 'welcome' || $this->matchesIntent($command, ['oi', 'olá', 'ola', 'help', 'inicio', 'início', 'bom dia', 'boa tarde', 'boa noite'])) {
            $this->handleWelcome($phone, $text, $customer);

            return;
        }

        match ($state) {
            'ordering' => $this->handleOrdering($phone, $text, $customer),
            'side' => $this->handleSide($phone, $text, $customer),
            'extras' => $this->handleExtras($phone, $text, $customer),
            'address' => $this->handleAddress($phone, $text, $customer),
            'schedule' => $this->handleSchedule($phone, $text, $customer),
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

        if ($this->captureScheduleIntent($phone, $text, $customer, $session)) {
            return;
        }

        if ($this->matchesIntent($command, ['só isso', 'so isso', 'pronto', 'finalizar', 'continuar', 'fechar', 'acabou', 'só', 'so', 'nao', 'não', 'n'])) {
            if (($session['cart'] ?? []) === []) {
                $this->replyText($phone, 'Seu pedido ainda está vazio. Me diga o que você gostaria de pedir!', $customer);

                return;
            }

            $this->setSession($phone, array_merge($session, ['state' => 'ordering']));
            $this->askSideOrExtras($phone, $customer);

            return;
        }

        $parsed = $this->parseProductsFromText($text);

        if ($parsed === []) {
            $faqAnswer = $this->tryAnswerFaq($text);

            if ($faqAnswer !== null) {
                $this->replyText($phone, $faqAnswer, $customer);

                return;
            }

            $this->replyText($phone, 'Não encontrei esse item no cardápio. Confira a imagem do cardápio ou me diga o nome do prato (ex.: *strogonoff P*). Quando terminar, digite *pronto*.', $customer);

            return;
        }

        foreach ($parsed as $item) {
            if ($item['needs_variant'] ?? false) {
                $sizes = $item['available_sizes'] ?? 'P, M ou G';
                $this->replyText(
                    $phone,
                    "O *{$item['product_name']}* tem tamanhos {$sizes}. Qual você prefere? Ex.: *{$item['product_name']} P*",
                    $customer
                );

                return;
            }
        }

        $cart = $session['cart'] ?? [];

        foreach ($parsed as $item) {
            $cartKey = $item['product_id'].'|'.($item['variant_id'] ?? 0);
            $found = false;

            foreach ($cart as &$cartItem) {
                $existingKey = $cartItem['product_id'].'|'.($cartItem['variant_id'] ?? 0);

                if ($existingKey === $cartKey) {
                    $cartItem['quantity'] += $item['quantity'];
                    $found = true;
                    break;
                }
            }
            unset($cartItem);

            if (! $found) {
                $cart[] = [
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
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

    private function askSideOrExtras(string $phone, ?Customer $customer): void
    {
        if (SideOptions::enabled()) {
            $this->setSession($phone, array_merge($this->getSession($phone), ['state' => 'side']));
            $this->replyText($phone, $this->render($this->message('side_message'), [
                'options' => SideOptions::listForMessage(),
            ]), $customer);

            return;
        }

        $this->setSession($phone, array_merge($this->getSession($phone), ['state' => 'extras']));
        $this->replyText($phone, $this->message('extras_message'), $customer);
    }

    private function handleSide(string $phone, string $text, ?Customer $customer): void
    {
        $side = SideOptions::resolve($text);

        if ($side === null) {
            $this->replyText(
                $phone,
                'Não entendi o acompanhamento. Escolha uma opção:\n\n'.SideOptions::listForMessage()."\n\nEx.: *1* ou *fritas*.",
                $customer
            );

            return;
        }

        $session = $this->getSession($phone);
        $this->setSession($phone, array_merge($session, [
            'state' => 'extras',
            'side' => $side,
        ]));
        $this->replyText($phone, $this->message('extras_message'), $customer);
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

            $this->proceedToSchedule($phone, $customer);

            return;
        }

        $quote = $this->deliveryFeeService->quoteForAddress(trim($text));

        if ($quote === null) {
            $this->replyText($phone, $this->deliveryFailureMessage(trim($text)), $customer);

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

        $this->proceedToSchedule($phone, $customer);
    }

    private function proceedToSchedule(string $phone, ?Customer $customer): void
    {
        if (! OrderSchedule::enabled()) {
            $session = $this->getSession($phone);
            $this->setSession($phone, array_merge($session, ['state' => 'payment']));
            $this->sendPaymentSummary($phone, $customer);

            return;
        }

        $session = $this->getSession($phone);

        if (filled($session['scheduled_label'] ?? null)) {
            $this->setSession($phone, array_merge($session, ['state' => 'payment']));
            $this->sendPaymentSummary($phone, $customer);

            return;
        }

        $this->setSession($phone, array_merge($session, ['state' => 'schedule']));
        $this->replyText($phone, $this->message('schedule_message'), $customer);
    }

    private function handleSchedule(string $phone, string $text, ?Customer $customer): void
    {
        $resolved = OrderSchedule::resolve($text);

        if ($resolved['error'] !== null) {
            $this->replyText($phone, $resolved['error'], $customer);

            return;
        }

        $session = $this->getSession($phone);
        $this->setSession($phone, array_merge($session, [
            'state' => 'payment',
            'scheduled_for' => $resolved['datetime']?->toIso8601String(),
            'scheduled_label' => $resolved['label'],
        ]));

        if ($resolved['datetime'] !== null) {
            $this->replyText($phone, 'Perfeito! Seu pedido ficou agendado para *'.$resolved['label'].'*.', $customer);
        }

        $this->sendPaymentSummary($phone, $customer);
    }

    /** @param  array<string, mixed>  $session */
    private function captureScheduleIntent(string $phone, string $text, ?Customer $customer, array $session): bool
    {
        if (! OrderSchedule::enabled() || ! OrderSchedule::mentionsScheduling($text)) {
            return false;
        }

        $resolved = OrderSchedule::resolve($text);

        if ($resolved['error'] !== null) {
            return false;
        }

        $this->setSession($phone, array_merge($session, [
            'scheduled_for' => $resolved['datetime']?->toIso8601String(),
            'scheduled_label' => $resolved['label'],
        ]));

        $this->replyText(
            $phone,
            'Horário anotado: *'.$resolved['label'].'*. Continue montando o pedido e digite *pronto* quando terminar.',
            $customer
        );

        return true;
    }

    private function scheduledForFromSession(array $session): ?Carbon
    {
        if (! filled($session['scheduled_for'] ?? null)) {
            return null;
        }

        return Carbon::parse($session['scheduled_for']);
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
        $lockKey = 'wa-create-order:'.$this->normalizedPhoneKey($phone);
        $lock = Cache::lock($lockKey, 20);

        if (! $lock->get()) {
            Log::info('WhatsApp order create skipped — lock busy', ['phone' => $phone]);

            return;
        }

        $claimedSession = null;

        try {
            // Re-read under lock so concurrent finalize cannot reuse the same cart.
            $session = $this->getSession($phone);
            $cart = $session['cart'] ?? [];

            if ($cart === [] || ($session['order_claimed'] ?? false) === true) {
                Log::info('WhatsApp order create skipped — empty or already claimed', ['phone' => $phone]);

                return;
            }

            $claimedSession = $session;
            $this->setSession($phone, array_merge($session, [
                'cart' => [],
                'order_claimed' => true,
                'state' => 'creating',
            ]));

            $order = DB::transaction(function () use ($cart, $customer, $phone, $session) {
                $deliveryFee = (float) ($session['delivery_fee'] ?? 0);
                $orderType = $session['order_type'] ?? 'takeaway';
                $notes = $this->buildOrderNotes($session);
                $scheduledFor = $this->scheduledForFromSession($session);

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
                    'scheduled_for' => $scheduledFor,
                    'status' => 'pending',
                    'user_id' => null,
                ]);

                $itemsTotal = 0;

                foreach ($cart as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $attrs = ProductSellable::orderItemAttributes(
                        $product,
                        (int) $item['quantity'],
                        isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                    );

                    $order->items()->create($attrs);
                    $itemsTotal += (float) $attrs['subtotal'];
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
                'scheduled_for' => OrderSchedule::formatForMessage($order->scheduled_for),
            ]), $customer, $order);
        } catch (\Throwable $exception) {
            if (is_array($claimedSession)) {
                $this->setSession($phone, $claimedSession);
            }

            Log::error('Conversational WhatsApp order creation failed', ['error' => $exception->getMessage()]);
            $this->replyText($phone, 'Não foi possível criar o pedido. Tente novamente ou entre em contato conosco.', $customer);
        } finally {
            optional($lock)->release();
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

        if (! $url) {
            Log::warning('WhatsApp menu image missing for today', [
                'day' => WeeklyMenuImages::todayKey(),
            ]);
            $this->replyText($phone, 'O cardápio em imagem de hoje ainda não foi configurado. Me diga o prato que você quer (ex.: *strogonoff P*).', $customer);

            return;
        }

        try {
            $this->whatsAppService->sendImageToPhone($phone, $url, null, $customer, null, null, logInteraction: false, sentByBot: true);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send WhatsApp menu image', ['error' => $exception->getMessage()]);
            $this->replyText($phone, 'Não consegui enviar a imagem do cardápio agora. Me diga o prato que você quer (ex.: *strogonoff P*).', $customer);

            return;
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

    /** @return array<int, array{product_id: int, variant_id: ?int, quantity: int, name: string, needs_variant?: bool, product_name?: string, available_sizes?: string}> */
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

            $quantity = 1;
            $productQuery = $segment;

            if (preg_match('/^(\d+)\s*[xX×]?\s*(.+)$/u', $segment, $matches)) {
                $quantity = max(1, (int) $matches[1]);
                $productQuery = trim($matches[2]);
            }

            $parsedItem = $this->parseProductSegment($productQuery, $quantity);

            if ($parsedItem !== null) {
                $items[] = $parsedItem;
            }
        }

        if ($items !== []) {
            return $items;
        }

        $parsedItem = $this->parseProductSegment($text, 1);

        return $parsedItem !== null ? [$parsedItem] : [];
    }

    /** @return array{product_id: int, variant_id: ?int, quantity: int, name: string, needs_variant?: bool, product_name?: string, available_sizes?: string}|null */
    private function parseProductSegment(string $segment, int $quantity): ?array
    {
        $segment = $this->normalizeOrderSegment($segment);

        if ($segment === '') {
            return null;
        }

        [$productQuery, $variantHint] = $this->extractVariantHint($segment);
        $product = $this->matchProduct($productQuery !== '' ? $productQuery : $segment);

        if (! $product) {
            return null;
        }

        $variant = $this->resolveVariant($product, $variantHint);

        if ($product->hasVariants() && ! $variant) {
            return [
                'product_id' => $product->id,
                'variant_id' => null,
                'quantity' => $quantity,
                'name' => $product->name,
                'needs_variant' => true,
                'product_name' => $product->name,
                'available_sizes' => $this->variantSizeList($product),
            ];
        }

        $name = $variant
            ? $product->name.' ('.$variant->label.')'
            : $product->name;

        return [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => $quantity,
            'name' => $name,
        ];
    }

    private function normalizeOrderSegment(string $segment): string
    {
        $segment = mb_strtolower(trim($segment));

        $patterns = [
            '/^(me\s+)?(vê|ve|vei|manda|quero|gostaria\s+de|preciso\s+de|pode\s+ser|vou\s+querer|vou\s+de|desejo)\s+/iu',
            '/^(um|uma|uns|umas)\s+/iu',
            '/^(de|do|da|dos|das)\s+/iu',
        ];

        do {
            $previous = $segment;

            foreach ($patterns as $pattern) {
                $segment = preg_replace($pattern, '', $segment) ?? $segment;
                $segment = trim($segment);
            }
        } while ($segment !== $previous && $segment !== '');

        return trim($segment);
    }

    /** @return array{0: string, 1: ?string} */
    private function extractVariantHint(string $query): array
    {
        $patterns = [
            '/\s+(pequeno|pequena|p)\s*$/iu' => 'P',
            '/\s+(m[ée]dio|m[ée]dia|m)\s*$/iu' => 'M',
            '/\s+(grande|g)\s*$/iu' => 'G',
            '/\s+tamanho\s+(p|m|g|pequeno|medio|médio|grande)\s*$/iu' => null,
        ];

        foreach ($patterns as $pattern => $defaultLabel) {
            if (! preg_match($pattern, $query, $matches)) {
                continue;
            }

            $matched = mb_strtolower(trim($matches[count($matches) - 1]));
            $label = $defaultLabel ?? match ($matched) {
                'p', 'pequeno' => 'P',
                'm', 'medio', 'médio' => 'M',
                'g', 'grande' => 'G',
                default => mb_strtoupper($matched),
            };

            $query = trim(preg_replace($pattern, '', $query) ?? $query);

            return [$query, $label];
        }

        return [$query, null];
    }

    private function resolveVariant(Product $product, ?string $hint): ?ProductVariant
    {
        if (! $product->hasVariants() || ! $hint) {
            return null;
        }

        $product->loadMissing(['variants' => fn ($query) => $query->where('is_available', true)->orderBy('sort_order')]);
        $hint = mb_strtoupper(trim($hint));

        return $product->variants->first(function ($variant) use ($hint) {
            $label = mb_strtoupper(trim($variant->label));

            return $label === $hint || str_starts_with($label, $hint);
        });
    }

    private function variantSizeList(Product $product): string
    {
        $product->loadMissing(['variants' => fn ($query) => $query->where('is_available', true)->orderBy('sort_order')]);

        $labels = $product->variants->pluck('label')->filter()->all();

        if ($labels === []) {
            return 'P, M ou G';
        }

        if (count($labels) === 1) {
            return (string) $labels[0];
        }

        $last = array_pop($labels);

        return implode(', ', $labels).' ou '.$last;
    }

    private function matchProduct(string $query): ?Product
    {
        $query = $this->normalizeOrderSegment($query);

        if ($query === '') {
            return null;
        }

        $products = $this->menuProducts();
        $queryTokens = $this->significantTokens($query);
        $best = null;
        $bestScore = 0;

        foreach ($products as $product) {
            $score = $this->productMatchScore($query, $queryTokens, $product);

            if ($score > $bestScore) {
                $best = $product;
                $bestScore = $score;
            }
        }

        return $bestScore >= 50 ? $best : null;
    }

    /** @param  array<int, string>  $queryTokens */
    private function productMatchScore(string $query, array $queryTokens, Product $product): int
    {
        $name = mb_strtolower($product->name);

        if ($name === $query) {
            return 1000;
        }

        if (mb_stripos($query, $name) !== false || mb_stripos($name, $query) !== false) {
            return 900;
        }

        if ($queryTokens === []) {
            return 0;
        }

        $nameTokens = $this->significantTokens($product->name);
        $matched = 0;

        foreach ($queryTokens as $queryToken) {
            foreach ($nameTokens as $nameToken) {
                if ($queryToken === $nameToken
                    || mb_stripos($nameToken, $queryToken) !== false
                    || mb_stripos($queryToken, $nameToken) !== false) {
                    $matched++;
                    break;
                }
            }
        }

        if ($matched === 0) {
            return 0;
        }

        $queryCoverage = $matched / count($queryTokens);
        $nameCoverage = $matched / max(count($nameTokens), 1);

        return (int) round(($queryCoverage * 0.7 + $nameCoverage * 0.3) * 100);
    }

    /** @return array<int, string> */
    private function significantTokens(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;
        $parts = preg_split('/\s+/u', trim($text)) ?: [];
        $stopWords = ['de', 'da', 'do', 'dos', 'das', 'com', 'e', 'um', 'uma', 'uns', 'umas', 'ao', 'na', 'no', 'para', 'por'];

        return array_values(array_filter($parts, function (string $token) use ($stopWords) {
            if ($token === '' || in_array($token, $stopWords, true)) {
                return false;
            }

            return mb_strlen($token) >= 3;
        }));
    }

    private function tryAnswerFaq(string $text): ?string
    {
        $normalized = mb_strtolower(trim($text));

        if (! preg_match('/(hor[aá]rio|que horas|abre|aberto|funciona|fechado|fecha|atende)/u', $normalized)) {
            return null;
        }

        $opening = (string) (config('general.opening_time') ?: config('digital_menu.opening_time', '09:00'));
        $closing = (string) (config('general.closing_time') ?: config('digital_menu.closing_time', '22:00'));

        return 'Funcionamos de *'.$this->formatTimeForWhatsApp($opening).'* às *'.$this->formatTimeForWhatsApp($closing)."*.\n\nMe diga o que deseja pedir (ex.: *strogonoff P*) ou digite *pronto* quando terminar.";
    }

    private function formatTimeForWhatsApp(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return $time;
    }

    private function deliveryFailureMessage(string $address, ?string $reason = null, ?float $distanceKm = null): string
    {
        if ($reason === null) {
            $diagnosis = $this->deliveryFeeService->diagnoseAddress($address);
            $reason = $diagnosis['reason'];
            $distanceKm = $diagnosis['distance_km'];
        }

        $city = trim((string) config('digital_menu.city'));
        $cityHint = $city !== '' ? $city : 'sua cidade';

        return match ($reason) {
            'missing_origin' => 'Ainda não configurei a localização do restaurante para calcular entrega. Por favor, digite *retirada* para buscar no balcão ou fale conosco.',
            'geocode_failed' => 'Não localizei esse endereço em *'.$cityHint.'*. Envie rua, número e bairro (ex.: *Rua Machado de Assis, 465, Vila Mariana*) ou digite *retirada*.',
            'out_of_range' => 'Esse endereço fica a cerca de *'.number_format((float) $distanceKm, 1, ',', '.').' km* do restaurante, fora das faixas de entrega cadastradas. Digite *retirada* ou informe outro endereço.',
            default => 'Não consegui calcular a entrega para esse endereço. Verifique se está completo (rua, número e bairro em '.$cityHint.') ou digite *retirada* para buscar no balcão.',
        };
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

        if (filled($session['side'] ?? null)) {
            $lines[] = '*Acompanhamento:* '.$session['side'];
        }

        if (filled($session['extras_notes'] ?? null)) {
            $lines[] = '*Observações:* '.$session['extras_notes'];
        }

        if (filled($session['scheduled_label'] ?? null)) {
            $lines[] = '*Horário:* '.ucfirst((string) $session['scheduled_label']);
        }

        $total = $this->cartTotal($cart) + (float) ($session['delivery_fee'] ?? 0);
        $lines[] = '';
        $lines[] = '*Total (com a taxa): R$ '.number_format($total, 2, ',', '.').'*';

        return implode("\n", $lines);
    }

    private function buildOrderNotes(array $session): string
    {
        $parts = ['Pedido via WhatsApp'];

        if (filled($session['side'] ?? null)) {
            $parts[] = 'Acompanhamento: '.$session['side'];
        }

        if (filled($session['extras_notes'] ?? null)) {
            $parts[] = 'Obs: '.$session['extras_notes'];
        }

        if (($session['order_type'] ?? '') === 'takeaway') {
            $parts[] = 'Retirada no balcão';
        }

        if (filled($session['scheduled_label'] ?? null)) {
            $parts[] = 'Agendado para '.$session['scheduled_label'];
        }

        return implode(' | ', $parts);
    }

    private function cartSummary(array $cart, bool $detailed = false): string
    {
        $products = Product::query()
            ->when(ProductVariants::enabled(), fn ($query) => $query->with([
                'variants' => fn ($variantQuery) => $variantQuery->where('is_available', true)->orderBy('sort_order'),
            ]))
            ->whereIn('id', collect($cart)->pluck('product_id'))
            ->get()
            ->keyBy('id');
        $lines = [];

        foreach ($cart as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                continue;
            }

            $resolved = ProductSellable::resolve($product, $item['variant_id'] ?? null);
            $subtotal = $resolved['price'] * $item['quantity'];

            if ($detailed) {
                $price = number_format($resolved['price'], 2, ',', '.');
                $lines[] = "• {$item['quantity']}x {$resolved['name']} — R$ {$price} = R$ ".number_format($subtotal, 2, ',', '.');
            } else {
                $lines[] = "• {$item['quantity']}x {$resolved['name']}";
            }
        }

        return implode("\n", $lines);
    }

    private function cartTotal(array $cart): float
    {
        $products = Product::query()
            ->when(ProductVariants::enabled(), fn ($query) => $query->with([
                'variants' => fn ($variantQuery) => $variantQuery->where('is_available', true)->orderBy('sort_order'),
            ]))
            ->whereIn('id', collect($cart)->pluck('product_id'))
            ->get()
            ->keyBy('id');
        $total = 0;

        foreach ($cart as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                continue;
            }

            $resolved = ProductSellable::resolve($product, $item['variant_id'] ?? null);
            $total += $resolved['price'] * $item['quantity'];
        }

        return $total;
    }

    private function menuProducts(): Collection
    {
        return Product::query()
            ->with('category')
            ->when(ProductVariants::enabled(), fn ($query) => $query->with([
                'variants' => fn ($variantQuery) => $variantQuery->where('is_available', true)->orderBy('sort_order'),
            ]))
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

    private function replyText(string $phone, string $message, ?Customer $customer = null, ?Order $order = null, bool $sentByBot = true): void
    {
        try {
            $this->whatsAppService->sendToPhone($phone, $message, $customer, $order, null, logInteraction: false, sentByBot: $sentByBot);
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

    public function replyToCustomer(string $phone, string $message, ?string $pushName = null): void
    {
        $this->replyText($phone, $message, $this->resolveCustomer($phone, $pushName));
    }

    public function normalizedPhoneKey(string $phone): string
    {
        return PhoneNumber::normalize($phone) ?? $phone;
    }

    public function restaurantDisplayName(): string
    {
        return $this->restaurantName();
    }

    public function openingHoursLabel(): string
    {
        $opening = (string) (config('general.opening_time') ?: config('digital_menu.opening_time', '09:00'));
        $closing = (string) (config('general.closing_time') ?: config('digital_menu.closing_time', '22:00'));

        return $this->formatTimeForWhatsApp($opening).' às '.$this->formatTimeForWhatsApp($closing);
    }

    /** @return array<string, mixed> */
    public function sessionSnapshot(string $phone): array
    {
        $session = $this->getSession($phone);

        return [
            'state' => $session['state'] ?? 'welcome',
            'cart' => $this->simplifiedCart($session['cart'] ?? []),
            'order_type' => $session['order_type'] ?? null,
            'delivery_fee' => $session['delivery_fee'] ?? null,
            'payment_method' => $session['payment_method'] ?? null,
            'scheduled_label' => $session['scheduled_label'] ?? null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function menuSnapshot(): array
    {
        return $this->menuProducts()->map(function (Product $product) {
            $entry = [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category->name,
                'price' => (float) $product->displayPrice(),
                'price_label' => $product->priceLabel(),
                'has_variants' => $product->hasVariants(),
            ];

            if ($product->hasVariants()) {
                $entry['variants'] = $product->variants->map(fn ($variant) => [
                    'label' => $variant->label,
                    'price' => (float) $variant->price,
                ])->values()->all();
            }

            return $entry;
        })->values()->all();
    }

    /** @return array<string, mixed> */
    public function toolSendMenuImage(string $phone, ?string $pushName): array
    {
        $customer = $this->resolveCustomer($phone, $pushName);
        $this->sendMenuImage($phone, $customer, sendFollowup: false);
        $this->setSession($phone, array_merge($this->getSession($phone), [
            'state' => 'ordering',
            'cart' => $this->getSession($phone)['cart'] ?? [],
        ]));

        return ['ok' => true, 'sent' => $this->menuImageUrl() !== null];
    }

    /** @param  array<string, mixed>  $arguments */
    /** @return array<string, mixed> */
    public function toolAddToCart(string $phone, array $arguments, ?string $pushName): array
    {
        $session = $this->getSession($phone);
        $cart = $session['cart'] ?? [];
        $added = [];
        $errors = [];

        foreach ($arguments['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $product = $this->matchProduct((string) ($item['product_name'] ?? ''));

            if (! $product) {
                $errors[] = 'Produto não encontrado: '.($item['product_name'] ?? '?');

                continue;
            }

            $variantLabel = isset($item['variant_label']) ? mb_strtoupper(trim((string) $item['variant_label'])) : null;
            $variant = $this->resolveVariant($product, $variantLabel);

            if ($product->hasVariants() && ! $variant) {
                $errors[] = "Informe o tamanho (P, M ou G) para {$product->name}.";

                continue;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $cartKey = $product->id.'|'.($variant?->id ?? 0);
            $found = false;

            foreach ($cart as &$cartItem) {
                $existingKey = $cartItem['product_id'].'|'.($cartItem['variant_id'] ?? 0);

                if ($existingKey === $cartKey) {
                    $cartItem['quantity'] += $quantity;
                    $found = true;
                    break;
                }
            }
            unset($cartItem);

            if (! $found) {
                $cart[] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'quantity' => $quantity,
                ];
            }

            $name = $variant ? $product->name.' ('.$variant->label.')' : $product->name;
            $added[] = "{$quantity}x {$name}";
        }

        $this->setSession($phone, array_merge($session, [
            'state' => 'ordering',
            'cart' => $cart,
        ]));

        return [
            'ok' => $errors === [],
            'added' => $added,
            'errors' => $errors,
            'cart' => $this->simplifiedCart($cart),
        ];
    }

    /** @return array<string, mixed> */
    public function toolViewCart(string $phone): array
    {
        $session = $this->getSession($phone);
        $cart = $session['cart'] ?? [];

        return [
            'ok' => true,
            'cart' => $this->simplifiedCart($cart),
            'total' => $this->cartTotal($cart),
        ];
    }

    /** @return array<string, mixed> */
    public function toolFinalizeItems(string $phone, ?string $pushName): array
    {
        $session = $this->getSession($phone);

        if (($session['cart'] ?? []) === []) {
            return ['ok' => false, 'error' => 'Carrinho vazio.'];
        }

        if (SideOptions::enabled()) {
            $this->setSession($phone, array_merge($session, ['state' => 'side']));
            $message = $this->render($this->message('side_message'), [
                'options' => SideOptions::listForMessage(),
            ]);

            return ['ok' => true, 'next' => 'side', 'message' => $message, 'side_options' => SideOptions::all()];
        }

        $this->setSession($phone, array_merge($session, ['state' => 'extras']));

        return ['ok' => true, 'next' => 'extras', 'message' => $this->message('extras_message')];
    }

    /** @return array<string, mixed> */
    public function toolSetSide(string $phone, string $side, ?string $pushName): array
    {
        if (! SideOptions::enabled()) {
            return $this->toolSetExtras($phone, '', $pushName);
        }

        $resolved = SideOptions::resolve($side);

        if ($resolved === null) {
            return [
                'ok' => false,
                'error' => 'Acompanhamento inválido.',
                'side_options' => SideOptions::all(),
                'message' => 'Escolha uma opção:\n'.SideOptions::listForMessage(),
            ];
        }

        $session = $this->getSession($phone);
        $this->setSession($phone, array_merge($session, [
            'state' => 'extras',
            'side' => $resolved,
        ]));

        return [
            'ok' => true,
            'side' => $resolved,
            'next' => 'extras',
            'message' => $this->message('extras_message'),
        ];
    }

    /** @return array<string, mixed> */
    public function toolSetExtras(string $phone, string $notes, ?string $pushName): array
    {
        $session = $this->getSession($phone);

        $this->setSession($phone, array_merge($session, [
            'state' => 'address',
            'extras_notes' => trim($notes),
        ]));

        return ['ok' => true, 'next' => 'address', 'message' => $this->message('address_message')];
    }

    /** @return array<string, mixed> */
    public function toolQuoteDelivery(string $phone, string $address, ?string $pushName): array
    {
        $session = $this->getSession($phone);
        $customer = $this->resolveCustomer($phone, $pushName);
        $command = mb_strtolower(trim($address));

        if ($this->matchesIntent($command, ['retirada', 'retirar', 'balcão', 'balcao', 'buscar', 'pegar'])) {
            $this->setSession($phone, array_merge($session, [
                'state' => OrderSchedule::enabled() ? 'schedule' : 'payment',
                'order_type' => 'takeaway',
                'delivery_address' => null,
                'delivery_fee' => 0,
                'delivery_area_id' => null,
                'distance_km' => null,
            ]));

            return $this->deliveryStepResponse($phone);
        }

        $diagnosis = $this->deliveryFeeService->diagnoseAddress(trim($address));
        $quote = $diagnosis['quote'];

        if ($quote === null) {
            return [
                'ok' => false,
                'error' => $this->deliveryFailureMessage(trim($address), $diagnosis['reason'] ?? null, $diagnosis['distance_km'] ?? null),
                'reason' => $diagnosis['reason'] ?? null,
                'distance_km' => $diagnosis['distance_km'] ?? null,
            ];
        }

        $this->setSession($phone, array_merge($session, [
            'state' => OrderSchedule::enabled() ? 'schedule' : 'payment',
            'order_type' => 'delivery',
            'delivery_address' => trim($address),
            'delivery_fee' => $quote['delivery_fee'],
            'delivery_area_id' => $quote['delivery_area_id'],
            'distance_km' => $quote['distance_km'],
        ]));

        $response = $this->deliveryStepResponse($phone);
        $response['distance_km'] = $quote['distance_km'];
        $response['delivery_fee'] = $quote['delivery_fee'];

        return $response;
    }

    /** @return array<string, mixed> */
    public function toolSetSchedule(string $phone, string $scheduleText, ?string $pushName): array
    {
        $resolved = OrderSchedule::resolve($scheduleText);

        if ($resolved['error'] !== null) {
            return ['ok' => false, 'error' => $resolved['error']];
        }

        $session = $this->getSession($phone);
        $this->setSession($phone, array_merge($session, [
            'state' => 'payment',
            'scheduled_for' => $resolved['datetime']?->toIso8601String(),
            'scheduled_label' => $resolved['label'],
        ]));

        return [
            'ok' => true,
            'scheduled_label' => $resolved['label'],
            'next' => 'payment',
            'summary' => $this->buildSummary($this->getSession($phone)),
        ];
    }

    /** @return array<string, mixed> */
    private function deliveryStepResponse(string $phone): array
    {
        $session = $this->getSession($phone);

        if (($session['state'] ?? '') === 'schedule' && ! filled($session['scheduled_label'] ?? null)) {
            return [
                'ok' => true,
                'order_type' => $session['order_type'] ?? 'takeaway',
                'next' => 'schedule',
                'message' => $this->message('schedule_message'),
            ];
        }

        return [
            'ok' => true,
            'order_type' => $session['order_type'] ?? 'takeaway',
            'next' => 'payment',
            'summary' => $this->buildSummary($session),
        ];
    }

    /** @return array<string, mixed> */
    public function toolSetPayment(string $phone, string $methodText, ?string $pushName, array $payload = []): array
    {
        $method = PaymentMethod::normalize($methodText);

        if ($method === null) {
            return ['ok' => false, 'error' => 'Forma de pagamento não reconhecida.'];
        }

        $session = $this->getSession($phone);
        $customer = $this->resolveCustomer($phone, $pushName);
        $session['payment_method'] = $method;

        if ($method === 'pix') {
            $pixKey = config('whatsapp_agent.pix_key');

            if (! filled($pixKey)) {
                return ['ok' => false, 'error' => 'Chave Pix não configurada no sistema.'];
            }

            $this->setSession($phone, array_merge($session, ['state' => 'pix_wait']));

            return [
                'ok' => true,
                'awaiting_pix_proof' => true,
                'pix_key' => $pixKey,
                'pix_message' => $this->render($this->message('pix_message'), ['pix_key' => $pixKey]),
            ];
        }

        $this->setSession($phone, $session);
        $this->createOrder($phone, $customer, $session);

        return ['ok' => true, 'order_created' => true];
    }

    /** @return array<string, mixed> */
    public function toolCancelOrder(string $phone, ?string $pushName): array
    {
        $this->clearSession($phone);
        WhatsAppBotPause::forgetAiHistory($phone);

        return ['ok' => true, 'message' => $this->message('cancel_message')];
    }

    private function handoffToHuman(string $phone, ?Customer $customer): void
    {
        if (WhatsAppBotPause::isPaused($phone)) {
            return;
        }

        WhatsAppBotPause::pause($phone, 'customer_request');
        WhatsAppBotPause::forgetAiHistory($phone);
        $this->replyText($phone, $this->message('human_handoff_message'), $customer, sentByBot: true);
    }

    private function wantsHumanAgent(string $command): bool
    {
        if ($this->matchesIntent($command, [
            'atendente',
            'humano',
            'operador',
            'falar com atendente',
            'quero atendente',
            'quero um atendente',
            'atendimento humano',
        ])) {
            return true;
        }

        return (bool) preg_match(
            '/\b(atendente|humano|operador|pessoa\s+real)\b/u',
            $command
        ) || (bool) preg_match(
            '/\bfalar\s+com\s+(algu[eé]m|uma\s+pessoa|voc[eê]s|atendente|humano)\b/u',
            $command
        );
    }

    private function wantsBotResume(string $command): bool
    {
        return $this->matchesIntent($command, [
            'bot',
            'robô',
            'robo',
            'voltar bot',
            'automático',
            'automatico',
            'continuar com bot',
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $cart */
    /** @return array<int, array<string, mixed>> */
    private function simplifiedCart(array $cart): array
    {
        if ($cart === []) {
            return [];
        }

        $products = Product::query()
            ->when(ProductVariants::enabled(), fn ($query) => $query->with([
                'variants' => fn ($variantQuery) => $variantQuery->where('is_available', true)->orderBy('sort_order'),
            ]))
            ->whereIn('id', collect($cart)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($cart as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                continue;
            }

            $resolved = ProductSellable::resolve($product, $item['variant_id'] ?? null);
            $lines[] = [
                'name' => $resolved['name'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => $resolved['price'],
                'subtotal' => $resolved['price'] * (int) $item['quantity'],
            ];
        }

        return $lines;
    }
}
