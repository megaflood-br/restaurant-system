<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OpenAiWhatsAppAgentService
{
    public function __construct(
        private readonly OpenAiClient $openAi,
        private readonly ConversationalWhatsAppBotService $bot,
    ) {}

    public function isAvailable(): bool
    {
        return (bool) config('whatsapp_agent.use_openai') && $this->openAi->isConfigured();
    }

    public function handle(string $phone, string $text, ?string $pushName = null, array $payload = []): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        try {
            $this->appendHistory($phone, 'user', $text);
            $messages = $this->buildMessages($phone, $pushName);
            $tools = $this->toolDefinitions();
            $rounds = 0;
            $maxRounds = (int) config('openai.max_tool_rounds', 4);

            while ($rounds < $maxRounds) {
                $rounds++;
                $response = $this->openAi->chat($messages, $tools);
                $choice = $response['choices'][0]['message'] ?? null;

                if (! is_array($choice)) {
                    break;
                }

                $toolCalls = $choice['tool_calls'] ?? [];

                if ($toolCalls === []) {
                    $reply = trim((string) ($choice['content'] ?? ''));

                    if ($reply !== '') {
                        $this->bot->replyToCustomer($phone, $reply, $pushName);
                        $this->appendHistory($phone, 'assistant', $reply);
                    }

                    return true;
                }

                $messages[] = $choice;

                foreach ($toolCalls as $toolCall) {
                    $name = (string) data_get($toolCall, 'function.name', '');
                    $arguments = json_decode((string) data_get($toolCall, 'function.arguments', '{}'), true);
                    $arguments = is_array($arguments) ? $arguments : [];

                    $result = $this->executeTool($phone, $pushName, $payload, $name, $arguments);

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => data_get($toolCall, 'id'),
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('OpenAI WhatsApp agent failed', [
                'phone' => $phone,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function buildMessages(string $phone, ?string $pushName): array
    {
        $session = $this->bot->sessionSnapshot($phone);
        $menu = $this->bot->menuSnapshot();

        $system = implode("\n", [
            'Você é a atendente virtual do restaurante no WhatsApp.',
            'Nome do restaurante: '.$this->bot->restaurantDisplayName(),
            'Cliente: '.($pushName ?: 'Cliente'),
            'Horário: '.$this->bot->openingHoursLabel(),
            'Objetivo: ajudar a montar pedido (itens com tamanho P/M/G quando existir), entrega ou retirada, pagamento e Pix.',
            'Use SEMPRE as ferramentas para consultar cardápio, adicionar itens, ver carrinho e avançar etapas.',
            'Nunca invente pratos ou preços — consulte get_menu.',
            'Seja breve, clara e amigável em português do Brasil.',
            'Estado atual da sessão: '.json_encode($session, JSON_UNESCAPED_UNICODE),
            'Cardápio resumido: '.json_encode($menu, JSON_UNESCAPED_UNICODE),
        ]);

        $messages = [
            ['role' => 'system', 'content' => $system],
        ];

        foreach ($this->history($phone) as $entry) {
            $messages[] = $entry;
        }

        return $messages;
    }

    /** @param  array<string, mixed>  $arguments */
    /** @return array<string, mixed> */
    private function executeTool(string $phone, ?string $pushName, array $payload, string $name, array $arguments): array
    {
        return match ($name) {
            'get_menu' => ['ok' => true, 'menu' => $this->bot->menuSnapshot()],
            'get_opening_hours' => ['ok' => true, 'hours' => $this->bot->openingHoursLabel()],
            'send_menu_image' => $this->bot->toolSendMenuImage($phone, $pushName),
            'add_to_cart' => $this->bot->toolAddToCart($phone, $arguments, $pushName),
            'view_cart' => $this->bot->toolViewCart($phone),
            'finalize_items' => $this->bot->toolFinalizeItems($phone, $pushName),
            'set_extras' => $this->bot->toolSetExtras($phone, (string) ($arguments['notes'] ?? ''), $pushName),
            'quote_delivery' => $this->bot->toolQuoteDelivery($phone, (string) ($arguments['address'] ?? ''), $pushName),
            'set_payment' => $this->bot->toolSetPayment($phone, (string) ($arguments['method'] ?? ''), $pushName, $payload),
            'cancel_order' => $this->bot->toolCancelOrder($phone, $pushName),
            default => ['ok' => false, 'error' => 'Ferramenta desconhecida.'],
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function toolDefinitions(): array
    {
        return [
            ['type' => 'function', 'function' => [
                'name' => 'get_menu',
                'description' => 'Lista produtos disponíveis com variações P/M/G e preços.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'get_opening_hours',
                'description' => 'Retorna horário de funcionamento.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'send_menu_image',
                'description' => 'Envia imagem do cardápio do dia.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'add_to_cart',
                'description' => 'Adiciona itens ao pedido.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'product_name' => ['type' => 'string'],
                                    'variant_label' => ['type' => 'string', 'description' => 'P, M ou G quando o produto tiver tamanhos'],
                                    'quantity' => ['type' => 'integer'],
                                ],
                                'required' => ['product_name', 'quantity'],
                            ],
                        ],
                    ],
                    'required' => ['items'],
                ],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'view_cart',
                'description' => 'Mostra itens e total parcial do carrinho.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'finalize_items',
                'description' => 'Cliente terminou de pedir pratos; avança para observações/talher.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'set_extras',
                'description' => 'Registra observações do pedido e pede endereço.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['notes'],
                ],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'quote_delivery',
                'description' => 'Calcula taxa de entrega ou registra retirada no balcão.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'address' => ['type' => 'string', 'description' => 'Endereço completo ou palavra retirada/balcão'],
                    ],
                    'required' => ['address'],
                ],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'set_payment',
                'description' => 'Define forma de pagamento: pix, dinheiro, cartão de crédito ou débito.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'method' => ['type' => 'string'],
                    ],
                    'required' => ['method'],
                ],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'cancel_order',
                'description' => 'Cancela pedido em andamento.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
            ]],
        ];
    }

    private function appendHistory(string $phone, string $role, string $content): void
    {
        $history = $this->history($phone);
        $history[] = ['role' => $role, 'content' => $content];
        $max = (int) config('openai.max_history_messages', 16);

        if (count($history) > $max) {
            $history = array_slice($history, -$max);
        }

        Cache::put($this->historyKey($phone), $history, now()->addMinutes(60));
    }

    /** @return array<int, array{role: string, content: string}> */
    private function history(string $phone): array
    {
        $history = Cache::get($this->historyKey($phone), []);

        return is_array($history) ? $history : [];
    }

    private function historyKey(string $phone): string
    {
        return 'whatsapp_ai_history:'.($this->bot->normalizedPhoneKey($phone));
    }
}
