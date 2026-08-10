<p class="text-sm text-gray-600 mb-6">
    Configure o agente conversacional do WhatsApp. Quando ativo, o sistema responde automaticamente seguindo o fluxo abaixo — sem depender do n8n para as mensagens.
    Placeholders: <code class="text-xs bg-gray-100 px-1 rounded">{restaurant_name}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{items}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{total}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{distance_km}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{delivery_fee}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{minutes}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{scheduled_for}</code>, <code class="text-xs bg-gray-100 px-1 rounded">{pix_key}</code>.
</p>

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
    </div>

    <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-900 space-y-2">
        <p class="font-medium">Evolution API (.env)</p>
        <p class="text-xs text-indigo-800">
            Para o bot responder, confirme no servidor: <code>EVOLUTION_ENABLED=true</code>,
            <code>EVOLUTION_API_URL</code>, <code>EVOLUTION_API_KEY</code> e <code>EVOLUTION_API_INSTANCE</code>.
        </p>
        <p class="text-xs text-indigo-800">
            Webhook na Evolution: <code class="break-all">{{ url('/api/webhooks/evolution') }}</code>
            — evento <strong>MESSAGES_UPSERT</strong>. Com <em>Webhook by Events</em> ligado, também funciona
            <code class="break-all">{{ url('/api/webhooks/evolution/messages-upsert') }}</code>.
        </p>
        <p class="text-xs text-indigo-800">
            Com OpenAI ativa, desmarque <strong>Encaminhar para n8n</strong> abaixo para evitar dois bots respondendo ao mesmo tempo.
        </p>
        <p class="text-xs text-indigo-800">
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
        'menu_followup_message' => '1b. Após enviar cardápio',
        'human_handoff_message' => 'Atendente humano (cliente pediu)',
        'bot_resumed_message' => 'Bot reativado (cliente digitou bot)',
        'side_message' => '2b. Acompanhamento (fritas/legumes)',
        'extras_message' => '3. Talher / observações',
        'address_message' => '4. Endereço ou retirada',
        'schedule_message' => '4b. Horário (agora ou agendar)',
        'payment_message' => '5. Forma de pagamento',
        'pix_message' => '5b. Instruções Pix',
        'confirmed_message' => '6. Pedido confirmado',
    ] as $field => $label)
        <div>
            <label for="{{ $field }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
            <textarea name="{{ $field }}" id="{{ $field }}" rows="4"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old($field, $whatsappAgent[$field]) }}</textarea>
            @if ($field === 'side_message')
                <p class="mt-1 text-xs text-gray-500">Placeholder: <code class="bg-gray-100 px-1 rounded">{options}</code>. Deixe as opções abaixo vazias para pular esta etapa.</p>
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
