<p class="text-sm text-gray-600 mb-6">
    Configure o agente conversacional do WhatsApp. Quando ativo, o sistema responde automaticamente seguindo o fluxo abaixo — sem depender do n8n para as mensagens.
    Placeholders: <code class="text-xs bg-gray-100 px-1 rounded">{restaurant_name}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{items}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{total}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{distance_km}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{delivery_fee}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{minutes}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{scheduled_for}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{pix_key}</code>.
</p>

{{-- Evolution API: credenciais + QR --}}
<div class="mb-8 rounded-lg border border-emerald-200 bg-emerald-50/40 p-5 space-y-5"
     x-data="evolutionPanel({
        statusUrl: @js(route('settings.evolution.status')),
        connectUrl: @js(route('settings.evolution.connect')),
        logoutUrl: @js(route('settings.evolution.logout')),
        webhookUrl: @js(route('settings.evolution.webhook')),
        csrf: @js(csrf_token()),
        configured: @js((bool) ($evolution['enabled'] && $evolution['api_key_set'] && filled($evolution['base_url']) && filled($evolution['instance']))),
     })"
     x-init="init()">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-emerald-950">Evolution API / WhatsApp</h3>
            <p class="mt-1 text-xs text-emerald-900/80">
                Salve a URL e a API Key abaixo, depois gere o QR Code e escaneie no celular
                (WhatsApp → Aparelhos conectados).
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="badgeClass()"
                  x-text="statusLabel"></span>
            <button type="button" @click="refreshStatus()"
                    class="text-xs text-emerald-800 hover:text-emerald-950 underline"
                    :disabled="busy">Atualizar</button>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.evolution.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <label class="flex items-center gap-2 text-sm font-medium text-gray-800">
            <input type="checkbox" name="evolution_enabled" value="1" @checked(old('evolution_enabled', $evolution['enabled'])) class="rounded border-gray-300 text-indigo-600">
            Evolution API ativa
        </label>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label for="evolution_base_url" class="block text-sm font-medium text-gray-700">URL da Evolution API</label>
                <input type="url" name="base_url" id="evolution_base_url" required
                    value="{{ old('base_url', $evolution['base_url']) }}"
                    placeholder="https://sua-evolution.exemplo.com"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-gray-500">Sem barra no final. Ex.: <code class="bg-white/80 px-1 rounded">http://127.0.0.1:8080</code></p>
            </div>
            <div>
                <label for="evolution_instance" class="block text-sm font-medium text-gray-700">Nome da instância</label>
                <input type="text" name="instance" id="evolution_instance" required
                    value="{{ old('instance', $evolution['instance']) }}"
                    pattern="[a-zA-Z0-9_-]+"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="evolution_api_key" class="block text-sm font-medium text-gray-700">API Key</label>
                <input type="password" name="api_key" id="evolution_api_key" autocomplete="new-password"
                    placeholder="{{ $evolution['api_key_set'] ? '•••••• (deixe em branco para manter)' : 'Cole a GLOBAL API KEY' }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @if ($evolution['api_key_set'])
                    <p class="mt-1 text-xs text-gray-500">Atual: {{ $evolution['api_key_preview'] }}</p>
                @endif
            </div>
            <div class="md:col-span-2">
                <label for="evolution_webhook_secret" class="block text-sm font-medium text-gray-700">Webhook secret (opcional)</label>
                <input type="password" name="webhook_secret" id="evolution_webhook_secret" autocomplete="new-password"
                    placeholder="{{ $evolution['webhook_secret_set'] ? '•••••• (deixe em branco para manter)' : 'Header x-webhook-secret' }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <label class="mt-2 flex items-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" name="clear_webhook_secret" value="1" class="rounded border-gray-300 text-indigo-600">
                    Remover secret
                </label>
            </div>
        </div>

        <button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-700 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-emerald-800">
            Salvar Evolution
        </button>
    </form>

    <div class="border-t border-emerald-200 pt-4 space-y-4">
        <p class="text-xs text-emerald-900/80" x-show="message" x-text="message"></p>
        <p class="text-xs text-red-700" x-show="error" x-text="error"></p>

        <div class="flex flex-wrap gap-2">
            <button type="button" @click="connect()"
                    class="inline-flex items-center px-3 py-2 bg-white border border-emerald-300 text-emerald-900 text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-emerald-50 disabled:opacity-50"
                    :disabled="busy || !configured">
                <span x-show="!busy">Gerar QR Code</span>
                <span x-show="busy" x-cloak>Aguarde…</span>
            </button>
            <button type="button" @click="setupWebhook()"
                    class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-gray-50 disabled:opacity-50"
                    :disabled="busy || !configured">
                Configurar webhook
            </button>
            <button type="button" @click="logout()"
                    class="inline-flex items-center px-3 py-2 bg-white border border-red-200 text-red-700 text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-red-50 disabled:opacity-50"
                    :disabled="busy || !configured">
                Desconectar WhatsApp
            </button>
        </div>

        <div x-show="qrcode" x-cloak class="flex flex-col sm:flex-row items-start gap-4">
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                <img :src="qrcode" alt="QR Code WhatsApp" class="w-56 h-56 object-contain">
            </div>
            <div class="text-sm text-gray-700 space-y-2 max-w-sm">
                <p class="font-medium">Como vincular</p>
                <ol class="list-decimal list-inside text-xs text-gray-600 space-y-1">
                    <li>Abra o WhatsApp no celular</li>
                    <li>Menu → Aparelhos conectados → Conectar um aparelho</li>
                    <li>Escaneie este QR Code</li>
                </ol>
                <p class="text-xs text-gray-500" x-show="pairingCode">
                    Código de pareamento: <code class="bg-white px-1 rounded font-mono" x-text="pairingCode"></code>
                </p>
                <p class="text-xs text-amber-700">O QR expira em cerca de 1 minuto. Se não escanear a tempo, clique em Gerar QR Code de novo.</p>
            </div>
        </div>

        <div class="text-xs text-gray-600 space-y-1">
            <p>Webhook do sistema: <code class="break-all bg-white/80 px-1 rounded">{{ $evolution['webhook_url'] }}</code></p>
            <p>Com “Webhook by Events”: <code class="break-all bg-white/80 px-1 rounded">{{ $evolution['webhook_url_by_events'] }}</code></p>
        </div>
    </div>
