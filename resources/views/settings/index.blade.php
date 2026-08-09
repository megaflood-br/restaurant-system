<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Configurações</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash-messages />

            <p class="text-sm text-gray-500 px-1">
                Todas as configurações do sistema ficam aqui. O arquivo <code class="text-xs bg-gray-100 px-1 rounded">.env</code> é usado apenas para infraestrutura (banco, chave da aplicação).
            </p>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="border-b border-gray-200 overflow-x-auto">
                    <nav class="flex -mb-px min-w-max">
                        @foreach ([
                            'general' => 'Geral',
                            'restaurant' => 'Restaurante',
                            'digital_menu' => 'Cardápio digital',
                            'printing' => 'Impressão',
                            'whatsapp_agent' => 'Agente WhatsApp',
                            'integration' => 'Integração',
                        ] as $key => $label)
                            <a href="{{ route('settings.index', ['tab' => $key]) }}"
                               class="px-5 py-4 text-sm font-medium border-b-2 whitespace-nowrap {{ $tab === $key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                <div class="p-6">
                    @if ($tab === 'general')
                        @include('settings.partials.general')

                    @elseif ($tab === 'restaurant')
                        <form method="POST" action="{{ route('settings.restaurant.update') }}" class="space-y-6">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="total_comandas" class="block text-sm font-medium text-gray-700">Total de comandas disponíveis</label>
                                <input type="number" name="total_comandas" id="total_comandas" min="1" max="9999"
                                    value="{{ old('total_comandas', $restaurant['total_comandas']) }}" required
                                    class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="mt-1 text-xs text-gray-500">Quantidade de números de comanda que podem ser distribuídas (ex.: 001 a 100).</p>
                            </div>

                            <div>
                                <label for="order_delay_minutes" class="block text-sm font-medium text-gray-700">Alerta de pedido atrasado (minutos)</label>
                                <input type="number" name="order_delay_minutes" id="order_delay_minutes" min="1" max="180"
                                    value="{{ old('order_delay_minutes', $restaurant['order_delay_minutes']) }}" required
                                    class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="counter_comanda_number" class="block text-sm font-medium text-gray-700">Comanda balcão / pedido avulso</label>
                                <input type="number" name="counter_comanda_number" id="counter_comanda_number" min="1" max="9999"
                                    value="{{ old('counter_comanda_number', $restaurant['counter_comanda_number']) }}" required
                                    class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="mt-1 text-xs text-gray-500">Usada ao criar pedido pelo perfil do cliente (ex.: 950).</p>
                            </div>

                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                                Salvar
                            </button>
                        </form>

                    @elseif ($tab === 'digital_menu')
                        <p class="text-sm text-gray-600 mb-6">
                            Personalize a aparência do <a href="{{ route('public.menu') }}" target="_blank" class="text-indigo-600 hover:underline">cardápio digital</a>: capa, logo, horários e informações exibidas no topo.
                        </p>

                        <form method="POST" action="{{ route('settings.digital-menu.update') }}" enctype="multipart/form-data" class="space-y-6">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Foto de capa</label>
                                    @if ($digitalMenu['cover_url'])
                                        <img src="{{ $digitalMenu['cover_url'] }}" alt="Capa" class="mb-3 h-32 w-full object-cover rounded-lg border border-gray-200">
                                        <label class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                                            <input type="checkbox" name="remove_cover" value="1" class="rounded border-gray-300 text-indigo-600"> Remover capa
                                        </label>
                                    @endif
                                    <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700">
                                    <p class="mt-1 text-xs text-gray-500">Recomendado: 1200×400 px. Máx. 4 MB.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Logo do restaurante</label>
                                    @if ($digitalMenu['logo_url'])
                                        <img src="{{ $digitalMenu['logo_url'] }}" alt="Logo" class="mb-3 h-24 w-24 object-cover rounded-full border-4 border-gray-100">
                                        <label class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-indigo-600"> Remover logo
                                        </label>
                                    @endif
                                    <input type="file" name="logo_image" accept="image/jpeg,image/png,image/webp"
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700">
                                    <p class="mt-1 text-xs text-gray-500">Formato quadrado. Aparece circular no cardápio.</p>
                                </div>
                            </div>

                            <div>
                                <label for="display_name" class="block text-sm font-medium text-gray-700">Nome exibido no cardápio</label>
                                <input type="text" name="display_name" id="display_name" value="{{ old('display_name', $digitalMenu['display_name']) }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label for="city" class="block text-sm font-medium text-gray-700">Cidade</label>
                                    <input type="text" name="city" id="city" value="{{ old('city', $digitalMenu['city']) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label for="state" class="block text-sm font-medium text-gray-700">UF</label>
                                    <input type="text" name="state" id="state" maxlength="2" value="{{ old('state', $digitalMenu['state']) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div>
                                <label for="address_line" class="block text-sm font-medium text-gray-700">Endereço / bairro (card de entrega)</label>
                                <input type="text" name="address_line" id="address_line" value="{{ old('address_line', $digitalMenu['address_line']) }}"
                                    placeholder="Ex.: Rua das Flores, 123 — Centro"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="more_info" class="block text-sm font-medium text-gray-700">Mais informações (modal)</label>
                                <textarea name="more_info" id="more_info" rows="4"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('more_info', $digitalMenu['more_info']) }}</textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-gray-200 pt-6">
                                <div>
                                    <label for="opening_time" class="block text-sm font-medium text-gray-700">Abre às</label>
                                    <input type="time" name="opening_time" id="opening_time" value="{{ old('opening_time', $digitalMenu['opening_time']) }}" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label for="closing_time" class="block text-sm font-medium text-gray-700">Fecha às</label>
                                    <input type="time" name="closing_time" id="closing_time" value="{{ old('closing_time', $digitalMenu['closing_time']) }}" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="force_closed" id="force_closed" value="1" @checked(old('force_closed', $digitalMenu['force_closed'])) class="rounded border-gray-300 text-indigo-600">
                                <label for="force_closed" class="text-sm text-gray-700">Fechar manualmente (ignorar horário)</label>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-gray-200 pt-6">
                                <div>
                                    <label for="delivery_minutes" class="block text-sm font-medium text-gray-700">Tempo de entrega (min)</label>
                                    <input type="number" name="delivery_minutes" id="delivery_minutes" min="5" max="180"
                                        value="{{ old('delivery_minutes', $digitalMenu['delivery_minutes']) }}" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label for="delivery_fee" class="block text-sm font-medium text-gray-700">Taxa de entrega (R$)</label>
                                    <input type="number" step="0.01" min="0" name="delivery_fee" id="delivery_fee"
                                        value="{{ old('delivery_fee', $digitalMenu['delivery_fee']) }}" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div class="border-t border-gray-200 pt-6 space-y-4">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" name="loyalty_enabled" id="loyalty_enabled" value="1" @checked(old('loyalty_enabled', $digitalMenu['loyalty_enabled'])) class="rounded border-gray-300 text-indigo-600">
                                    <label for="loyalty_enabled" class="text-sm text-gray-700">Exibir card de fidelidade</label>
                                </div>
                                <div>
                                    <label for="loyalty_title" class="block text-sm font-medium text-gray-700">Título fidelidade</label>
                                    <input type="text" name="loyalty_title" id="loyalty_title" value="{{ old('loyalty_title', $digitalMenu['loyalty_title']) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label for="loyalty_text" class="block text-sm font-medium text-gray-700">Texto fidelidade</label>
                                    <textarea name="loyalty_text" id="loyalty_text" rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('loyalty_text', $digitalMenu['loyalty_text']) }}</textarea>
                                </div>
                            </div>

                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                                Salvar cardápio digital
                            </button>
                        </form>

                    @elseif ($tab === 'printing')
                        <form method="POST" action="{{ route('settings.printing.update') }}" class="space-y-6">
                            @csrf
                            @method('PUT')

                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="enabled" id="printing_enabled" value="1" @checked(old('enabled', $printing['enabled'])) class="rounded border-gray-300 text-indigo-600">
                                <label for="printing_enabled" class="text-sm text-gray-700">Impressão habilitada</label>
                            </div>

                            <div>
                                <label for="restaurant_name" class="block text-sm font-medium text-gray-700">Nome no cupom</label>
                                <input type="text" name="restaurant_name" id="restaurant_name" value="{{ old('restaurant_name', $printing['restaurant_name']) }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="driver" class="block text-sm font-medium text-gray-700">Modo de impressão</label>
                                <select name="driver" id="driver" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="browser" @selected(old('driver', $printing['driver']) === 'browser')>Navegador (qualquer impressora Windows)</option>
                                    <option value="network" @selected(old('driver', $printing['driver']) === 'network')>Rede IP (impressora térmica ESC/POS)</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="auto_print_on_create" id="auto_print_on_create" value="1" @checked(old('auto_print_on_create', $printing['auto_print_on_create'])) class="rounded border-gray-300 text-indigo-600">
                                <label for="auto_print_on_create" class="text-sm text-gray-700">Imprimir automaticamente ao criar pedido</label>
                            </div>

                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="kitchen_hide_prices" id="kitchen_hide_prices" value="1" @checked(old('kitchen_hide_prices', $printing['kitchen_hide_prices'])) class="rounded border-gray-300 text-indigo-600">
                                <label for="kitchen_hide_prices" class="text-sm text-gray-700">Ocultar preços na via cozinha</label>
                            </div>

                            <div class="border-t border-gray-200 pt-6">
                                <h3 class="text-sm font-semibold text-gray-800 mb-4">Impressora de rede</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label for="network_host" class="block text-sm font-medium text-gray-700">IP da impressora</label>
                                        <input type="text" name="network_host" id="network_host" value="{{ old('network_host', $printing['network_host']) }}" placeholder="192.168.0.100"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label for="network_port" class="block text-sm font-medium text-gray-700">Porta</label>
                                        <input type="number" name="network_port" id="network_port" value="{{ old('network_port', $printing['network_port']) }}"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label for="network_timeout" class="block text-sm font-medium text-gray-700">Timeout (segundos)</label>
                                        <input type="number" name="network_timeout" id="network_timeout" value="{{ old('network_timeout', $printing['network_timeout']) }}"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label for="paper_width" class="block text-sm font-medium text-gray-700">Largura do papel (caracteres)</label>
                                        <input type="number" name="paper_width" id="paper_width" min="24" max="48" value="{{ old('paper_width', $printing['paper_width']) }}"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <p class="mt-1 text-xs text-gray-500">Padrão: 32 para bobina 58mm, 48 para 80mm.</p>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                                Salvar
                            </button>
                        </form>

                        <form method="POST" action="{{ route('settings.printing.test') }}" class="mt-4">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-yellow-700">
                                Testar impressora de rede
                            </button>
                        </form>

                    @elseif ($tab === 'whatsapp_agent')
                        @include('settings.partials.whatsapp-agent')

                    @elseif ($tab === 'integration')
                        <p class="text-sm text-gray-600 mb-6">
                            O atendimento via WhatsApp é feito no <strong>n8n</strong>. Este sistema expõe uma API REST para o n8n consultar pedidos, cardápio, comandas e registrar mensagens.
                        </p>

                        <form method="POST" action="{{ route('settings.integration.update') }}" class="space-y-6">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="api_token" class="block text-sm font-medium text-gray-700">Token da API</label>
                                <input type="text" name="api_token" id="api_token"
                                    placeholder="{{ $integration['api_token_set'] ? 'Deixe vazio para manter ('.$integration['api_token_preview'].')' : 'Será gerado automaticamente ao salvar' }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm">
                                <p class="mt-1 text-xs text-gray-500">Use no n8n: <code>Authorization: Bearer {token}</code></p>
                            </div>

                            <div>
                                <label for="n8n_webhook_url" class="block text-sm font-medium text-gray-700">Webhook do n8n (mensagens recebidas)</label>
                                <input type="url" name="n8n_webhook_url" id="n8n_webhook_url" value="{{ old('n8n_webhook_url', $integration['n8n_webhook_url']) }}"
                                    placeholder="https://seu-n8n.com/webhook/restaurante"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="mt-1 text-xs text-gray-500">Mensagens da Evolution API serão encaminhadas para esta URL.</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="forward_inbound_to_n8n" id="forward_inbound_to_n8n" value="1"
                                    @checked(old('forward_inbound_to_n8n', $integration['forward_inbound_to_n8n'])) class="rounded border-gray-300 text-indigo-600">
                                <label for="forward_inbound_to_n8n" class="text-sm text-gray-700">Encaminhar mensagens recebidas ao n8n</label>
                            </div>

                            <div>
                                <label for="default_country_code" class="block text-sm font-medium text-gray-700">Código do país (telefone)</label>
                                <input type="text" name="default_country_code" id="default_country_code" value="{{ old('default_country_code', $integration['default_country_code']) }}" required
                                    class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-indigo-700">
                                Salvar
                            </button>
                        </form>

                        <form method="POST" action="{{ route('settings.integration.regenerate-token') }}" class="mt-4"
                            onsubmit="return confirm('Gerar novo token? Atualize o n8n com o novo valor.')">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md hover:bg-gray-700">
                                Gerar novo token
                            </button>
                        </form>

                        <div class="mt-8 border-t border-gray-200 pt-6 space-y-4 text-sm">
                            <h3 class="font-semibold text-gray-900">Endpoints da API</h3>
                            <p class="text-gray-500">Base: <code class="text-indigo-700">{{ $integration['api_base_url'] }}</code></p>
                            <ul class="space-y-2 text-gray-700 font-mono text-xs">
                                <li>GET /menu</li>
                                <li>GET /orders · POST /orders · PATCH /orders/{id}/status</li>
                                <li>GET /orders/by-phone/{phone}</li>
                                <li>GET /customers · POST /customers · GET /customers/by-phone/{phone}</li>
                                <li>GET /comandas · GET /comandas/{number}</li>
                                <li>POST /whatsapp/messages · GET /whatsapp/messages</li>
                                <li>POST /whatsapp/inbound</li>
                            </ul>
                            <p class="text-gray-500">Documentação JSON: <code class="text-indigo-700">{{ $integration['api_base_url'] }}</code> (com token)</p>
                            <p class="text-gray-500">Webhook Evolution → sistema: <code class="text-indigo-700 break-all">{{ $integration['evolution_webhook_url'] }}</code></p>
                            <p class="text-xs text-gray-400">Evolution API (envio opcional): configure EVOLUTION_* no .env se usar POST /whatsapp/messages pelo sistema.</p>
                        </div>

                        @if ($messages)
                            <div class="mt-8 border-t border-gray-200 pt-6">
                                <h3 class="font-semibold text-gray-900 mb-4">Histórico de mensagens</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Dir.</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Contato</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mensagem</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            @forelse ($messages as $msg)
                                                <tr>
                                                    <td class="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">{{ $msg->created_at->format('d/m/Y H:i') }}</td>
                                                    <td class="px-3 py-2">
                                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $msg->direction === 'inbound' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                                            {{ $msg->direction === 'inbound' ? 'In' : 'Out' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        @if ($msg->customer)
                                                            <a href="{{ route('customers.show', $msg->customer) }}" class="text-indigo-600 hover:underline">{{ $msg->customer->name }}</a>
                                                        @else
                                                            {{ $msg->phone }}
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-gray-700">{{ Str::limit($msg->message, 60) }}</td>
                                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $msg->status }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="px-3 py-6 text-center text-gray-500">Nenhuma mensagem registrada.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if ($messages->hasPages())
                                    <div class="mt-4">{{ $messages->links() }}</div>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
