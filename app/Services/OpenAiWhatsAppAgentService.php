<?php

namespace App\Services;

use App\Support\SideOptions;
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

                    // Se o cliente indicou pagamento e a LLM não chamou set_payment, finalize no PHP.
                    $forced = $this->bot->forceFinalizePaymentFromUserText($phone, $text, $pushName, $payload);

                    if ($forced !== null) {
                        $this->appendHistory($phone, 'assistant', $forced['ok'] ? 'OK' : ($forced['error'] ?? 'erro'));

                        return true;
                    }

                    if ($reply !== '') {
                        $this->bot->replyToCustomer($phone, $reply, $pushName);
                        $this->appendHistory($phone, 'assistant', $reply);
                    }

                    return true;
                }

                $messages[] = $choice;

                $alreadySentToCustomer = false;

                foreach ($toolCalls as $toolCall) {
                    $name = (string) data_get($toolCall, 'function.name', '');
                    $arguments = json_decode((string) data_get($toolCall, 'function.arguments', '{}'), true);
                    $arguments = is_array($arguments) ? $arguments : [];

                    $result = $this->executeTool($phone, $pushName, array_merge($payload, [
                        'user_text' => $text,
                    ]), $name, $arguments);

                    if (! empty($result['already_sent_to_customer'])) {
                        $alreadySentToCustomer = true;
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => data_get($toolCall, 'id'),
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }

                // PHP já enviou a resposta (ex.: chave Pix + pedido criado) — não deixe a LLM inventar outra.
                if ($alreadySentToCustomer) {
                    $this->appendHistory($phone, 'assistant', 'OK');

                    return true;
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
            'Horário de funcionamento: '.$this->bot->openingHoursLabel(),
            'Status agora: '.json_encode($this->bot->openingHoursSnapshot(), JSON_UNESCAPED_UNICODE),
            'Agora no restaurante: '.now()->timezone(config('app.timezone'))->format('d/m/Y H:i').' ('.config('app.timezone').')',
            'Objetivo: ajudar a montar pedido (itens com tamanho P/M/G quando existir), entrega ou retirada, horário (agora ou agendado), pagamento e Pix.',
            'Fluxo: itens → acompanhamento (set_side, só se o pedido tiver pratos com requires_side=true) → observações → endereço/retirada → horário (set_schedule) → pagamento → confirmação.',
            'Se force_closed=true, NÃO inicie pedido nem chame ferramentas de carrinho: informe que está fechado e diga quando abre.',
            'Se is_open=false e force_closed=false, ACEITE montar o pedido e AGENDAR para o próximo expediente (use next_open_day_label do get_opening_hours — pode ser segunda-feira se domingo estiver fechado). NÃO ofereça entrega "agora" e NÃO chame set_schedule com "agora".',
            'Se o estado da sessão for "side", e o cliente escolher fritas/legumes, use set_side. Se em vez disso pedir OUTRO prato (ex.: "quero mais um strogonoff"), use add_to_cart.',
            'Se o estado for address/extras/schedule/payment e o cliente pedir mais itens ou nomear um prato, NÃO insista no endereço/pagamento: chame add_to_cart (ou diga para informar o prato) e só depois finalize_items.',
            'NUNCA chame quote_delivery com texto que seja nome de prato (ex.: "strogonoff P"). Isso não é endereço.',
            'Nunca diga que "houve um erro ao adicionar" se a ferramenta não retornou erro real (ok=false).',
            'Se o cliente já tiver endereço cadastrado, após set_extras a ferramenta devolve a confirmação. Se o cliente disser sim/mesmo, chame quote_delivery com "sim". Se disser não/outro, peça o endereço novo e depois quote_delivery.',
            'Depois de quote_delivery/set_schedule, se already_sent_to_customer=true, NÃO chame set_payment e NÃO invente Pix — apenas OK. O PHP já perguntou horário ou forma de pagamento.',
            'NUNCA chame set_payment no mesmo turno em que o cliente só confirmou endereço (sim/mesmo). Espere a resposta explícita: pix, dinheiro ou cartão.',
            'NUNCA invente forma de pagamento. Pix só se o cliente pediu Pix.',
            'Se o cliente já mencionar horário durante o pedido (ex.: "para às 12h", "as 11hs"), use set_schedule assim que possível.',
            'Horários como "11hs"/"11h" sem "daqui" são horário do relógio; se já passou hoje, a ferramenta agenda para amanhã.',
            'Nunca invente pratos, preços, tamanhos (P/M/G) nem ingredientes.',
            'Para dúvidas sobre o prato (ingredientes, descrição), use SOMENTE o campo description do get_menu/cardápio. Se description estiver vazio, diga que não tem esse detalhe cadastrado — NÃO invente.',
            'Se o produto tiver variações e o cliente NÃO disse o tamanho, NÃO chame add_to_cart com P/M/G inventado: pergunte o tamanho e só então adicione.',
            'Se add_to_cart retornar needs_variant ou ask_customer, repasse a pergunta ao cliente e espere a resposta — não troque o prato por outro do cardápio.',
            'Use SEMPRE as ferramentas para consultar cardápio, adicionar itens, ver carrinho e avançar etapas.',
            'Nunca invente pratos ou preços — consulte get_menu.',
            'Quando o cliente pedir cardápio/menu (ex.: "cardápio", "cardápio de hoje", "cardápio de segunda", "manda o menu"), chame OBRIGATORIAMENTE send_menu_image.',
            'Se o cliente pedir cardápio de um dia (segunda, amanhã, etc.), passe day nesse dia (monday/tuesday/... ou "segunda"/"amanhã"). Se estiver fechado e pedir só "cardápio" sem dia, passe o próximo expediente (next_open_day_label / weekday).',
            'NÃO liste o cardápio completo em texto (nomes e preços). A resposta ao pedido de cardápio é a imagem do dia.',
            'get_menu serve para montar/confirmar itens e responder dúvidas com a description cadastrada — nunca para listar o cardápio inteiro ao cliente.',
            'Após finalizar os itens (finalize_items), só chame set_side se a ferramenta pedir next=side (há pratos que pedem acompanhamento). Se next=extras ou address, siga o next retornado.',
            'Em saudação simples (oi/olá/bom dia/boa tarde), NÃO chame send_menu_image: responda só com boas-vindas e pergunte se quer fazer um pedido. Envie a imagem do cardápio somente se o cliente pedir.',
            'Com Pix, set_payment cria o pedido no sistema e já envia a chave ao cliente — não invente outra chave nem diga que houve erro se ok=true.',
            'Para dinheiro/cartão, set_payment também CRIA o pedido. Nunca diga que o pedido foi confirmado sem chamar set_payment e receber order_created=true.',
            'Se set_payment retornar ok=false, NÃO invente confirmação nem número de pedido — informe o erro e peça para tentar de novo.',
            'Se a ferramenta retornar already_sent_to_customer=true, NÃO reescreva a mensagem; responda apenas OK.',
            'Nunca diga que o pedido foi enviado à cozinha sem confirmação (Pix aguarda comprovante, mas o pedido já fica registrado).',
            'Seja breve, clara e amigável em português do Brasil.',
            'Estado atual da sessão: '.json_encode($session, JSON_UNESCAPED_UNICODE),
            'Endereço cadastrado do cliente: '.($this->bot->savedAddressForPhone($phone, $pushName) ?: 'nenhum'),
            'Cardápio resumido (uso interno): '.json_encode($menu, JSON_UNESCAPED_UNICODE),
            'Acompanhamentos disponíveis: '.json_encode(SideOptions::all(), JSON_UNESCAPED_UNICODE),
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
        if ($name === 'add_to_cart') {
            $session = $this->bot->sessionSnapshot($phone);
            $userText = (string) ($payload['user_text'] ?? '');

            // Só bloqueia add_to_cart no side se a mensagem parece escolha de acompanhamento.
            if (($session['state'] ?? '') === 'side'
                && SideOptions::resolve($userText) !== null
                && ! $this->bot->messageLooksLikeMenuItems($userText)) {
                return [
                    'ok' => false,
                    'error' => 'O cliente está escolhendo o acompanhamento. Use set_side (ex.: fritas ou legumes), não add_to_cart.',
                    'side_options' => SideOptions::all(),
                ];
            }
        }

        if ($name === 'quote_delivery') {
            $address = (string) ($arguments['address'] ?? '');

            if ($this->bot->messageLooksLikeMenuItems($address)) {
                return [
                    'ok' => false,
                    'error' => 'Isso parece um pedido de prato, não um endereço. Use add_to_cart com o item e depois finalize_items.',
                    'hint' => 'add_to_cart',
                ];
            }
        }

        return match ($name) {
            'get_menu' => ['ok' => true, 'menu' => $this->bot->menuSnapshot()],
            'get_opening_hours' => ['ok' => true, 'hours' => $this->bot->openingHoursSnapshot()],
            'send_menu_image' => $this->bot->toolSendMenuImage($phone, $pushName, isset($arguments['day']) ? (string) $arguments['day'] : null),
            'add_to_cart' => $this->bot->toolAddToCart($phone, $arguments, $pushName, $payload['user_text'] ?? null),
            'view_cart' => $this->bot->toolViewCart($phone),
            'finalize_items' => $this->bot->toolFinalizeItems($phone, $pushName),
            'set_side' => $this->bot->toolSetSide($phone, (string) ($arguments['side'] ?? ''), $pushName),
            'set_extras' => $this->bot->toolSetExtras($phone, (string) ($arguments['notes'] ?? ''), $pushName),
            'set_schedule' => $this->bot->toolSetSchedule($phone, (string) ($arguments['schedule'] ?? ''), $pushName),
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
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'get_opening_hours',
                'description' => 'Retorna horário de funcionamento e se o restaurante está aberto agora (is_open, next_open_day).',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'send_menu_image',
                'description' => 'Envia a imagem do cardápio no WhatsApp. Use SOMENTE quando o cliente pedir cardápio/menu explicitamente. Passe day quando pedir um dia (segunda, amanhã, monday…). Não use em saudação. Não liste pratos em texto.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'day' => [
                            'type' => 'string',
                            'description' => 'Dia do cardápio: monday…sunday, segunda, amanhã, hoje. Omita para hoje (ou próximo expediente se fechado).',
                        ],
                    ],
                ],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'add_to_cart',
                'description' => 'Adiciona itens ao pedido. Se o produto tiver tamanhos P/M/G e o cliente não informou o tamanho, NÃO invente: omita variant_label (a ferramenta pedirá o tamanho).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'product_name' => ['type' => 'string'],
                                    'variant_label' => ['type' => 'string', 'description' => 'Somente se o cliente pediu explicitamente P, M ou G'],
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
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'finalize_items',
                'description' => 'Cliente terminou de pedir pratos; avança para acompanhamento/observações.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'set_side',
                'description' => 'Define o acompanhamento do pedido (ex.: Batata frita, Legumes, fritas, 1, 2).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'side' => ['type' => 'string', 'description' => 'Opção de acompanhamento escolhida pelo cliente'],
                    ],
                    'required' => ['side'],
                ],
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
                'name' => 'set_schedule',
                'description' => 'Define horário do pedido: agora ou agendado (ex.: 12:30, hoje às 18h, amanhã ao meio-dia).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'schedule' => ['type' => 'string', 'description' => 'Horário desejado ou "agora"'],
                    ],
                    'required' => ['schedule'],
                ],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'quote_delivery',
                'description' => 'Calcula taxa de entrega, confirma endereço salvo (sim/mesmo), pede endereço novo, ou registra retirada no balcão.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'address' => ['type' => 'string', 'description' => 'Endereço completo, "sim"/"mesmo" para usar o cadastrado, ou palavra retirada/balcão'],
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
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
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
