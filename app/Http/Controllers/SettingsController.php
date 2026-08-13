<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WhatsAppMessage;
use App\Services\EvolutionApiService;
use App\Services\OrderPrinterService;
use App\Support\AppSettings;
use App\Support\MenuTheme;
use App\Support\SideOptions;
use App\Support\WeeklyMenuImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        AppSettings::loadIntoConfig();

        $tab = request('tab', 'restaurant');

        return view('settings.index', [
            'general' => AppSettings::general(),
            'restaurant' => AppSettings::restaurant(),
            'printing' => AppSettings::printing(),
            'digitalMenu' => AppSettings::digitalMenu(),
            'integration' => AppSettings::integration(),
            'whatsappAgent' => AppSettings::whatsappAgent(),
            'evolution' => AppSettings::evolution(),
            'tab' => $tab,
            'messages' => $tab === 'integration'
                ? WhatsAppMessage::with('customer', 'order', 'user')->latest()->paginate(15)->withQueryString()
                : null,
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        AppSettings::loadIntoConfig();

        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i'],
            'delivery_origin_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_origin_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'logo_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        $logoPath = config('general.logo_image');

        if ($request->boolean('remove_logo')) {
            $this->deletePublicFile($logoPath);
            $logoPath = null;
        } elseif ($request->hasFile('logo_image')) {
            $this->deletePublicFile($logoPath);
            $logoPath = $request->file('logo_image')->store('general', 'public');
        }

        Setting::setMany('general', [
            'app_name' => $validated['app_name'],
            'app_url' => rtrim($validated['app_url'], '/'),
            'cnpj' => $validated['cnpj'] ?? '',
            'address' => $validated['address'] ?? '',
            'opening_time' => $validated['opening_time'],
            'closing_time' => $validated['closing_time'],
            'delivery_origin_lat' => $validated['delivery_origin_lat'] ?? '',
            'delivery_origin_lng' => $validated['delivery_origin_lng'] ?? '',
            'logo_image' => $logoPath,
        ]);

        AppSettings::loadIntoConfig();

        return redirect()->route('settings.index', ['tab' => 'general'])
            ->with('success', 'Configurações gerais salvas.');
    }

    public function updateRestaurant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'total_comandas' => ['required', 'integer', 'min:1', 'max:9999'],
            'order_delay_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'counter_comanda_number' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        Setting::setMany('restaurant', $validated);
        AppSettings::loadIntoConfig();

        return redirect()->route('settings.index', ['tab' => 'restaurant'])
            ->with('success', 'Configurações do restaurante salvas.');
    }

    public function updateDigitalMenu(Request $request): RedirectResponse
    {
        AppSettings::loadIntoConfig();

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:2'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'more_info' => ['nullable', 'string', 'max:2000'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i'],
            'delivery_minutes' => ['required', 'integer', 'min:5', 'max:180'],
            'delivery_fee' => ['required', 'numeric', 'min:0'],
            'loyalty_title' => ['nullable', 'string', 'max:255'],
            'loyalty_text' => ['nullable', 'string', 'max:2000'],
            'public_domain' => ['nullable', 'string', 'max:255'],
            'theme_color' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'logo_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_cover' => ['sometimes', 'boolean'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        $coverPath = config('digital_menu.cover_image');
        $logoPath = config('digital_menu.logo_image');

        if ($request->boolean('remove_cover')) {
            $this->deletePublicFile($coverPath);
            $coverPath = null;
        } elseif ($request->hasFile('cover_image')) {
            $this->deletePublicFile($coverPath);
            $coverPath = $request->file('cover_image')->store('menu', 'public');
        }

        if ($request->boolean('remove_logo')) {
            $this->deletePublicFile($logoPath);
            $logoPath = null;
        } elseif ($request->hasFile('logo_image')) {
            $this->deletePublicFile($logoPath);
            $logoPath = $request->file('logo_image')->store('menu', 'public');
        }

        Setting::setMany('digital_menu', [
            'display_name' => $validated['display_name'],
            'city' => $validated['city'] ?? '',
            'state' => strtoupper($validated['state'] ?? ''),
            'address_line' => $validated['address_line'] ?? '',
            'more_info' => $validated['more_info'] ?? '',
            'opening_time' => $validated['opening_time'],
            'closing_time' => $validated['closing_time'],
            'force_closed' => $request->boolean('force_closed'),
            'delivery_minutes' => $validated['delivery_minutes'],
            'delivery_fee' => $validated['delivery_fee'],
            'loyalty_enabled' => $request->boolean('loyalty_enabled'),
            'loyalty_title' => $validated['loyalty_title'] ?? '',
            'loyalty_text' => $validated['loyalty_text'] ?? '',
            'public_domain' => self::normalizeDomain($validated['public_domain'] ?? ''),
            'theme_color' => MenuTheme::normalize($validated['theme_color']),
            'cover_image' => $coverPath,
            'logo_image' => $logoPath,
        ]);

        AppSettings::loadIntoConfig();

        return redirect()->route('settings.index', ['tab' => 'digital_menu'])
            ->with('success', 'Cardápio digital atualizado.');
    }

    private function deletePublicFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private static function normalizeDomain(?string $domain): string
    {
        if (! filled($domain)) {
            return '';
        }

        $domain = preg_replace('#^https?://#', '', trim($domain));

        return rtrim((string) $domain, '/');
    }

    public function updatePrinting(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'restaurant_name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'in:browser,network,agent'],
            'auto_print_on_create' => ['boolean'],
            'print_on_preparing' => ['boolean'],
            'network_host' => ['nullable', 'string', 'max:255'],
            'network_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'network_timeout' => ['nullable', 'integer', 'min:1', 'max:60'],
            'paper_width' => ['nullable', 'integer', 'min:24', 'max:48'],
            'kitchen_hide_prices' => ['boolean'],
        ]);

        Setting::setMany('printing', [
            'enabled' => $request->boolean('enabled'),
            'restaurant_name' => $validated['restaurant_name'],
            'driver' => $validated['driver'],
            'auto_print_on_create' => $request->boolean('auto_print_on_create'),
            'print_on_preparing' => $request->boolean('print_on_preparing'),
            'network_host' => $validated['network_host'] ?? '',
            'network_port' => $validated['network_port'] ?? 9100,
            'network_timeout' => $validated['network_timeout'] ?? 5,
            'paper_width' => $validated['paper_width'] ?? 32,
            'kitchen_hide_prices' => $request->boolean('kitchen_hide_prices'),
        ]);

        AppSettings::loadIntoConfig();

        return redirect()->route('settings.index', ['tab' => 'printing'])
            ->with('success', 'Configurações de impressão salvas.');
    }

    public function updateIntegration(Request $request): RedirectResponse
    {
        AppSettings::loadIntoConfig();

        $validated = $request->validate([
            'api_token' => ['nullable', 'string', 'min:16', 'max:255'],
            'n8n_webhook_url' => ['nullable', 'url', 'max:500'],
            'default_country_code' => ['required', 'string', 'max:4'],
        ]);

        $previousToken = config('integration.api_token');

        $apiToken = filled($validated['api_token'] ?? null)
            ? $validated['api_token']
            : ($previousToken ?: Str::random(48));

        Setting::setMany('integration', [
            'api_token' => $apiToken,
            'n8n_webhook_url' => $validated['n8n_webhook_url'] ?? '',
            'forward_inbound_to_n8n' => $request->boolean('forward_inbound_to_n8n'),
            'default_country_code' => $validated['default_country_code'],
        ]);

        AppSettings::loadIntoConfig();

        $showToken = filled($validated['api_token'] ?? null) || ! filled($previousToken);
        $message = 'Configurações de integração salvas.';
        if ($showToken) {
            $message .= ' Token da API: '.$apiToken;
        }

        return redirect()->route('settings.index', ['tab' => 'integration'])
            ->with('success', $message);
    }

    public function regenerateIntegrationToken(): RedirectResponse
    {
        $token = Str::random(48);
        Setting::set('integration', 'api_token', $token);
        AppSettings::loadIntoConfig();

        return redirect()->route('settings.index', ['tab' => 'integration'])
            ->with('success', 'Novo token gerado: '.$token);
    }

    public function testPrinting(OrderPrinterService $printer): RedirectResponse
    {
        AppSettings::loadIntoConfig();

        if (! in_array(config('printing.driver'), ['network', 'agent'], true)) {
            return back()->with('error', 'Para testar, escolha o modo "Rede IP" ou "Agente local", salve e tente de novo. (Ou use o modo Navegador e imprima pelo Windows.)');
        }

        if (config('printing.driver') === 'network' && ! config('printing.network.host')) {
            return back()->with('error', 'Informe o IP da impressora (ex.: 192.168.1.100), porta 9100, salve e teste novamente.');
        }

        try {
            $printer->printTestPage();

            if (config('printing.driver') === 'agent') {
                return back()->with('success', 'Teste enfileirado. Com o agente local rodando no PC do restaurante, o cupom deve sair em instantes.');
            }

            return back()->with('success', 'Teste enviado para a impressora com sucesso. Confira se saiu o cupom "TESTE DE IMPRESSAO".');
        } catch (\Throwable $exception) {
            return back()->with('error', 'Falha no teste: '.$exception->getMessage());
        }
    }

    public function updateWhatsappAgent(Request $request): RedirectResponse
    {
        AppSettings::loadIntoConfig();

        $rules = [
            'restaurant_name' => ['nullable', 'string', 'max:255'],
            'welcome_message' => ['nullable', 'string', 'max:4000'],
            'closed_message' => ['nullable', 'string', 'max:4000'],
            'menu_followup_message' => ['nullable', 'string', 'max:2000'],
            'extras_message' => ['nullable', 'string', 'max:2000'],
            'side_options' => ['nullable', 'string', 'max:2000'],
            'side_message' => ['nullable', 'string', 'max:2000'],
            'address_message' => ['nullable', 'string', 'max:2000'],
            'address_confirm_message' => ['nullable', 'string', 'max:2000'],
            'payment_message' => ['nullable', 'string', 'max:2000'],
            'pix_message' => ['nullable', 'string', 'max:2000'],
            'confirmed_message' => ['nullable', 'string', 'max:2000'],
            'pix_key' => ['nullable', 'string', 'max:255'],
            'estimated_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'human_pause_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'scheduling_enabled' => ['nullable', 'boolean'],
            'schedule_min_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'schedule_max_days' => ['required', 'integer', 'min:0', 'max:7'],
            'schedule_message' => ['nullable', 'string', 'max:2000'],
            'human_handoff_message' => ['nullable', 'string', 'max:2000'],
            'bot_resumed_message' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (WeeklyMenuImages::DAYS as $day) {
            $rules["menu_images.{$day}"] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
            $rules["remove_menu_images.{$day}"] = ['sometimes', 'boolean'];
        }

        $validated = $request->validate($rules);

        $menuImages = WeeklyMenuImages::normalize(config('whatsapp_agent.menu_images'));

        foreach (WeeklyMenuImages::DAYS as $day) {
            if ($request->boolean("remove_menu_images.{$day}")) {
                $this->deletePublicFile($menuImages[$day]);
                $menuImages[$day] = null;
            } elseif ($request->hasFile("menu_images.{$day}")) {
                $this->deletePublicFile($menuImages[$day]);
                $menuImages[$day] = $request->file("menu_images.{$day}")->store('whatsapp', 'public');
            }
        }

        Setting::setMany('whatsapp_agent', [
            'enabled' => $request->boolean('enabled'),
            'use_builtin_bot' => $request->boolean('use_builtin_bot'),
            'use_openai' => $request->boolean('use_openai'),
            'forward_to_n8n' => $request->boolean('forward_to_n8n'),
            'restaurant_name' => $validated['restaurant_name'] ?? '',
            'welcome_message' => $validated['welcome_message'] ?? '',
            'closed_message' => $validated['closed_message'] ?? '',
            'menu_followup_message' => $validated['menu_followup_message'] ?? '',
            'extras_message' => $validated['extras_message'] ?? '',
            'side_options' => json_encode(SideOptions::normalize($validated['side_options'] ?? '')),
            'side_message' => $validated['side_message'] ?? '',
            'address_message' => $validated['address_message'] ?? '',
            'address_confirm_message' => $validated['address_confirm_message'] ?? '',
            'payment_message' => $validated['payment_message'] ?? '',
            'pix_message' => $validated['pix_message'] ?? '',
            'confirmed_message' => $validated['confirmed_message'] ?? '',
            'pix_key' => $validated['pix_key'] ?? '',
            'estimated_minutes' => $validated['estimated_minutes'],
            'human_pause_minutes' => $validated['human_pause_minutes'],
            'scheduling_enabled' => $request->boolean('scheduling_enabled'),
            'schedule_min_minutes' => $validated['schedule_min_minutes'],
            'schedule_max_days' => $validated['schedule_max_days'],
            'schedule_message' => $validated['schedule_message'] ?? '',
            'human_handoff_message' => $validated['human_handoff_message'] ?? '',
            'bot_resumed_message' => $validated['bot_resumed_message'] ?? '',
            'menu_images' => json_encode($menuImages),
            'menu_image' => null,
        ]);

        AppSettings::loadIntoConfig();

        return redirect()->route('settings.index', ['tab' => 'whatsapp_agent'])
            ->with('success', 'Configurações do agente WhatsApp salvas.');
    }

    public function updateEvolution(Request $request): RedirectResponse
    {
        AppSettings::loadIntoConfig();

        $validated = $request->validate([
            'base_url' => ['required', 'string', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'instance' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $baseUrl = rtrim($validated['base_url'], '/');
        $previousKey = (string) config('evolution.api_key', '');
        $previousSecret = (string) config('evolution.webhook_secret', '');

        $apiKey = filled($validated['api_key'] ?? null)
            ? $validated['api_key']
            : $previousKey;

        if ($request->boolean('clear_webhook_secret')) {
            $webhookSecret = '';
        } elseif (filled($validated['webhook_secret'] ?? null)) {
            $webhookSecret = (string) $validated['webhook_secret'];
        } else {
            $webhookSecret = $previousSecret;
        }

        Setting::setMany('evolution', [
            'enabled' => $request->boolean('evolution_enabled'),
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'instance' => $validated['instance'],
            'webhook_secret' => $webhookSecret,
        ]);

        AppSettings::loadIntoConfig();

        return redirect()->route('settings.index', ['tab' => 'whatsapp_agent'])
            ->with('success', 'Configurações da Evolution API salvas.');
    }

    public function evolutionStatus(EvolutionApiService $evolutionApi): JsonResponse
    {
        AppSettings::loadIntoConfig();

        return response()->json(['data' => $evolutionApi->connectionState()]);
    }

    public function evolutionConnect(EvolutionApiService $evolutionApi): JsonResponse
    {
        AppSettings::loadIntoConfig();

        try {
            $result = $evolutionApi->connectWithQr();

            return response()->json(['data' => $result]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function evolutionLogout(EvolutionApiService $evolutionApi): JsonResponse
    {
        AppSettings::loadIntoConfig();

        try {
            $result = $evolutionApi->logout();

            return response()->json(['data' => $result]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function evolutionWebhook(EvolutionApiService $evolutionApi): JsonResponse
    {
        AppSettings::loadIntoConfig();

        try {
            $result = $evolutionApi->setWebhook();

            return response()->json(['data' => $result]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
