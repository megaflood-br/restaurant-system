<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class AppSettings
{
    public static function loadIntoConfig(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        self::ensureDefaults();

        config([
            'app.name' => Setting::get('general', 'app_name', config('app.name')),
            'app.url' => rtrim((string) Setting::get('general', 'app_url', config('app.url')), '/'),

            'general.cnpj' => Setting::get('general', 'cnpj', config('general.cnpj')),
            'general.address' => Setting::get('general', 'address', config('general.address')),
            'general.opening_time' => Setting::get('general', 'opening_time', config('general.opening_time')),
            'general.closing_time' => Setting::get('general', 'closing_time', config('general.closing_time')),
            'general.open_days' => OpeningHours::normalizeOpenDays(
                Setting::get('general', 'open_days', config('general.open_days'))
            ),
            'general.delivery_origin_lat' => Setting::get('general', 'delivery_origin_lat', config('general.delivery_origin_lat')),
            'general.delivery_origin_lng' => Setting::get('general', 'delivery_origin_lng', config('general.delivery_origin_lng')),
            'general.logo_image' => Setting::get('general', 'logo_image', config('general.logo_image')),

            'restaurant.total_comandas' => (int) Setting::get('restaurant', 'total_comandas', config('restaurant.total_comandas')),
            'restaurant.order_delay_minutes' => (int) Setting::get('restaurant', 'order_delay_minutes', config('restaurant.order_delay_minutes')),
            'restaurant.counter_comanda_number' => (int) Setting::get('restaurant', 'counter_comanda_number', config('restaurant.counter_comanda_number', 950)),

            'printing.enabled' => Setting::get('printing', 'enabled', config('printing.enabled')),
            'printing.restaurant_name' => Setting::get('printing', 'restaurant_name', config('printing.restaurant_name')),
            'printing.driver' => Setting::get('printing', 'driver', config('printing.driver')),
            'printing.auto_print_on_create' => Setting::get('printing', 'auto_print_on_create', config('printing.auto_print_on_create')),
            'printing.print_on_preparing' => Setting::get('printing', 'print_on_preparing', config('printing.print_on_preparing', true)),
            'printing.network.host' => Setting::get('printing', 'network_host', config('printing.network.host')),
            'printing.network.port' => (int) Setting::get('printing', 'network_port', config('printing.network.port')),
            'printing.network.timeout' => (int) Setting::get('printing', 'network_timeout', config('printing.network.timeout')),
            'printing.paper_width' => (int) Setting::get('printing', 'paper_width', config('printing.paper_width')),
            'printing.kitchen_hide_prices' => Setting::get('printing', 'kitchen_hide_prices', config('printing.kitchen_hide_prices')),

            'integration.api_token' => Setting::get('integration', 'api_token', config('integration.api_token')),
            'integration.n8n_webhook_url' => Setting::get('integration', 'n8n_webhook_url', config('integration.n8n_webhook_url')),
            'integration.forward_inbound_to_n8n' => Setting::get('integration', 'forward_inbound_to_n8n', config('integration.forward_inbound_to_n8n')),
            'integration.default_country_code' => (string) Setting::get('integration', 'default_country_code', config('integration.default_country_code')),

            'digital_menu.display_name' => Setting::get('digital_menu', 'display_name', config('digital_menu.display_name')),
            'digital_menu.city' => Setting::get('digital_menu', 'city', config('digital_menu.city')),
            'digital_menu.state' => Setting::get('digital_menu', 'state', config('digital_menu.state')),
            'digital_menu.address_line' => Setting::get('digital_menu', 'address_line', config('digital_menu.address_line')),
            'digital_menu.more_info' => Setting::get('digital_menu', 'more_info', config('digital_menu.more_info')),
            'digital_menu.opening_time' => Setting::get('digital_menu', 'opening_time', config('digital_menu.opening_time')),
            'digital_menu.closing_time' => Setting::get('digital_menu', 'closing_time', config('digital_menu.closing_time')),
            'digital_menu.force_closed' => Setting::get('digital_menu', 'force_closed', config('digital_menu.force_closed')),
            'digital_menu.delivery_minutes' => (int) Setting::get('digital_menu', 'delivery_minutes', config('digital_menu.delivery_minutes')),
            'digital_menu.delivery_fee' => Setting::get('digital_menu', 'delivery_fee', config('digital_menu.delivery_fee')),
            'digital_menu.loyalty_enabled' => Setting::get('digital_menu', 'loyalty_enabled', config('digital_menu.loyalty_enabled')),
            'digital_menu.loyalty_title' => Setting::get('digital_menu', 'loyalty_title', config('digital_menu.loyalty_title')),
            'digital_menu.loyalty_text' => Setting::get('digital_menu', 'loyalty_text', config('digital_menu.loyalty_text')),
            'digital_menu.cover_image' => Setting::get('digital_menu', 'cover_image', config('digital_menu.cover_image')),
            'digital_menu.logo_image' => Setting::get('digital_menu', 'logo_image', config('digital_menu.logo_image')),
            'digital_menu.public_domain' => Setting::get('digital_menu', 'public_domain', config('digital_menu.public_domain')),
            'digital_menu.theme_color' => Setting::get('digital_menu', 'theme_color', config('digital_menu.theme_color')),

            'whatsapp_agent.enabled' => Setting::get('whatsapp_agent', 'enabled', config('whatsapp_agent.enabled')),
            'whatsapp_agent.use_builtin_bot' => Setting::get('whatsapp_agent', 'use_builtin_bot', config('whatsapp_agent.use_builtin_bot')),
            'whatsapp_agent.use_openai' => Setting::get('whatsapp_agent', 'use_openai', config('whatsapp_agent.use_openai')),
            'whatsapp_agent.forward_to_n8n' => Setting::get('whatsapp_agent', 'forward_to_n8n', config('whatsapp_agent.forward_to_n8n', config('integration.forward_inbound_to_n8n'))),
            'whatsapp_agent.restaurant_name' => Setting::get('whatsapp_agent', 'restaurant_name', config('whatsapp_agent.restaurant_name')),
            'whatsapp_agent.welcome_message' => Setting::get('whatsapp_agent', 'welcome_message', config('whatsapp_agent.welcome_message')),
            'whatsapp_agent.closed_message' => Setting::get('whatsapp_agent', 'closed_message', config('whatsapp_agent.closed_message')),
            'whatsapp_agent.menu_followup_message' => Setting::get('whatsapp_agent', 'menu_followup_message', config('whatsapp_agent.menu_followup_message')),
            'whatsapp_agent.extras_message' => Setting::get('whatsapp_agent', 'extras_message', config('whatsapp_agent.extras_message')),
            'whatsapp_agent.side_options' => SideOptions::normalize(Setting::get('whatsapp_agent', 'side_options', config('whatsapp_agent.side_options'))),
            'whatsapp_agent.side_message' => Setting::get('whatsapp_agent', 'side_message', config('whatsapp_agent.side_message')),
            'whatsapp_agent.address_message' => Setting::get('whatsapp_agent', 'address_message', config('whatsapp_agent.address_message')),
            'whatsapp_agent.address_confirm_message' => Setting::get('whatsapp_agent', 'address_confirm_message', config('whatsapp_agent.address_confirm_message')),
            'whatsapp_agent.payment_message' => Setting::get('whatsapp_agent', 'payment_message', config('whatsapp_agent.payment_message')),
            'whatsapp_agent.pix_message' => Setting::get('whatsapp_agent', 'pix_message', config('whatsapp_agent.pix_message')),
            'whatsapp_agent.confirmed_message' => Setting::get('whatsapp_agent', 'confirmed_message', config('whatsapp_agent.confirmed_message')),
            'whatsapp_agent.pix_key' => Setting::get('whatsapp_agent', 'pix_key', config('whatsapp_agent.pix_key')),
            'whatsapp_agent.estimated_minutes' => (int) Setting::get('whatsapp_agent', 'estimated_minutes', config('whatsapp_agent.estimated_minutes')),
            'whatsapp_agent.menu_image' => Setting::get('whatsapp_agent', 'menu_image', config('whatsapp_agent.menu_image')),
            'whatsapp_agent.menu_images' => self::weeklyMenuImagesFromSettings(),
            'whatsapp_agent.order_added_message' => Setting::get('whatsapp_agent', 'order_added_message', config('whatsapp_agent.order_added_message')),
            'whatsapp_agent.delivery_quote_message' => Setting::get('whatsapp_agent', 'delivery_quote_message', config('whatsapp_agent.delivery_quote_message')),
            'whatsapp_agent.cancel_message' => Setting::get('whatsapp_agent', 'cancel_message', config('whatsapp_agent.cancel_message')),
            'whatsapp_agent.status_not_found_message' => Setting::get('whatsapp_agent', 'status_not_found_message', config('whatsapp_agent.status_not_found_message')),
            'whatsapp_agent.human_handoff_message' => Setting::get('whatsapp_agent', 'human_handoff_message', config('whatsapp_agent.human_handoff_message')),
            'whatsapp_agent.bot_resumed_message' => Setting::get('whatsapp_agent', 'bot_resumed_message', config('whatsapp_agent.bot_resumed_message')),
            'whatsapp_agent.human_pause_minutes' => (int) Setting::get('whatsapp_agent', 'human_pause_minutes', config('whatsapp_agent.human_pause_minutes', 60)),
            'whatsapp_agent.scheduling_enabled' => Setting::get('whatsapp_agent', 'scheduling_enabled', config('whatsapp_agent.scheduling_enabled', true)),
            'whatsapp_agent.schedule_min_minutes' => (int) Setting::get('whatsapp_agent', 'schedule_min_minutes', config('whatsapp_agent.schedule_min_minutes', 30)),
            'whatsapp_agent.schedule_max_days' => (int) Setting::get('whatsapp_agent', 'schedule_max_days', config('whatsapp_agent.schedule_max_days', 1)),
            'whatsapp_agent.schedule_message' => Setting::get('whatsapp_agent', 'schedule_message', config('whatsapp_agent.schedule_message')),
            'whatsapp_agent.comanda_feedback_enabled' => Setting::get('whatsapp_agent', 'comanda_feedback_enabled', config('whatsapp_agent.comanda_feedback_enabled', false)),
            'whatsapp_agent.comanda_feedback_delay_minutes' => (int) Setting::get('whatsapp_agent', 'comanda_feedback_delay_minutes', config('whatsapp_agent.comanda_feedback_delay_minutes', 30)),
            'whatsapp_agent.comanda_feedback_message' => Setting::get('whatsapp_agent', 'comanda_feedback_message', config('whatsapp_agent.comanda_feedback_message')),

            'evolution.enabled' => Setting::get('evolution', 'enabled', config('evolution.enabled')),
            'evolution.base_url' => rtrim((string) Setting::get('evolution', 'base_url', config('evolution.base_url')), '/'),
            'evolution.api_key' => Setting::get('evolution', 'api_key', config('evolution.api_key')),
            'evolution.instance' => Setting::get('evolution', 'instance', config('evolution.instance')),
            'evolution.webhook_secret' => Setting::get('evolution', 'webhook_secret', config('evolution.webhook_secret')),
        ]);
    }

    public static function ensureDefaults(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $defaults = self::defaultValues();

        foreach ($defaults as $group => $values) {
            foreach ($values as $key => $value) {
                $exists = Setting::query()
                    ->where('group', $group)
                    ->where('key', $key)
                    ->exists();

                if (! $exists) {
                    Setting::set($group, $key, $value);
                }
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    public static function defaultValues(): array
    {
        return [
            'general' => [
                'app_name' => config('app.name', 'Restaurant System'),
                'app_url' => config('app.url', 'http://localhost'),
                'cnpj' => config('general.cnpj', ''),
                'address' => config('general.address', ''),
                'opening_time' => config('general.opening_time', '09:00'),
                'closing_time' => config('general.closing_time', '22:00'),
                'open_days' => json_encode(OpeningHours::normalizeOpenDays(config('general.open_days'))),
                'delivery_origin_lat' => config('general.delivery_origin_lat', ''),
                'delivery_origin_lng' => config('general.delivery_origin_lng', ''),
                'logo_image' => config('general.logo_image'),
            ],
            'restaurant' => [
                'total_comandas' => config('restaurant.total_comandas', 100),
                'order_delay_minutes' => config('restaurant.order_delay_minutes', 25),
                'counter_comanda_number' => config('restaurant.counter_comanda_number', 950),
            ],
            'printing' => [
                'enabled' => config('printing.enabled', true),
                'restaurant_name' => config('printing.restaurant_name', config('app.name', 'Restaurant System')),
                'driver' => config('printing.driver', 'browser'),
                'auto_print_on_create' => config('printing.auto_print_on_create', false),
                'print_on_preparing' => config('printing.print_on_preparing', true),
                'network_host' => config('printing.network.host', ''),
                'network_port' => config('printing.network.port', 9100),
                'network_timeout' => config('printing.network.timeout', 5),
                'paper_width' => config('printing.paper_width', 32),
                'kitchen_hide_prices' => config('printing.kitchen_hide_prices', false),
            ],
            'integration' => [
                'api_token' => config('integration.api_token', ''),
                'n8n_webhook_url' => config('integration.n8n_webhook_url', ''),
                'forward_inbound_to_n8n' => config('integration.forward_inbound_to_n8n', true),
                'default_country_code' => config('integration.default_country_code', '55'),
            ],
            'digital_menu' => [
                'display_name' => config('printing.restaurant_name', config('app.name', 'Restaurant System')),
                'city' => '',
                'state' => '',
                'address_line' => '',
                'more_info' => '',
                'opening_time' => '09:00',
                'closing_time' => '22:00',
                'force_closed' => false,
                'delivery_minutes' => 30,
                'delivery_fee' => 5.00,
                'loyalty_enabled' => false,
                'loyalty_title' => 'Troque pontos por recompensas',
                'loyalty_text' => 'A cada R$ 1,00 em compras você ganha 1 ponto que pode ser trocado por recompensas.',
                'cover_image' => null,
                'logo_image' => null,
                'public_domain' => config('digital_menu.public_domain', ''),
                'theme_color' => config('digital_menu.theme_color', '#f97316'),
            ],
            'whatsapp_agent' => [
                'enabled' => config('whatsapp_agent.enabled', false),
                'use_builtin_bot' => config('whatsapp_agent.use_builtin_bot', true),
                'use_openai' => config('whatsapp_agent.use_openai', false),
                'forward_to_n8n' => config('whatsapp_agent.forward_to_n8n', config('integration.forward_inbound_to_n8n', true)),
                'restaurant_name' => config('whatsapp_agent.restaurant_name', ''),
                'welcome_message' => config('whatsapp_agent.welcome_message', ''),
                'closed_message' => config('whatsapp_agent.closed_message', ''),
                'menu_followup_message' => config('whatsapp_agent.menu_followup_message', ''),
                'extras_message' => config('whatsapp_agent.extras_message', ''),
                'side_options' => json_encode(SideOptions::normalize(config('whatsapp_agent.side_options'))),
                'side_message' => config('whatsapp_agent.side_message', ''),
                'address_message' => config('whatsapp_agent.address_message', ''),
                'address_confirm_message' => config('whatsapp_agent.address_confirm_message', ''),
                'payment_message' => config('whatsapp_agent.payment_message', ''),
                'pix_message' => config('whatsapp_agent.pix_message', ''),
                'confirmed_message' => config('whatsapp_agent.confirmed_message', ''),
                'pix_key' => config('whatsapp_agent.pix_key', ''),
                'estimated_minutes' => config('whatsapp_agent.estimated_minutes', 45),
                'menu_image' => config('whatsapp_agent.menu_image'),
                'menu_images' => json_encode(WeeklyMenuImages::empty()),
                'order_added_message' => config('whatsapp_agent.order_added_message', ''),
                'delivery_quote_message' => config('whatsapp_agent.delivery_quote_message', ''),
                'cancel_message' => config('whatsapp_agent.cancel_message', ''),
                'status_not_found_message' => config('whatsapp_agent.status_not_found_message', ''),
                'human_handoff_message' => config('whatsapp_agent.human_handoff_message', ''),
                'bot_resumed_message' => config('whatsapp_agent.bot_resumed_message', ''),
                'human_pause_minutes' => config('whatsapp_agent.human_pause_minutes', 60),
                'scheduling_enabled' => config('whatsapp_agent.scheduling_enabled', true),
                'schedule_min_minutes' => config('whatsapp_agent.schedule_min_minutes', 30),
                'schedule_max_days' => config('whatsapp_agent.schedule_max_days', 1),
                'schedule_message' => config('whatsapp_agent.schedule_message', ''),
                'comanda_feedback_enabled' => config('whatsapp_agent.comanda_feedback_enabled', false),
                'comanda_feedback_delay_minutes' => config('whatsapp_agent.comanda_feedback_delay_minutes', 30),
                'comanda_feedback_message' => config('whatsapp_agent.comanda_feedback_message', ''),
            ],
            'evolution' => [
                'enabled' => config('evolution.enabled', false),
                'base_url' => config('evolution.base_url', 'http://localhost:8080'),
                'api_key' => config('evolution.api_key', ''),
                'instance' => config('evolution.instance', 'restaurant'),
                'webhook_secret' => config('evolution.webhook_secret', ''),
            ],
        ];
    }

    public static function integration(): array
    {
        $token = config('integration.api_token');

        return [
            'api_token_set' => filled($token),
            'api_token_preview' => filled($token) ? substr($token, 0, 8).'…' : null,
            'n8n_webhook_url' => config('integration.n8n_webhook_url'),
            'forward_inbound_to_n8n' => config('integration.forward_inbound_to_n8n'),
            'default_country_code' => config('integration.default_country_code'),
            'api_base_url' => url('/api/v1'),
            'evolution_webhook_url' => url('/api/webhooks/evolution'),
        ];
    }

    public static function general(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'cnpj' => config('general.cnpj'),
            'address' => config('general.address'),
            'opening_time' => config('general.opening_time'),
            'closing_time' => config('general.closing_time'),
            'open_days' => OpeningHours::normalizeOpenDays(config('general.open_days')),
            'delivery_origin_lat' => config('general.delivery_origin_lat'),
            'delivery_origin_lng' => config('general.delivery_origin_lng'),
            'logo_image' => config('general.logo_image'),
            'logo_url' => DigitalMenu::assetUrl(config('general.logo_image')),
            'weekday_labels' => WeeklyMenuImages::labels(),
        ];
    }

    public static function restaurant(): array
    {
        return [
            'total_comandas' => config('restaurant.total_comandas'),
            'order_delay_minutes' => config('restaurant.order_delay_minutes'),
            'counter_comanda_number' => config('restaurant.counter_comanda_number'),
        ];
    }

    public static function printing(): array
    {
        return [
            'enabled' => config('printing.enabled'),
            'restaurant_name' => config('printing.restaurant_name'),
            'driver' => config('printing.driver'),
            'auto_print_on_create' => config('printing.auto_print_on_create'),
            'print_on_preparing' => config('printing.print_on_preparing'),
            'network_host' => config('printing.network.host'),
            'network_port' => config('printing.network.port'),
            'network_timeout' => config('printing.network.timeout'),
            'paper_width' => config('printing.paper_width'),
            'kitchen_hide_prices' => config('printing.kitchen_hide_prices'),
        ];
    }

    public static function digitalMenu(): array
    {
        return [
            'display_name' => config('digital_menu.display_name'),
            'city' => config('digital_menu.city'),
            'state' => config('digital_menu.state'),
            'address_line' => config('digital_menu.address_line'),
            'more_info' => config('digital_menu.more_info'),
            'opening_time' => config('digital_menu.opening_time'),
            'closing_time' => config('digital_menu.closing_time'),
            'force_closed' => config('digital_menu.force_closed'),
            'delivery_minutes' => config('digital_menu.delivery_minutes'),
            'delivery_fee' => config('digital_menu.delivery_fee'),
            'loyalty_enabled' => config('digital_menu.loyalty_enabled'),
            'loyalty_title' => config('digital_menu.loyalty_title'),
            'loyalty_text' => config('digital_menu.loyalty_text'),
            'cover_image' => config('digital_menu.cover_image'),
            'logo_image' => config('digital_menu.logo_image'),
            'cover_url' => DigitalMenu::assetUrl(config('digital_menu.cover_image')),
            'logo_url' => DigitalMenu::assetUrl(config('digital_menu.logo_image')),
            'public_domain' => config('digital_menu.public_domain'),
            'public_url' => DigitalMenu::publicUrl('/'),
            'theme_color' => config('digital_menu.theme_color'),
            'theme_label' => strtoupper(config('digital_menu.theme_color')),
        ];
    }

    public static function whatsappAgent(): array
    {
        return [
            'enabled' => config('whatsapp_agent.enabled'),
            'use_builtin_bot' => config('whatsapp_agent.use_builtin_bot'),
            'use_openai' => config('whatsapp_agent.use_openai'),
            'openai_configured' => filled(config('openai.api_key')) && config('openai.enabled'),
            'forward_to_n8n' => config('whatsapp_agent.forward_to_n8n'),
            'restaurant_name' => config('whatsapp_agent.restaurant_name'),
            'welcome_message' => config('whatsapp_agent.welcome_message'),
            'closed_message' => config('whatsapp_agent.closed_message'),
            'menu_followup_message' => config('whatsapp_agent.menu_followup_message'),
            'extras_message' => config('whatsapp_agent.extras_message'),
            'side_options' => SideOptions::normalize(config('whatsapp_agent.side_options')),
            'side_options_text' => implode("\n", SideOptions::normalize(config('whatsapp_agent.side_options'))),
            'side_message' => config('whatsapp_agent.side_message'),
            'address_message' => config('whatsapp_agent.address_message'),
            'address_confirm_message' => config('whatsapp_agent.address_confirm_message'),
            'payment_message' => config('whatsapp_agent.payment_message'),
            'pix_message' => config('whatsapp_agent.pix_message'),
            'confirmed_message' => config('whatsapp_agent.confirmed_message'),
            'pix_key' => config('whatsapp_agent.pix_key'),
            'estimated_minutes' => config('whatsapp_agent.estimated_minutes'),
            'menu_images' => WeeklyMenuImages::normalize(config('whatsapp_agent.menu_images')),
            'menu_image_urls' => WeeklyMenuImages::urls(),
            'menu_image_url' => WeeklyMenuImages::urlForToday(),
            'today_menu_day' => WeeklyMenuImages::todayKey(),
            'today_menu_label' => WeeklyMenuImages::labels()[WeeklyMenuImages::todayKey()],
            'order_added_message' => config('whatsapp_agent.order_added_message'),
            'delivery_quote_message' => config('whatsapp_agent.delivery_quote_message'),
            'cancel_message' => config('whatsapp_agent.cancel_message'),
            'status_not_found_message' => config('whatsapp_agent.status_not_found_message'),
            'human_handoff_message' => config('whatsapp_agent.human_handoff_message'),
            'bot_resumed_message' => config('whatsapp_agent.bot_resumed_message'),
            'human_pause_minutes' => config('whatsapp_agent.human_pause_minutes'),
            'scheduling_enabled' => config('whatsapp_agent.scheduling_enabled'),
            'schedule_min_minutes' => config('whatsapp_agent.schedule_min_minutes'),
            'schedule_max_days' => config('whatsapp_agent.schedule_max_days'),
            'schedule_message' => config('whatsapp_agent.schedule_message'),
            'comanda_feedback_enabled' => (bool) config('whatsapp_agent.comanda_feedback_enabled'),
            'comanda_feedback_delay_minutes' => (int) config('whatsapp_agent.comanda_feedback_delay_minutes', 30),
            'comanda_feedback_message' => config('whatsapp_agent.comanda_feedback_message'),
        ];
    }

    public static function evolution(): array
    {
        $apiKey = (string) config('evolution.api_key', '');
        $webhookSecret = (string) config('evolution.webhook_secret', '');

        return [
            'enabled' => (bool) config('evolution.enabled'),
            'base_url' => (string) config('evolution.base_url', ''),
            'api_key_set' => filled($apiKey),
            'api_key_preview' => filled($apiKey) ? substr($apiKey, 0, 6).'…' : null,
            'instance' => (string) config('evolution.instance', ''),
            'webhook_secret_set' => filled($webhookSecret),
            'webhook_url' => url('/api/webhooks/evolution'),
            'webhook_url_by_events' => url('/api/webhooks/evolution/messages-upsert'),
        ];
    }

    /** @return array<string, string|null> */
    private static function weeklyMenuImagesFromSettings(): array
    {
        $stored = Setting::get('whatsapp_agent', 'menu_images');

        if ($stored !== null) {
            return WeeklyMenuImages::normalize($stored);
        }

        $legacy = Setting::get('whatsapp_agent', 'menu_image', config('whatsapp_agent.menu_image'));

        if (filled($legacy)) {
            return WeeklyMenuImages::fromLegacy((string) $legacy);
        }

        return WeeklyMenuImages::normalize(config('whatsapp_agent.menu_images'));
    }
}