</div>

<script>
function evolutionPanel(cfg) {
    return {
        ...cfg,
        busy: false,
        state: 'unknown',
        statusLabel: 'Verificando…',
        message: '',
        error: '',
        qrcode: null,
        pairingCode: null,
        pollTimer: null,

        badgeClass() {
            const map = {
                open: 'bg-emerald-100 text-emerald-800',
                connecting: 'bg-amber-100 text-amber-800',
                close: 'bg-gray-200 text-gray-700',
                closed: 'bg-gray-200 text-gray-700',
                missing: 'bg-amber-100 text-amber-800',
                not_configured: 'bg-gray-200 text-gray-600',
                unreachable: 'bg-red-100 text-red-800',
                error: 'bg-red-100 text-red-800',
            };
            return map[this.state] || 'bg-gray-100 text-gray-700';
        },

        async init() {
            if (!this.configured) {
                this.state = 'not_configured';
                this.statusLabel = 'Não configurado — salve URL e API Key';
                return;
            }
            await this.refreshStatus();
        },

        async refreshStatus() {
            this.error = '';
            try {
                const res = await fetch(this.statusUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const json = await res.json();
                const data = json.data || {};
                this.state = data.state || 'unknown';
                this.statusLabel = data.message || this.state;
                this.configured = !!data.configured || this.configured;
                if (this.state === 'open') {
                    this.qrcode = null;
                    this.stopPoll();
                }
            } catch (e) {
                this.error = 'Falha ao consultar status.';
            }
        },

        async connect() {
            this.busy = true;
            this.error = '';
            this.message = '';
            try {
                const res = await fetch(this.connectUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: '{}',
                });
                const json = await res.json();
                if (!res.ok) {
                    this.error = json.message || 'Falha ao gerar QR Code';
                    return;
                }
                const data = json.data || {};
                this.state = data.state || this.state;
                this.message = data.message || '';
                this.statusLabel = data.message || this.statusLabel;
                this.qrcode = data.qrcode || null;
                this.pairingCode = data.pairing_code || null;
                if (this.state !== 'open') {
                    this.startPoll();
                }
            } catch (e) {
                this.error = 'Falha ao gerar QR Code.';
            } finally {
                this.busy = false;
            }
        },

        async logout() {
            if (!confirm('Desconectar este WhatsApp da Evolution?')) return;
            this.busy = true;
            this.error = '';
            try {
                const res = await fetch(this.logoutUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: '{}',
                });
                const json = await res.json();
                if (!res.ok) {
                    this.error = json.message || 'Falha ao desconectar';
                    return;
                }
                this.qrcode = null;
                this.message = (json.data && json.data.message) || 'Desconectado.';
                await this.refreshStatus();
            } catch (e) {
                this.error = 'Falha ao desconectar.';
            } finally {
                this.busy = false;
            }
        },

        async setupWebhook() {
            this.busy = true;
            this.error = '';
            this.message = '';
            try {
                const res = await fetch(this.webhookUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: '{}',
                });
                const json = await res.json();
                if (!res.ok) {
                    this.error = json.message || 'Falha ao configurar webhook';
                    return;
                }
                this.message = (json.data && json.data.message) || 'Webhook configurado.';
            } catch (e) {
                this.error = 'Falha ao configurar webhook.';
            } finally {
                this.busy = false;
            }
        },

        startPoll() {
            this.stopPoll();
            this.pollTimer = setInterval(() => this.refreshStatus(), 3000);
        },

        stopPoll() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },
    };
}
</script>

