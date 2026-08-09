<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WhatsAppMessage;
use App\Services\OrderPrinterService;
use App\Support\AppSettings;
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

    public function updatePrinting(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'restaurant_name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'in:browser,network'],
            'auto_print_on_create' => ['boolean'],
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

        if (! config('printing.network.host')) {
            return back()->with('error', 'Informe o IP da impressora antes de testar.');
        }

        try {
            $printer->printTestPage();

            return back()->with('success', 'Teste enviado para a impressora com sucesso.');
        } catch (\Throwable $exception) {
            return back()->with('error', 'Falha no teste: '.$exception->getMessage());
        }
    }

    public function updateWhatsappAgent(Request $request): RedirectResponse
    {
        AppSettings::loadIntoConfig();

        $validated = $request->validate([
            'restaurant_name' => ['nullable', 'string', 'max:255'],
            'welcome_message' => ['nullable', 'string', 'max:4000'],
            'menu_followup_message' => ['nullable', 'string', 'max:2000'],
            'extras_message' => ['nullable', 'string', 'max:2000'],
            'address_message' => ['nullable', 'string', 'max:2000'],
            'payment_message' => ['nullable', 'string', 'max:2000'],
            'pix_message' => ['nullable', 'string', 'max:2000'],
            'confirmed_message' => ['nullable', 'string', 'max:2000'],
            'pix_key' => ['nullable', 'string', 'max:255'],
            'estimated_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'menu_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_menu_image' => ['sometimes', 'boolean'],
        ]);

        $menuImage = config('whatsapp_agent.menu_image');

        if ($request->boolean('remove_menu_image')) {
            $this->deletePublicFile($menuImage);
            $menuImage = null;
        } elseif ($request->hasFile('menu_image')) {
            $this->deletePublicFile($menuImage);
            $menuImage = $request->file('menu_image')->store('whatsapp', 'public');
        }

        Setting::setMany('whatsapp_agent', [
            'enabled' => $request->boolean('enabled'),
            'use_builtin_bot' => $request->boolean('use_builtin_bot'),
            'forward_to_n8n' => $request->boolean('forward_to_n8n'),
            'restaurant_name' => $validated['restaurant_name'] ?? '',
            'welcome_message' => $validated['welcome_message'] ?? '',
            'menu_followup_message' => $validated['menu_followup_message'] ?? '',
            'extras_message' => $validated['extras_message'] ?? '',
            'address_message' => $validated['address_message'] ?? '',
            'payment_message' => $validated['payment_message'] ?? '',
            'pix_message' => $validated['pix_message'] ?? '',
            'confirmed_message' => $validated['confirmed_message'] ?? '',
            'pix_key' => $validated['pix_key'] ?? '',
            'estimated_minutes' => $validated['estimated_minutes'],
            'menu_image' => $menuImage,
        ]);

        AppSettings::loadIntoConfig();

        return redirect()->route('settings.index', ['tab' => 'whatsapp_agent'])
            ->with('success', 'Configurações do agente WhatsApp salvas.');
    }
}
