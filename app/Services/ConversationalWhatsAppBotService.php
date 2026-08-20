<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WhatsAppMessage;
use App\Support\OrderSchedule;
use App\Support\OpeningHours;
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
            $session = $this->getSession($phone);
            $day = $this->resolveMenuImageDay(WhatsAppMenuIntent::requestedDay($command));
            $this->sendMenuImage($phone, $customer, sendFollowup: true, day: $day);

            // Pediu cardápio no meio do checkout → volta a aceitar itens.
            if ($this->isCheckoutInterruptibleState($session['state'] ?? null) && ($session['cart'] ?? []) !== []) {
                $this->setSession($phone, array_merge($session, [
                    'state' => 'ordering',
                    'saved_address_prompt' => false,
                    'delivery_fee' => null,
                    'delivery_address' => null,
                    'delivery_area_id' => null,
                    'distance_km' => null,
                    'order_type' => null,
                    'scheduled_for' => null,
                    'scheduled_label' => null,
                ]));
            }

            return;
        }

        if ($this->wantsToCancelOrder($command)) {
            $this->clearSession($phone);
            WhatsAppBotPause::forgetAiHistory($phone);
            $this->replyText($phone, $this->message('cancel_message'), $customer);

            return;
        }

        if ($this->matchesIntent($command, ['status'])) {
            $this->sendOrderStatus($phone, $customer);

            return;
        }

        $session = $this->getSession($phone);

        // Cliente pediu mais itens no meio do checkout (endereço/extras/etc.): voltar ao carrinho.
        if ($this->shouldResumeOrderingForMoreItems($session, $text)) {
            $this->resumeOrderingForMoreItems($phone, $text, $customer);

            return;
        }

        // Acompanhamento (fritas/legumes): tratar antes da OpenAI para evitar add_to_cart errado.
        if (($session['state'] ?? '') === 'side' && SideOptions::resolve($text) !== null) {
            $this->handleSide($phone, $text, $customer);

            return;
        }

        // Confirmação de endereço salvo: PHP controla o próximo passo (horário/pagamento).
        if (($session['state'] ?? '') === 'address' && ($session['saved_address_prompt'] ?? false) === true) {
            $command = mb_strtolower(trim($text));

            if ($this->confirmsSavedAddress($command)
                || $this->declinesSavedAddress($command)
                || $this->matchesIntent($command, ['retirada', 'retirar', 'balcão', 'balcao', 'buscar', 'pegar'])) {
                $this->handleAddress($phone, $text, $customer);

                return;
            }
        }

        // Em pix_wait, permitir trocar para dinheiro/cartão (em vez de insistir no comprovante).
        if (($session['state'] ?? '') === 'pix_wait') {
            $altMethod = PaymentMethod::detect($text);

            if ($altMethod !== null && $altMethod !== 'pix') {
                $this->switchAwayFromPix($phone, $altMethod, $customer);

                return;
            }

            $this->handlePixWait($phone, $text, $customer, $payload);

            return;
        }

        // Pagamento: gravar pedido no PHP antes da OpenAI (ela às vezes "confirma" sem set_payment).
        if (($session['state'] ?? '') === 'payment' && PaymentMethod::detect($text) !== null) {
            $this->handlePayment($phone, $text, $customer);

            return;
        }

        // Horário: resolver no PHP quando já estamos na etapa schedule.
        if (($session['state'] ?? '') === 'schedule' && OrderSchedule::enabled()) {
            $this->handleSchedule($phone, $text, $customer);

            return;
        }

        if ($this->shouldRefuseOrdersWhileClosed($session)) {
            $this->replyClosed($phone, $customer);

            return;
        }

        if (config('whatsapp_agent.use_openai')) {
            $hours = OpeningHours::forWhatsApp();

            if (! $hours['is_open'] && ! $hours['force_closed'] && $this->shouldInterceptOrderWhileClosed($session, $text)) {
                $this->handleMenuItemsOutsideHours($phone, $text, $customer);

                return;
            }

            if ($this->shouldHandleMenuItemsInPhp($session, $text) && $this->captureMenuItemsFromUserText($phone, $text, $customer)) {
                return;
            }
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

            $this->setSession($phone, array_merge($session, [
                'state' => 'ordering',
                'pending_variant' => null,
            ]));
            $this->askSideOrExtras($phone, $customer);

            return;
        }

        if ($this->completePendingVariantFromText($phone, $text, $customer)) {
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
                $this->setSession($phone, array_merge($session, [
                    'state' => 'ordering',
                    'pending_variant' => [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'product_name' => $item['product_name'] ?? $item['name'],
                    ],
                ]));
                $this->replyText(
                    $phone,
                    "O *{$item['product_name']}* tem tamanhos {$sizes}. Qual você prefere? Responda *P*, *M* ou *G*.",
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
            'pending_variant' => null,
        ]);

        $addedLines = collect($parsed)
            ->map(fn (array $item) => "{$item['quantity']}x {$item['name']}")
            ->implode(', ');

        $this->replyText($phone, $this->render($this->message('order_added_message'), [
            'items' => $addedLines.' 🍽️',
        ]), $customer);
    }

    private function shouldHandleMenuItemsInPhp(array $session, string $text): bool
    {
        if ($this->isPureConfirmation($text)) {
            return false;
        }

        if (! $this->isMenuOrderingState($session)) {
            return false;
        }

        if (is_array($session['pending_variant'] ?? null) && ! empty($session['pending_variant']['product_id'])) {
            return true;
        }

        return $this->messageLooksLikeMenuItems($text);
    }

    /**
     * Fora do horário: interceptar pedidos no PHP (mesmo sem match exato no cardápio)
     * para a OpenAI não inventar outro prato.
     */
    private function shouldInterceptOrderWhileClosed(array $session, string $text): bool
    {
        if ($this->isPureConfirmation($text)) {
            return false;
        }

        if (! $this->isMenuOrderingState($session)) {
            return false;
        }

        if (is_array($session['pending_variant'] ?? null) && ! empty($session['pending_variant']['product_id'])) {
            return true;
        }

        return $this->messageLooksLikeMenuItems($text)
            || $this->messageLooksLikeOrderIntent($text);
    }

    /** @param  array<string, mixed>  $session */
    private function isMenuOrderingState(array $session): bool
    {
        $state = (string) ($session['state'] ?? 'welcome');

        return in_array($state, ['welcome', 'ordering'], true);
    }

    /**
     * Registra itens pelo PHP antes da OpenAI (fora do horário) para não trocar o prato.
     */
    private function handleMenuItemsOutsideHours(string $phone, string $text, ?Customer $customer): void
    {
        if ($this->completePendingVariantFromText($phone, $text, $customer)) {
            return;
        }

        $parsed = $this->parseProductsFromText($text);

        if ($parsed === []) {
            $this->handleOrdering($phone, $text, $customer);

            return;
        }

        foreach ($parsed as $item) {
            if ($item['needs_variant'] ?? false) {
                $this->handleOrdering($phone, $text, $customer);

                return;
            }
        }

        $result = $this->toolAddParsedItems($phone, $parsed, $customer);
        $status = OpeningHours::forWhatsApp();
        $added = implode(', ', $result['added']);

        $this->replyText($phone, implode("\n", [
            'No momento estamos *fechados*. Abrimos *'.$status['next_open_day_label'].'* às *'.$status['opening_label'].'*.',
            '',
            'Anotei: *'.$added.'*.',
            '',
            'Pode continuar pedindo ou digite *pronto* quando terminar. Seu pedido será agendado para *'.$status['next_open_day_label'].'*.',
        ]), $customer);
    }

    /**
     * @return bool True quando o PHP já respondeu ao cliente (não chamar OpenAI).
     */
    private function captureMenuItemsFromUserText(string $phone, string $text, ?Customer $customer): bool
    {
        if ($this->completePendingVariantFromText($phone, $text, $customer)) {
            return true;
        }

        $parsed = $this->parseProductsFromText($text);

        if ($parsed === []) {
            return false;
        }

        foreach ($parsed as $item) {
            if ($item['needs_variant'] ?? false) {
                $this->handleOrdering($phone, $text, $customer);

                return true;
            }
        }

        $this->toolAddParsedItems($phone, $parsed, $customer);

        return false;
    }

    private function completePendingVariantFromText(string $phone, string $text, ?Customer $customer): bool
    {
        $session = $this->getSession($phone);
        $pending = $session['pending_variant'] ?? null;

        if (! is_array($pending) || empty($pending['product_id'])) {
            return false;
        }

        if ($this->isPureConfirmation($text)) {
            return false;
        }

        $product = Product::query()
            ->with(['variants' => fn ($query) => $query->where('is_available', true)->orderBy('sort_order')])
            ->find($pending['product_id']);

        if (! $product) {
            $this->setSession($phone, array_merge($session, ['pending_variant' => null]));

            return false;
        }

        if ($this->userTextMentionsDifferentProduct($text, $product)) {
            $this->setSession($phone, array_merge($session, ['pending_variant' => null]));

            return false;
        }

        $size = $this->parseSizeToken($text) ?? $this->extractVariantHint(mb_strtolower(trim($text)))[1];

        if ($size === null) {
            return false;
        }

        $variant = $this->resolveVariant($product, $size);

        if (! $variant) {
            $this->replyText(
                $phone,
                "Tamanho inválido para *{$product->name}*. Escolha {$this->variantSizeList($product)}.",
                $customer
            );

            return true;
        }

        $quantity = max(1, (int) ($pending['quantity'] ?? 1));
        $cart = $session['cart'] ?? [];
        $cartKey = $product->id.'|'.$variant->id;
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
                'variant_id' => $variant->id,
                'quantity' => $quantity,
            ];
        }

        $this->setSession($phone, [
            'state' => 'ordering',
            'cart' => $cart,
            'pending_variant' => null,
        ]);

        $this->replyText($phone, $this->render($this->message('order_added_message'), [
            'items' => "{$quantity}x {$product->name} ({$variant->label}) 🍽️",
        ]), $customer);

        return true;
    }

    private function askSideOrExtras(string $phone, ?Customer $customer): void
    {
        $session = $this->getSession($phone);

        if (SideOptions::neededForCart($session['cart'] ?? []) && blank($session['side'] ?? null)) {
            $this->setSession($phone, array_merge($session, ['state' => 'side']));
            $this->replyText($phone, $this->render($this->message('side_message'), [
                'options' => SideOptions::listForMessage(),
            ]), $customer);

            return;
        }

        if (! ($session['extras_completed'] ?? false)) {
            $this->setSession($phone, array_merge($session, ['state' => 'extras']));
            $this->replyText($phone, $this->message('extras_message'), $customer);

            return;
        }

        $this->askForAddress($phone, $customer);
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
            'extras_notes' => trim($text),
            'extras_completed' => true,
        ]));

        $this->askForAddress($phone, $customer);
    }

    private function askForAddress(string $phone, ?Customer $customer): void
    {
        $session = $this->getSession($phone);
        $saved = $this->savedDeliveryAddress($customer);

        if ($saved !== null) {
            $this->setSession($phone, array_merge($session, [
                'state' => 'address',
                'saved_address' => $saved,
                'saved_address_prompt' => true,
            ]));

            $this->replyText($phone, $this->render($this->message('address_confirm_message'), [
                'address' => $saved,
            ]), $customer);

            return;
        }

        $this->setSession($phone, array_merge($session, [
            'state' => 'address',
            'saved_address' => null,
            'saved_address_prompt' => false,
        ]));

        $this->replyText($phone, $this->message('address_message'), $customer);
    }

    private function handleAddress(string $phone, string $text, ?Customer $customer): void
    {
        $session = $this->getSession($phone);
        $command = mb_strtolower(trim($text));

        if ($this->matchesIntent($command, ['retirada', 'retirar', 'balcão', 'balcao', 'buscar', 'pegar'])) {
            $this->setSession($phone, array_merge($session, [
                'state' => OrderSchedule::enabled() ? 'schedule' : 'payment',
                'order_type' => 'takeaway',
                'delivery_address' => null,
                'delivery_fee' => 0,
                'delivery_area_id' => null,
                'distance_km' => null,
                'saved_address_prompt' => false,
            ]));

            $this->proceedToSchedule($phone, $customer);

            return;
        }

        if (($session['saved_address_prompt'] ?? false) === true) {
            if ($this->confirmsSavedAddress($command)) {
                $text = (string) ($session['saved_address'] ?? '');
            } elseif ($this->declinesSavedAddress($command)) {
                $this->setSession($phone, array_merge($session, [
                    'saved_address_prompt' => false,
                ]));
                $this->replyText($phone, $this->message('address_message'), $customer);

                return;
            }
        }

        $address = trim($text);

        if ($address === '') {
            $this->replyText($phone, $this->message('address_message'), $customer);

            return;
        }

        $quote = $this->deliveryFeeService->quoteForAddress($address);

        if ($quote === null) {
            $this->replyText($phone, $this->deliveryFailureMessage($address), $customer);

            return;
        }

        $fee = number_format($quote['delivery_fee'], 2, ',', '.');
        $km = number_format($quote['distance_km'], 1, ',', '.');

        $this->replyText($phone, $this->render($this->message('delivery_quote_message'), [
            'distance_km' => $km,
            'delivery_fee' => $fee,
        ]), $customer);

        $this->setSession($phone, array_merge($session, [
            'state' => OrderSchedule::enabled() ? 'schedule' : 'payment',
            'order_type' => 'delivery',
            'delivery_address' => $address,
            'delivery_fee' => $quote['delivery_fee'],
            'delivery_area_id' => $quote['delivery_area_id'],
            'distance_km' => $quote['distance_km'],
            'saved_address_prompt' => false,
        ]));

        $this->proceedToSchedule($phone, $customer);
    }

    private function savedDeliveryAddress(?Customer $customer): ?string
    {
        return $customer?->resolvedDeliveryAddress();
    }

    private function formatCustomerAddress(Customer $customer): ?string
    {
        return $customer->formattedDeliveryAddress();
    }

    private function confirmsSavedAddress(string $command): bool
    {
        return $this->isPureConfirmation($command);
    }

    private function isPureConfirmation(string $command): bool
    {
        $command = mb_strtolower(trim($command));

        return $this->matchesIntent($command, [
            'sim', 's', 'ss', 'yes', 'ok', 'okay', 'pode', 'pode ser', 'isso', 'isso mesmo',
            'mesmo', 'esse', 'esse mesmo', 'este', 'este mesmo', 'confirmar', 'confirmo',
            'mesmo endereço', 'mesmo endereco', 'o mesmo',
        ]) || (bool) preg_match('/^(sim|isso|mesmo|esse|este)(\s|,|!|\.|$)/u', $command);
    }

    private function declinesSavedAddress(string $command): bool
    {
        return $this->matchesIntent($command, [
            'nao', 'não', 'n', 'no', 'outro', 'outra', 'mudar', 'trocar', 'novo', 'nova',
            'diferente', 'alterar', 'outro endereço', 'outro endereco', 'endereço novo', 'endereco novo',
        ]) || (bool) preg_match('/^(nao|não|outro|mudar|trocar|novo)/u', $command);
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
        $this->replyText($phone, OrderSchedule::schedulePrompt(), $customer);
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
        $method = PaymentMethod::detect($text) ?? PaymentMethod::normalize($text);

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
            $order = $this->createOrder($phone, $customer, array_merge($session, ['state' => 'pix_wait']), awaitingPixProof: true);

            if (! $order) {
                $this->replyText($phone, 'Não foi possível registrar o pedido. Tente novamente.', $customer);

                return;
            }

            $this->replyText($phone, $this->render($this->message('pix_message'), [
                'pix_key' => $pixKey,
            ])."\n\nPedido *{$order->order_number}* já registrado. Assim que enviar o comprovante, confirmamos.", $customer, $order);

            return;
        }

        $this->setSession($phone, $session);
        $order = $this->createOrder($phone, $customer, $session);

        if (! $order) {
            $this->replyText($phone, 'Não foi possível registrar o pedido. Tente novamente.', $customer);
        }
    }

    private function handlePixWait(string $phone, string $text, ?Customer $customer, array $payload): void
    {
        $hasImage = data_get($payload, 'message.imageMessage') !== null
            || data_get($payload, 'message.documentMessage') !== null
            || mb_strtolower(trim($text)) === '[imagem]';

        $command = mb_strtolower(trim($text));
        $looksLikeProof = $hasImage
            || $this->matchesIntent($command, [
                'paguei', 'comprovante', 'pix feito', 'enviado', 'enviada', 'enviei',
                'feito', 'ok', 'pronto', 'já paguei', 'ja paguei', 'pago', 'paguei o pix',
            ]);

        if (! $looksLikeProof) {
            $this->replyText($phone, 'Assim que fizer o pagamento Pix, envie o comprovante (foto ou mensagem) para confirmarmos seu pedido.', $customer);

            return;
        }

        $session = $this->getSession($phone);

        if (filled($session['order_id'] ?? null)) {
            $order = Order::query()->with('items.product')->find($session['order_id']);

            if ($order) {
                $this->clearSession($phone);
                WhatsAppBotPause::forgetAiHistory($phone);

                $this->replyText($phone, $this->render($this->message('confirmed_message'), [
                    'order_number' => $order->order_number,
                    'total' => number_format((float) $order->total, 2, ',', '.'),
                    'estimated_minutes' => (string) config('whatsapp_agent.estimated_minutes', 45),
                    'scheduled_for' => OrderSchedule::formatForMessage($order->scheduled_for),
                ]), $customer, $order);

                return;
            }
        }

        $order = $this->createOrder($phone, $customer, $session);

        if (! $order) {
            $this->replyText($phone, 'Não encontrei o pedido para confirmar. Envie *oi* e refaça o pedido, por favor.', $customer);
        }
    }

    private function createOrder(string $phone, ?Customer $customer, array $session, bool $awaitingPixProof = false): ?Order
    {
        $lockKey = 'wa-create-order:'.$this->normalizedPhoneKey($phone);
        $lock = Cache::lock($lockKey, 20);

        if (! $lock->get()) {
            Log::info('WhatsApp order create skipped — lock busy', ['phone' => $phone]);

            return null;
        }

        $claimedSession = null;

        try {
            $passedSession = $session;
            $cached = $this->getSession($phone);
            // Prefer campos passados (payment_method etc.), mas nunca perder o carrinho da sessão.
            $session = array_merge($cached, $passedSession);

            if (($session['cart'] ?? []) === [] && ($cached['cart'] ?? []) !== []) {
                $session['cart'] = $cached['cart'];
            }

            if (($session['cart'] ?? []) === [] && ($passedSession['cart'] ?? []) !== []) {
                $session['cart'] = $passedSession['cart'];
            }

            $cart = $session['cart'] ?? [];

            if ($cart === [] || ($session['order_claimed'] ?? false) === true) {
                Log::info('WhatsApp order create skipped — empty or already claimed', [
                    'phone' => $phone,
                    'cart_count' => is_array($cart) ? count($cart) : 0,
                    'order_claimed' => (bool) ($session['order_claimed'] ?? false),
                    'state' => $session['state'] ?? null,
                    'order_id' => $session['order_id'] ?? null,
                    'has_payment_method' => filled($session['payment_method'] ?? null),
                ]);

                if ($awaitingPixProof && filled($session['order_id'] ?? null)) {
                    return Order::query()->find($session['order_id']);
                }

                if (filled($session['order_id'] ?? null)) {
                    return Order::query()->find($session['order_id']);
                }

                return null;
            }

            $claimedSession = $session;
            $this->setSession($phone, array_merge($session, [
                'cart' => [],
                'order_claimed' => true,
                'state' => $awaitingPixProof ? 'pix_wait' : 'creating',
            ]));

            $order = DB::transaction(function () use ($cart, $customer, $phone, $session, $awaitingPixProof) {
                $deliveryFee = (float) ($session['delivery_fee'] ?? 0);
                $orderType = $this->resolveOrderType($session);
                $notes = $this->buildOrderNotes($session);
                if ($awaitingPixProof) {
                    $notes = trim($notes."\nAguardando comprovante PIX");
                }
                $scheduledFor = $this->scheduledForFromSession($session);

                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'customer_id' => $customer?->id,
                    'type' => $orderType,
                    'delivery_area_id' => $orderType === 'delivery' ? ($session['delivery_area_id'] ?? null) : null,
                    'delivery_fee' => $orderType === 'delivery' ? $deliveryFee : 0,
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

                $order->update(['total' => $itemsTotal + (float) $order->delivery_fee]);

                if ($customer && $orderType === 'delivery' && filled($session['delivery_address'] ?? null)) {
                    $customer->update(['address' => $session['delivery_address']]);
                }

                return $order->fresh('items.product');
            });

            Log::info('WhatsApp order created', [
                'phone' => $phone,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_type' => $order->type,
                'delivery_address' => $order->delivery_address,
                'payment_method' => $order->payment_method,
                'awaiting_pix_proof' => $awaitingPixProof,
            ]);

            try {
                app(OrderPrinterService::class)->maybePrintOnCreate($order);
            } catch (\Throwable) {
                // best-effort
            }

            if ($awaitingPixProof) {
                $this->setSession($phone, array_merge($session, [
                    'state' => 'pix_wait',
                    'order_id' => $order->id,
                    'order_claimed' => true,
                    'cart' => [],
                    'payment_method' => $session['payment_method'] ?? 'pix',
                ]));

                return $order;
            }

            $this->clearSession($phone);
            WhatsAppBotPause::forgetAiHistory($phone);

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

            return $order;
        } catch (\Throwable $exception) {
            if (is_array($claimedSession)) {
                $this->setSession($phone, $claimedSession);
            }

            Log::error('Conversational WhatsApp order creation failed', [
                'phone' => $phone,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
            $this->replyText($phone, 'Não foi possível criar o pedido. Tente novamente ou entre em contato conosco.', $customer);

            return null;
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

    private function sendMenuImage(string $phone, ?Customer $customer, bool $sendFollowup = true, ?string $day = null): void
    {
        $day = $this->resolveMenuImageDay($day);
        $url = $this->menuImageUrl($day);
        $dayLabel = WeeklyMenuImages::labelFor($day);

        if (! $url) {
            Log::warning('WhatsApp menu image missing for day', [
                'day' => $day,
            ]);
            $this->replyText(
                $phone,
                "O cardápio em imagem de *{$dayLabel}* ainda não foi configurado. Me diga o prato que você quer (ex.: *strogonoff P*).",
                $customer
            );

            return;
        }

        try {
            $this->whatsAppService->sendImageToPhone($phone, $url, null, $customer, null, null, logInteraction: false, sentByBot: true);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send WhatsApp menu image', ['error' => $exception->getMessage(), 'day' => $day]);
            $this->replyText($phone, 'Não consegui enviar a imagem do cardápio agora. Me diga o prato que você quer (ex.: *strogonoff P*).', $customer);

            return;
        }

        if ($sendFollowup) {
            $followup = $this->message('menu_followup_message');
            if ($day !== WeeklyMenuImages::todayKey()) {
                $followup = "Cardápio de *{$dayLabel}*:\n\n".$followup;
            }
            $this->replyText($phone, $followup, $customer);
        }
    }

    /**
     * Explicit day wins; when closed and no day named, use the next open day's menu.
     */
    private function resolveMenuImageDay(?string $day = null): string
    {
        $resolved = WeeklyMenuImages::dayKeyFromText($day);

        if ($resolved !== null) {
            return $resolved;
        }

        if (! OpeningHours::isOpenForWhatsApp()) {
            return WeeklyMenuImages::keyForDate(OpeningHours::nextOpenDate());
        }

        return WeeklyMenuImages::todayKey();
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
            '/^(também|tambem)\s+(quero|gostaria\s+de|manda|pede)\s+/iu',
            '/^(quero\s+mais|mais\s+um|mais\s+uma|mais)\s+/iu',
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
        $query = trim($query);

        if ($query === '') {
            return ['', null];
        }

        if (($standalone = $this->parseSizeToken($query)) !== null) {
            return ['', $standalone];
        }

        $patterns = [
            '/(?:^|\s)(pequeno|pequena|p)\s*$/iu' => 'P',
            '/(?:^|\s)(m[ée]dio|m[ée]dia|m)\s*$/iu' => 'M',
            '/(?:^|\s)(grande|g)\s*$/iu' => 'G',
            '/(?:^|\s)tamanho\s+(p|m|g|pequeno|pequena|medio|média|media|médio|grande)\s*$/iu' => null,
        ];

        foreach ($patterns as $pattern => $defaultLabel) {
            if (! preg_match($pattern, $query, $matches)) {
                continue;
            }

            $matched = mb_strtolower(trim($matches[count($matches) - 1]));
            $label = $defaultLabel ?? match ($matched) {
                'p', 'pequeno', 'pequena' => 'P',
                'm', 'medio', 'médio', 'media', 'média' => 'M',
                'g', 'grande' => 'G',
                default => mb_strtoupper($matched),
            };

            $query = trim(preg_replace($pattern, '', $query) ?? $query);

            return [$query, $label];
        }

        return [$query, null];
    }

    private function parseSizeToken(string $text): ?string
    {
        $normalized = mb_strtolower(trim($text));

        return match (true) {
            (bool) preg_match('/^(p|pequeno|pequena)$/u', $normalized) => 'P',
            (bool) preg_match('/^(m|medio|m[ée]dio|media|m[ée]dia)$/u', $normalized) => 'M',
            (bool) preg_match('/^(g|grande)$/u', $normalized) => 'G',
            default => null,
        };
    }

    private function messageMentionsVariant(?string $text, string $label): bool
    {
        $text = mb_strtolower(trim((string) $text));
        $label = mb_strtoupper(trim($label));

        if ($text === '' || $label === '') {
            return false;
        }

        $patterns = match ($label) {
            'P' => '/(?:^|[^a-z0-9])(p|pequeno|pequena)(?:[^a-z0-9]|$)/u',
            'M' => '/(?:^|[^a-z0-9])(m|medio|m[ée]dio|media|m[ée]dia)(?:[^a-z0-9]|$)/u',
            'G' => '/(?:^|[^a-z0-9])(g|grande)(?:[^a-z0-9]|$)/u',
            default => '/(?:^|[^a-z0-9])'.preg_quote(mb_strtolower($label), '/').'(?:[^a-z0-9]|$)/u',
        };

        return preg_match($patterns, $text) === 1;
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

    private function userTextMentionsDifferentProduct(string $text, Product $pendingProduct): bool
    {
        $parsed = $this->parseProductsFromText($text);

        foreach ($parsed as $item) {
            if (($item['product_id'] ?? null) !== $pendingProduct->id) {
                return true;
            }
        }

        return false;
    }

    private function userTextMentionsProduct(string $text, Product $product): bool
    {
        $parsed = $this->parseProductsFromText($text);

        foreach ($parsed as $item) {
            if (($item['product_id'] ?? null) === $product->id) {
                return true;
            }
        }

        if ($parsed !== []) {
            return false;
        }

        if ($this->parseSizeToken($text) !== null) {
            return true;
        }

        $normalized = $this->normalizeOrderSegment($text);

        if ($normalized === '') {
            return false;
        }

        $queryTokens = $this->significantTokens($normalized);

        if ($queryTokens === []) {
            return false;
        }

        return $this->productMatchScore($normalized, $queryTokens, $product) >= 50;
    }

    /** @param  array<int, array{product_id: int, variant_id: ?int, quantity: int, name: string, needs_variant?: bool, product_name?: string, available_sizes?: string}>  $parsed */
    /** @return array<string, mixed> */
    private function toolAddParsedItems(string $phone, array $parsed, ?Customer $customer): array
    {
        $session = $this->getSession($phone);

        foreach ($parsed as $item) {
            if ($item['needs_variant'] ?? false) {
                $sizes = $item['available_sizes'] ?? 'P, M ou G';
                $this->setSession($phone, array_merge($session, [
                    'state' => 'ordering',
                    'pending_variant' => [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'product_name' => $item['product_name'] ?? $item['name'],
                    ],
                ]));
                $ask = "O *{$item['product_name']}* tem tamanhos {$sizes}. Qual você prefere? Responda *P*, *M* ou *G*.";

                return [
                    'ok' => false,
                    'added' => [],
                    'errors' => [$ask],
                    'needs_variant' => [[
                        'product_name' => $item['product_name'] ?? $item['name'],
                        'available_sizes' => $sizes,
                        'message' => $ask,
                    ]],
                    'ask_customer' => $ask,
                    'cart' => $this->simplifiedCart($session['cart'] ?? []),
                ];
            }
        }

        $cart = $session['cart'] ?? [];
        $added = [];

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

            $added[] = "{$item['quantity']}x {$item['name']}";
        }

        $this->setSession($phone, [
            'state' => 'ordering',
            'cart' => $cart,
            'pending_variant' => null,
        ]);

        return [
            'ok' => true,
            'added' => $added,
            'errors' => [],
            'cart' => $this->simplifiedCart($cart),
        ];
    }

    /** @param  array<int, string>  $queryTokens */
    private function productMatchScore(string $query, array $queryTokens, Product $product): int
    {
        $name = mb_strtolower($product->name);

        if ($name === $query) {
            return 1000;
        }

        if ($this->fullNameContainsQuery($query, $name)) {
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
                    || $this->tokensHaveStrongPartialMatch($queryToken, $nameToken)) {
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

    private function tokensHaveStrongPartialMatch(string $queryToken, string $nameToken): bool
    {
        $queryToken = trim($queryToken);
        $nameToken = trim($nameToken);

        if ($queryToken === '' || $nameToken === '') {
            return false;
        }

        if (mb_strlen($queryToken) < 4 || mb_strlen($nameToken) < 4) {
            return false;
        }

        return mb_stripos($nameToken, $queryToken) !== false
            || mb_stripos($queryToken, $nameToken) !== false;
    }

    private function fullNameContainsQuery(string $query, string $name): bool
    {
        $query = trim($query);
        $name = trim($name);

        if ($query === '' || $name === '') {
            return false;
        }

        if (mb_strlen($query) < 4) {
            return false;
        }

        return mb_stripos($query, $name) !== false || mb_stripos($name, $query) !== false;
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

        $status = OpeningHours::forWhatsApp();

        if ($status['force_closed']) {
            return $this->closedMessageText();
        }

        if (! $status['is_open']) {
            return 'No momento estamos *fechados*. Funcionamos de *'.$status['opening_label'].'* às *'.$status['closing_label']
                ."*.\nAbrimos *{$status['next_open_day_label']}* às *{$status['opening_label']}*."
                ."\n\nPosso *agendar* seu pedido para o próximo expediente — me diga o que deseja (ex.: *strogonoff P*).";
        }

        return 'Estamos *abertos* agora. Funcionamos de *'.$status['opening_label'].'* às *'.$status['closing_label']
            ."*.\n\nMe diga o que deseja pedir (ex.: *strogonoff P*) ou digite *pronto* quando terminar.";
    }

    /**
     * Bloqueia novos pedidos só quando o restaurante está fechado manualmente.
     * Fora do horário ainda aceitamos montagem + agendamento (não entrega "agora").
     */
    private function shouldRefuseOrdersWhileClosed(array $session): bool
    {
        $status = OpeningHours::forWhatsApp();

        if ($status['is_open'] || ! $status['force_closed']) {
            return false;
        }

        $state = (string) ($session['state'] ?? '');

        if (in_array($state, ['side', 'extras', 'address', 'schedule', 'payment', 'pix_wait'], true)) {
            return false;
        }

        if ($state === 'ordering' && ($session['cart'] ?? []) !== []) {
            return false;
        }

        return true;
    }

    private function replyClosed(string $phone, ?Customer $customer): void
    {
        $this->clearSession($phone);
        WhatsAppBotPause::forgetAiHistory($phone);
        $this->replyText($phone, $this->closedMessageText(), $customer);
    }

    private function closedMessageText(): string
    {
        $status = OpeningHours::forWhatsApp();

        return $this->render($this->message('closed_message'), [
            'opening' => $status['opening_label'],
            'closing' => $status['closing_label'],
            'next_open_day' => $status['next_open_day_label'],
        ]);
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

        $orderType = $this->resolveOrderType($session);

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

        if ($this->resolveOrderType($session) === 'takeaway') {
            $parts[] = 'Retirada no balcão';
        }

        if (filled($session['scheduled_label'] ?? null)) {
            $parts[] = 'Agendado para '.$session['scheduled_label'];
        }

        return implode(' | ', $parts);
    }

    /**
     * Prefer an explicit order_type, but never drop a quoted delivery address
     * into takeaway just because the session lost order_type.
     */
    private function resolveOrderType(array $session): string
    {
        if (filled($session['delivery_address'] ?? null)) {
            return 'delivery';
        }

        $type = $session['order_type'] ?? null;

        return $type === 'delivery' || $type === 'takeaway' ? $type : 'takeaway';
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
            ->whereHas('category', fn ($query) => $query->where('is_active', true)->availableOnDay())
            ->get()
            ->sortBy(fn ($product) => $product->category->name.'|'.$product->name)
            ->values();
    }

    private function menuImageUrl(?string $day = null): ?string
    {
        return WeeklyMenuImages::urlForDay($day ?? WeeklyMenuImages::todayKey());
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

    /** @param  array<string, mixed>  $session */
    private function shouldResumeOrderingForMoreItems(array $session, string $text): bool
    {
        if (! $this->isCheckoutInterruptibleState($session['state'] ?? null)) {
            return false;
        }

        // Confirmação explícita de endereço salvo não é "quero mais itens".
        if (($session['state'] ?? '') === 'address' && ($session['saved_address_prompt'] ?? false) === true) {
            $command = mb_strtolower(trim($text));

            if ($this->confirmsSavedAddress($command)
                || $this->declinesSavedAddress($command)
                || $this->matchesIntent($command, ['retirada', 'retirar', 'balcão', 'balcao', 'buscar', 'pegar'])) {
                return false;
            }
        }

        // Resposta de pagamento válida não é interrupção.
        if (($session['state'] ?? '') === 'payment' && PaymentMethod::detect($text) !== null) {
            return false;
        }

        // Resposta válida de acompanhamento não é interrupção.
        if (($session['state'] ?? '') === 'side' && SideOptions::resolve($text) !== null) {
            return false;
        }

        if ($this->parseProductsFromText($text) !== []) {
            return true;
        }

        return $this->wantsMoreItemsWithoutNamingThem($text);
    }

    private function isCheckoutInterruptibleState(?string $state): bool
    {
        return in_array($state, ['side', 'extras', 'address', 'schedule', 'payment'], true);
    }

    private function wantsMoreItemsWithoutNamingThem(string $text): bool
    {
        $command = mb_strtolower(trim($text));

        if ($command === '') {
            return false;
        }

        $phrases = [
            'quero mais',
            'mais um',
            'mais uma',
            'mais itens',
            'mais item',
            'adicionar',
            'incluir',
            'também quero',
            'tambem quero',
            'e mais',
            'colocar mais',
            'pedir mais',
            'mudar o pedido',
            'alterar o pedido',
            'voltar pro cardapio',
            'voltar pro cardápio',
            'voltar ao cardapio',
            'voltar ao cardápio',
        ];

        foreach ($phrases as $phrase) {
            if ($command === $phrase || str_starts_with($command, $phrase.' ') || str_contains($command, ' '.$phrase)) {
                return true;
            }
        }

        return false;
    }

    private function resumeOrderingForMoreItems(string $phone, string $text, ?Customer $customer): void
    {
        $session = $this->getSession($phone);

        $this->setSession($phone, array_merge($session, [
            'state' => 'ordering',
            'saved_address_prompt' => false,
            'delivery_fee' => null,
            'delivery_address' => null,
            'delivery_area_id' => null,
            'distance_km' => null,
            'order_type' => null,
            'scheduled_for' => null,
            'scheduled_label' => null,
            'payment_method' => null,
        ]));

        if ($this->parseProductsFromText($text) === []) {
            $this->replyText(
                $phone,
                'Claro! Me diga o que mais você quer incluir. Quando terminar, digite *pronto*.',
                $customer
            );

            return;
        }

        $this->handleOrdering($phone, $text, $customer);
    }

    /** Usado pelo agente OpenAI para não tratar prato como endereço. */
    public function messageLooksLikeMenuItems(string $text): bool
    {
        return $this->parseProductsFromText($text) !== []
            || $this->wantsMoreItemsWithoutNamingThem($text);
    }

    public function messageLooksLikeOrderIntent(string $text): bool
    {
        $command = mb_strtolower(trim($text));

        if ($command === '') {
            return false;
        }

        if ($this->wantsToCancelOrder($command) || WhatsAppMenuIntent::matches($command)) {
            return false;
        }

        $patterns = [
            '/\b(quero|gostaria|preciso|manda|pede|desejo|vou\s+(de|querer|pedir))\b/u',
            '/^\d+\s*[xX×]?\s*\S/u',
            '/\b(um|uma|uns|umas)\s+\S/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $command) === 1) {
                return true;
            }
        }

        return false;
    }

    private function wantsToCancelOrder(string $command): bool
    {
        $command = mb_strtolower(trim($command));

        if ($command === '') {
            return false;
        }

        if ($this->matchesIntent($command, ['cancelar', 'cancela', 'cancele', 'sair', 'cancel', 'desistir', 'desisto'])) {
            return true;
        }

        return preg_match('/^cancel/u', $command) === 1;
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
        $status = OpeningHours::forWhatsApp();

        return $status['opening_label'].' às '.$status['closing_label'];
    }

    /** @return array<string, mixed> */
    public function openingHoursSnapshot(): array
    {
        $status = OpeningHours::forWhatsApp();

        return [
            'is_open' => $status['is_open'],
            'force_closed' => $status['force_closed'],
            'opening' => $status['opening_label'],
            'closing' => $status['closing_label'],
            'next_open_day' => $status['next_open_day_label'],
            'label' => $status['label'],
            'detail' => $status['detail'],
            'hours_label' => $status['opening_label'].' às '.$status['closing_label'],
        ];
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
            'saved_address' => $session['saved_address'] ?? null,
            'saved_address_prompt' => (bool) ($session['saved_address_prompt'] ?? false),
        ];
    }

    public function ensureOrderingSession(string $phone): void
    {
        $session = $this->getSession($phone);
        $state = (string) ($session['state'] ?? '');

        if ($state !== '' && ! in_array($state, ['welcome', 'ordering'], true)) {
            return;
        }

        $this->setSession($phone, array_merge($session, [
            'state' => 'ordering',
            'cart' => $session['cart'] ?? [],
        ]));
    }

    public function savedAddressForPhone(string $phone, ?string $pushName = null): ?string
    {
        return $this->savedDeliveryAddress($this->resolveCustomer($phone, $pushName));
    }

    /** @return array<int, array<string, mixed>> */
    public function menuSnapshot(): array
    {
        return $this->menuProducts()->map(function (Product $product) {
            $entry = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => filled($product->description) ? (string) $product->description : null,
                'category' => $product->category->name,
                'price' => (float) $product->displayPrice(),
                'price_label' => $product->priceLabel(),
                'has_variants' => $product->hasVariants(),
                'requires_side' => (bool) $product->requires_side,
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
    public function toolSendMenuImage(string $phone, ?string $pushName, ?string $day = null): array
    {
        $customer = $this->resolveCustomer($phone, $pushName);
        $resolvedDay = $this->resolveMenuImageDay($day);
        $this->sendMenuImage($phone, $customer, sendFollowup: false, day: $resolvedDay);
        $this->setSession($phone, array_merge($this->getSession($phone), [
            'state' => 'ordering',
            'cart' => $this->getSession($phone)['cart'] ?? [],
        ]));

        $url = $this->menuImageUrl($resolvedDay);

        return [
            'ok' => true,
            'sent' => $url !== null,
            'day' => $resolvedDay,
            'day_label' => WeeklyMenuImages::labelFor($resolvedDay),
        ];
    }

    /** @param  array<string, mixed>  $arguments */
    /** @return array<string, mixed> */
    public function toolAddToCart(string $phone, array $arguments, ?string $pushName, ?string $userText = null): array
    {
        $session = $this->getSession($phone);
        $cart = $session['cart'] ?? [];
        $added = [];
        $errors = [];
        $needsVariant = [];
        $userText = trim((string) $userText);
        $customer = $this->resolveCustomer($phone, $pushName);

        if ($userText !== '' && $this->messageLooksLikeMenuItems($userText)) {
            $parsed = $this->parseProductsFromText($userText);

            if ($parsed !== []) {
                return $this->toolAddParsedItems($phone, $parsed, $customer);
            }
        }

        if ($userText !== ''
            && ! $this->isPureConfirmation($userText)
            && $this->completePendingVariantFromText($phone, $userText, $customer)) {
            $session = $this->getSession($phone);

            return [
                'ok' => true,
                'added' => ['tamanho confirmado'],
                'errors' => [],
                'cart' => $this->simplifiedCart($session['cart'] ?? []),
            ];
        }

        foreach ($arguments['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $product = $this->matchProduct((string) ($item['product_name'] ?? ''));

            if (! $product) {
                $errors[] = 'Produto não encontrado: '.($item['product_name'] ?? '?');

                continue;
            }

            $openaiLabel = isset($item['variant_label']) ? mb_strtoupper(trim((string) $item['variant_label'])) : null;
            [, $hintFromName] = $this->extractVariantHint(mb_strtolower(trim((string) ($item['product_name'] ?? ''))));
            $hintFromUser = null;
            $sizeOnly = null;

            if ($userText !== '' && $this->userTextMentionsProduct($userText, $product)) {
                [, $hintFromUser] = $this->extractVariantHint(mb_strtolower($userText));
                $sizeOnly = $this->parseSizeToken($userText);
            }

            $hint = $hintFromName ?? $hintFromUser ?? $sizeOnly;

            if ($hint === null && $openaiLabel && $this->messageMentionsVariant($userText, $openaiLabel)) {
                $hint = $openaiLabel;
            }

            $variant = $this->resolveVariant($product, $hint);

            if ($product->hasVariants() && ! $variant) {
                $sizes = $this->variantSizeList($product);
                $ask = "O *{$product->name}* tem tamanhos {$sizes}. Qual você prefere? Responda *P*, *M* ou *G*.";
                $errors[] = $ask;
                $needsVariant[] = [
                    'product_name' => $product->name,
                    'available_sizes' => $sizes,
                    'message' => $ask,
                ];
                $this->setSession($phone, array_merge($this->getSession($phone), [
                    'state' => 'ordering',
                    'pending_variant' => [
                        'product_id' => $product->id,
                        'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                        'product_name' => $product->name,
                    ],
                ]));

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

        $this->setSession($phone, array_merge($this->getSession($phone), [
            'state' => 'ordering',
            'cart' => $cart,
            'pending_variant' => $needsVariant !== []
                ? ($this->getSession($phone)['pending_variant'] ?? null)
                : null,
        ]));

        return [
            'ok' => $errors === [],
            'added' => $added,
            'errors' => $errors,
            'needs_variant' => $needsVariant,
            'ask_customer' => $needsVariant[0]['message'] ?? null,
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

        if (SideOptions::neededForCart($session['cart'] ?? []) && blank($session['side'] ?? null)) {
            $this->setSession($phone, array_merge($session, ['state' => 'side']));
            $message = $this->render($this->message('side_message'), [
                'options' => SideOptions::listForMessage(),
            ]);

            return ['ok' => true, 'next' => 'side', 'message' => $message, 'side_options' => SideOptions::all()];
        }

        if (! ($session['extras_completed'] ?? false)) {
            $this->setSession($phone, array_merge($session, ['state' => 'extras']));

            return ['ok' => true, 'next' => 'extras', 'message' => $this->message('extras_message')];
        }

        $customer = $this->resolveCustomer($phone, $pushName);
        $this->askForAddress($phone, $customer);
        $session = $this->getSession($phone);

        return [
            'ok' => true,
            'next' => 'address',
            'message' => ($session['saved_address_prompt'] ?? false)
                ? $this->render($this->message('address_confirm_message'), [
                    'address' => (string) ($session['saved_address'] ?? ''),
                ])
                : $this->message('address_message'),
        ];
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
        $customer = $this->resolveCustomer($phone, $pushName);
        $saved = $this->savedDeliveryAddress($customer);

        $payload = [
            'extras_notes' => trim($notes),
            'extras_completed' => true,
            'state' => 'address',
        ];

        if ($saved !== null) {
            $payload['saved_address'] = $saved;
            $payload['saved_address_prompt'] = true;
            $this->setSession($phone, array_merge($session, $payload));

            return [
                'ok' => true,
                'next' => 'address',
                'saved_address' => $saved,
                'message' => $this->render($this->message('address_confirm_message'), [
                    'address' => $saved,
                ]),
            ];
        }

        $payload['saved_address'] = null;
        $payload['saved_address_prompt'] = false;
        $this->setSession($phone, array_merge($session, $payload));

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
                'saved_address_prompt' => false,
            ]));

            return $this->deliveryStepResponse($phone, $customer, sendToCustomer: true);
        }

        if (($session['saved_address_prompt'] ?? false) === true) {
            if ($this->confirmsSavedAddress($command)) {
                $address = (string) ($session['saved_address'] ?? '');
            } elseif ($this->declinesSavedAddress($command)) {
                $this->setSession($phone, array_merge($session, [
                    'saved_address_prompt' => false,
                ]));

                $message = $this->message('address_message');
                $this->replyText($phone, $message, $customer);

                return [
                    'ok' => true,
                    'next' => 'address',
                    'ask_new_address' => true,
                    'message' => $message,
                    'already_sent_to_customer' => true,
                ];
            }
        }

        if (($session['saved_address_prompt'] ?? false) !== true && $this->confirmsSavedAddress($command)) {
            $saved = $this->savedDeliveryAddress($customer);

            if ($saved !== null) {
                $address = $saved;
            }
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
            'saved_address_prompt' => false,
        ]));

        $response = $this->deliveryStepResponse($phone, $customer, sendToCustomer: true, prependDeliveryQuote: true);
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
        $customer = $this->resolveCustomer($phone, $pushName);
        $this->setSession($phone, array_merge($session, [
            'state' => 'payment',
            'scheduled_for' => $resolved['datetime']?->toIso8601String(),
            'scheduled_label' => $resolved['label'],
        ]));

        if ($resolved['datetime'] !== null) {
            $this->replyText($phone, 'Perfeito! Seu pedido ficou agendado para *'.$resolved['label'].'*.', $customer);
        }

        $this->sendPaymentSummary($phone, $customer);

        return [
            'ok' => true,
            'scheduled_label' => $resolved['label'],
            'next' => 'payment',
            'summary' => $this->buildSummary($this->getSession($phone)),
            'already_sent_to_customer' => true,
            'ask_payment_method' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function deliveryStepResponse(
        string $phone,
        ?Customer $customer = null,
        bool $sendToCustomer = false,
        bool $prependDeliveryQuote = false,
    ): array {
        $session = $this->getSession($phone);
        $parts = [];

        if ($prependDeliveryQuote && ($session['order_type'] ?? null) === 'delivery') {
            $parts[] = $this->render($this->message('delivery_quote_message'), [
                'distance_km' => number_format((float) ($session['distance_km'] ?? 0), 1, ',', '.'),
                'delivery_fee' => number_format((float) ($session['delivery_fee'] ?? 0), 2, ',', '.'),
            ]);
        }

        if (($session['state'] ?? '') === 'schedule' && ! filled($session['scheduled_label'] ?? null)) {
            $parts[] = OrderSchedule::schedulePrompt();
            $message = implode("\n\n", array_filter($parts));

            if ($sendToCustomer) {
                $this->replyText($phone, $message, $customer);
            }

            return [
                'ok' => true,
                'order_type' => $this->resolveOrderType($session),
                'next' => 'schedule',
                'message' => $message,
                'already_sent_to_customer' => $sendToCustomer,
            ];
        }

        $this->setSession($phone, array_merge($session, ['state' => 'payment']));

        if ($sendToCustomer) {
            if ($parts !== []) {
                $this->replyText($phone, implode("\n\n", $parts), $customer);
            }

            $this->sendPaymentSummary($phone, $customer);
        }

        $session = $this->getSession($phone);

        return [
            'ok' => true,
            'order_type' => $this->resolveOrderType($session),
            'next' => 'payment',
            'message' => implode("\n\n", $parts),
            'summary' => $this->buildSummary($session),
            'already_sent_to_customer' => $sendToCustomer,
            'ask_payment_method' => true,
        ];
    }

    /** @return array<string, mixed> */
    public function toolSetPayment(string $phone, string $methodText, ?string $pushName, array $payload = []): array
    {
        $userText = trim((string) ($payload['user_text'] ?? ''));

        // A escolha do cliente prevalece — a LLM não pode inventar Pix após "sim" do endereço.
        $method = PaymentMethod::detect($userText);

        if ($method === null && $userText === '') {
            $method = PaymentMethod::detect($methodText) ?? PaymentMethod::normalize($methodText);
        }

        if ($method === null) {
            return [
                'ok' => false,
                'error' => 'O cliente ainda não escolheu a forma de pagamento nesta mensagem. Pergunte Pix, dinheiro ou cartão e aguarde a resposta. NÃO invente Pix.',
                'ask_payment_method' => true,
                'next' => 'payment',
            ];
        }

        $session = $this->getSession($phone);
        $customer = $this->resolveCustomer($phone, $pushName);
        $state = (string) ($session['state'] ?? '');

        if ($state === 'pix_wait' && $method !== 'pix') {
            $this->switchAwayFromPix($phone, $method, $customer);

            return [
                'ok' => true,
                'order_created' => true,
                'payment_method_switched' => true,
                'already_sent_to_customer' => true,
                'payment_method' => $method,
            ];
        }

        if ($state !== 'payment') {
            return [
                'ok' => false,
                'error' => 'Ainda não é a etapa de pagamento (estado atual: '.($state !== '' ? $state : 'vazio').'). Conclua endereço/horário e só então pergunte a forma de pagamento. NÃO chame set_payment agora.',
                'state' => $state,
                'next' => $state !== '' ? $state : 'payment',
            ];
        }

        $session['payment_method'] = $method;

        if (($session['cart'] ?? []) === [] && ! filled($session['order_id'] ?? null)) {
            Log::warning('WhatsApp set_payment without cart', [
                'phone' => $phone,
                'state' => $session['state'] ?? null,
                'method' => $method,
            ]);

            return [
                'ok' => false,
                'error' => 'Carrinho vazio — não posso finalizar. Peça os itens de novo com add_to_cart.',
            ];
        }

        if (! in_array($session['order_type'] ?? null, ['delivery', 'takeaway'], true)
            && blank($session['delivery_address'] ?? null)) {
            return [
                'ok' => false,
                'error' => 'Ainda não sei se é *entrega* ou *retirada*. Peça o endereço ou *retirada* com quote_delivery antes do pagamento.',
                'next' => 'address',
            ];
        }

        // Normalize inferred delivery before createOrder.
        $session['order_type'] = $this->resolveOrderType($session);

        if ($method === 'pix') {
            $pixKey = config('whatsapp_agent.pix_key');

            if (! filled($pixKey)) {
                return ['ok' => false, 'error' => 'Chave Pix não configurada no sistema.'];
            }

            $this->setSession($phone, array_merge($session, ['state' => 'pix_wait']));
            $order = $this->createOrder($phone, $customer, array_merge($session, ['state' => 'pix_wait']), awaitingPixProof: true);

            if (! $order) {
                return ['ok' => false, 'error' => 'Não foi possível registrar o pedido. Tente novamente.'];
            }

            $pixMessage = $this->render($this->message('pix_message'), ['pix_key' => $pixKey]);
            $this->replyText($phone, $pixMessage."\n\nPedido *{$order->order_number}* já registrado. Assim que enviar o comprovante, confirmamos.", $customer, $order);

            return [
                'ok' => true,
                'awaiting_pix_proof' => true,
                'already_sent_to_customer' => true,
                'order_created' => true,
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'pix_key' => $pixKey,
            ];
        }

        $this->setSession($phone, $session);
        $order = $this->createOrder($phone, $customer, $session);

        if (! $order) {
            return ['ok' => false, 'error' => 'Não foi possível registrar o pedido. Tente novamente.'];
        }

        return [
            'ok' => true,
            'order_created' => true,
            'already_sent_to_customer' => true,
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ];
    }

    /** @return array<string, mixed> */
    public function toolCancelOrder(string $phone, ?string $pushName): array
    {
        $this->clearSession($phone);
        WhatsAppBotPause::forgetAiHistory($phone);

        return ['ok' => true, 'message' => $this->message('cancel_message')];
    }

    /**
     * Usado pela OpenAI quando ela responde em texto sem chamar set_payment.
     *
     * @return array<string, mixed>|null
     */
    public function forceFinalizePaymentFromUserText(string $phone, string $text, ?string $pushName = null, array $payload = []): ?array
    {
        $session = $this->getSession($phone);
        $method = PaymentMethod::detect($text);

        if ($method === null) {
            return null;
        }

        $state = (string) ($session['state'] ?? '');

        if ($state === 'pix_wait' && $method !== 'pix') {
            $this->switchAwayFromPix($phone, $method, $this->resolveCustomer($phone, $pushName));

            return [
                'ok' => true,
                'payment_method_switched' => true,
                'already_sent_to_customer' => true,
                'payment_method' => $method,
            ];
        }

        if ($state !== 'payment') {
            return null;
        }

        return $this->toolSetPayment($phone, $method, $pushName, array_merge($payload, [
            'user_text' => $text,
        ]));
    }

    private function switchAwayFromPix(string $phone, string $method, ?Customer $customer): void
    {
        $session = $this->getSession($phone);
        $order = null;

        if (filled($session['order_id'] ?? null)) {
            $order = Order::query()->with('items.product')->find($session['order_id']);
        }

        if ($order) {
            $notes = preg_replace('/\s*Aguardando comprovante PIX\s*/u', "\n", (string) $order->notes) ?? '';
            $order->update([
                'payment_method' => $method,
                'notes' => trim($notes) !== '' ? trim($notes) : null,
            ]);

            $this->clearSession($phone);
            WhatsAppBotPause::forgetAiHistory($phone);

            $template = str_replace('Comprovante recebido e ', '', $this->message('confirmed_message'));
            $this->replyText($phone, $this->render($template, [
                'order_number' => $order->order_number,
                'total' => number_format((float) $order->total, 2, ',', '.'),
                'estimated_minutes' => (string) config('whatsapp_agent.estimated_minutes', 45),
                'scheduled_for' => OrderSchedule::formatForMessage($order->scheduled_for),
            ])."\n\nForma de pagamento atualizada para *".PaymentMethod::label($method).'*.', $customer, $order->fresh());

            return;
        }

        // Pedido Pix ainda não existia — volta para a etapa de pagamento e finaliza.
        $this->setSession($phone, array_merge($session, [
            'state' => 'payment',
            'payment_method' => $method,
            'order_claimed' => false,
            'order_id' => null,
        ]));
        $this->handlePayment($phone, $method, $customer);
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