<form method="POST" action="{{ route('settings.whatsapp-agent.update') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @method('PUT')

    <div class="space-y-3 rounded-lg border border-gray-200 p-4">
        <label class="flex items-center gap-2 text-sm font-medium text-gray-800">
            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $whatsappAgent['enabled'])) class="rounded border-gray-300 text-indigo-600">
            Agente WhatsApp ativo
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="use_builtin_bot" value="1" @checked(old('use_builtin_bot', $whatsappAgent['use_builtin_bot'])) class="rounded border-gray-300 text-indigo-600">
            Usar bot integrado (conversa automática)
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="use_openai" value="1" @checked(old('use_openai', $whatsappAgent['use_openai'])) class="rounded border-gray-300 text-indigo-600"
                @disabled(! $whatsappAgent['openai_configured'])>
            Usar OpenAI para entender mensagens (recomendado)
        </label>
        @if (! $whatsappAgent['openai_configured'])
            <p class="text-xs text-amber-700">Defina <code>OPENAI_ENABLED=true</code> e <code>OPENAI_API_KEY</code> no .env do servidor.</p>
        @else
            <p class="text-xs text-gray-500">Modelo: {{ config('openai.model', 'gpt-4o-mini') }}. Se a OpenAI falhar, o bot usa o parser antigo como fallback.</p>
        @endif
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="forward_to_n8n" value="1" @checked(old('forward_to_n8n', $whatsappAgent['forward_to_n8n'])) class="rounded border-gray-300 text-indigo-600">
            Encaminhar mensagens recebidas para o n8n (opcional)
        </label>
        <p class="text-xs text-gray-500">
            <strong>Atendimento humano:</strong> o bot pausa quando você responde pelo WhatsApp ou quando o cliente pede *atendente*/*humano*.
            Para reativar o bot, o cliente digita *bot*.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="wa_restaurant_name" class="block text-sm font-medium text-gray-700">Nome no WhatsApp</label>
            <input type="text" name="restaurant_name" id="wa_restaurant_name" value="{{ old('restaurant_name', $whatsappAgent['restaurant_name']) }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="estimated_minutes" class="block text-sm font-medium text-gray-700">Tempo estimado (minutos)</label>
            <input type="number" name="estimated_minutes" id="estimated_minutes" min="5" max="240"
                value="{{ old('estimated_minutes', $whatsappAgent['estimated_minutes']) }}" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="human_pause_minutes" class="block text-sm font-medium text-gray-700">Pausar bot após humano (minutos)</label>
            <input type="number" name="human_pause_minutes" id="human_pause_minutes" min="5" max="1440"
                value="{{ old('human_pause_minutes', $whatsappAgent['human_pause_minutes']) }}" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-gray-500">Quando você ou o cliente pedir atendente, o bot fica em silêncio por esse tempo.</p>
        </div>
    </div>

    <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-4 space-y-4">
        <label class="flex items-center gap-2 text-sm font-medium text-emerald-900">
            <input type="checkbox" name="scheduling_enabled" value="1" @checked(old('scheduling_enabled', $whatsappAgent['scheduling_enabled'])) class="rounded border-gray-300 text-indigo-600">
            Permitir agendamento de pedidos (cliente pede cedo, recebe mais tarde)
        </label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="schedule_min_minutes" class="block text-sm font-medium text-gray-700">Antecedência mínima (minutos)</label>
                <input type="number" name="schedule_min_minutes" id="schedule_min_minutes" min="15" max="240"
                    value="{{ old('schedule_min_minutes', $whatsappAgent['schedule_min_minutes']) }}" required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="schedule_max_days" class="block text-sm font-medium text-gray-700">Agendar até quantos dias à frente</label>
                <select name="schedule_max_days" id="schedule_max_days" required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="0" @selected(old('schedule_max_days', $whatsappAgent['schedule_max_days']) == 0)>Somente hoje</option>
                    <option value="1" @selected(old('schedule_max_days', $whatsappAgent['schedule_max_days']) == 1)>Hoje e amanhã</option>
                    @for ($d = 2; $d <= 7; $d++)
                        <option value="{{ $d }}" @selected(old('schedule_max_days', $whatsappAgent['schedule_max_days']) == $d)>Até {{ $d }} dias</option>
                    @endfor
                </select>
            </div>
        </div>
        <p class="text-xs text-emerald-800">
            O bot pergunta se o cliente quer receber *agora* ou em um horário (ex.: *12:30*, *hoje às 18h*).
            Placeholder na confirmação: <code class="text-xs bg-white/80 px-1 rounded">{scheduled_for}</code>.
        </p>
    </div>

    <div>
        <label for="pix_key" class="block text-sm font-medium text-gray-700">Chave Pix</label>
        <input type="text" name="pix_key" id="pix_key" value="{{ old('pix_key', $whatsappAgent['pix_key']) }}"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Imagens do cardápio por dia da semana</label>
        <p class="text-xs text-gray-500 mb-4">
            O bot envia automaticamente a imagem do dia atual
            (<strong>{{ $whatsappAgent['today_menu_label'] }}</strong>).
            Cadastre uma imagem para cada dia — o cardápio muda diariamente.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach (\App\Support\WeeklyMenuImages::labels() as $day => $label)
                @php($isToday = $day === $whatsappAgent['today_menu_day'])
                <div @class([
                    'rounded-lg border p-4 space-y-3',
                    'border-indigo-300 bg-indigo-50/40 ring-1 ring-indigo-200' => $isToday,
                    'border-gray-200 bg-gray-50' => ! $isToday,
                ])>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-gray-900">{{ $label }}</span>
                        @if ($isToday)
                            <span class="text-xs font-medium text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded-full">Hoje</span>
                        @endif
                    </div>

                    @if ($whatsappAgent['menu_image_urls'][$day] ?? null)
                        <img src="{{ $whatsappAgent['menu_image_urls'][$day] }}" alt="Cardápio {{ $label }}"
                            class="w-full max-h-40 object-contain rounded-lg border border-gray-200 bg-white">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="remove_menu_images[{{ $day }}]" value="1" class="rounded border-gray-300 text-indigo-600">
                            Remover imagem
                        </label>
                    @else
                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded px-2 py-1.5">
                            Nenhuma imagem cadastrada para este dia.
                        </p>
                    @endif

                    <input type="file" name="menu_images[{{ $day }}]" accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700">
                </div>
            @endforeach
        </div>
    </div>

    @foreach ([
        'welcome_message' => '1. Boas-vindas',
        'closed_message' => '1c. Fechado manualmente (force closed)',
        'menu_followup_message' => '1b. Após enviar cardápio',
        'human_handoff_message' => 'Atendente humano (cliente pediu)',
        'bot_resumed_message' => 'Bot reativado (cliente digitou bot)',
        'side_message' => '2b. Acompanhamento (fritas/legumes)',
        'extras_message' => '3. Talher / observações',
        'address_confirm_message' => '4a. Confirmar endereço já cadastrado',
        'address_message' => '4. Endereço novo ou retirada',
        'schedule_message' => '4b. Horário (agora ou agendar)',
        'payment_message' => '5. Forma de pagamento',
        'pix_message' => '5b. Instruções Pix',
        'confirmed_message' => '6. Pedido confirmado',
    ] as $field => $label)
        <div>
            <label for="{{ $field }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
            <textarea name="{{ $field }}" id="{{ $field }}" rows="4"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old($field, $whatsappAgent[$field] ?? '') }}</textarea>
            @if ($field === 'side_message')
                <p class="mt-1 text-xs text-gray-500">Placeholder: <code class="bg-gray-100 px-1 rounded">{options}</code>. Deixe as opções abaixo vazias para pular esta etapa.</p>
            @endif
            @if ($field === 'address_confirm_message')
                <p class="mt-1 text-xs text-gray-500">Usado quando o cliente já tem endereço. Placeholder: <code class="bg-gray-100 px-1 rounded">{address}</code>.</p>
            @endif
            @if ($field === 'closed_message')
                <p class="mt-1 text-xs text-gray-500">Usado quando o cardápio está marcado como fechado. Placeholders: <code class="bg-gray-100 px-1 rounded">{opening}</code>, <code class="bg-gray-100 px-1 rounded">{closing}</code>, <code class="bg-gray-100 px-1 rounded">{next_open_day}</code>, <code class="bg-gray-100 px-1 rounded">{restaurant_name}</code>.</p>
            @endif
        </div>
    @endforeach

    <div>
        <label for="side_options" class="block text-sm font-medium text-gray-700">Opções de acompanhamento</label>
        <textarea name="side_options" id="side_options" rows="3"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            placeholder="Batata frita&#10;Legumes">{{ old('side_options', $whatsappAgent['side_options_text'] ?? '') }}</textarea>
        <p class="mt-1 text-xs text-gray-500">Uma opção por linha (ex.: Batata frita, Legumes). O bot pergunta isso depois dos pratos.</p>
    </div>

    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
        Salvar agente
    </button>
</form>
