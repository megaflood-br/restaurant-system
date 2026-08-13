<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvolutionSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setMany('evolution', [
            'enabled' => true,
            'base_url' => 'http://evolution.test',
            'api_key' => 'test-api-key',
            'instance' => 'restaurant',
            'webhook_secret' => 'whsec',
        ]);

        AppSettings::loadIntoConfig();
    }

    public function test_admin_can_save_evolution_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('settings.evolution.update'), [
            'evolution_enabled' => '1',
            'base_url' => 'https://evo.example.com/',
            'api_key' => 'new-secret-key',
            'instance' => 'loja1',
            'webhook_secret' => 'secret123',
        ]);

        $response->assertRedirect(route('settings.index', ['tab' => 'whatsapp_agent']));

        AppSettings::loadIntoConfig();

        $this->assertTrue((bool) config('evolution.enabled'));
        $this->assertSame('https://evo.example.com', config('evolution.base_url'));
        $this->assertSame('new-secret-key', config('evolution.api_key'));
        $this->assertSame('loja1', config('evolution.instance'));
        $this->assertSame('secret123', config('evolution.webhook_secret'));
    }

    public function test_blank_api_key_keeps_previous(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('settings.evolution.update'), [
            'evolution_enabled' => '1',
            'base_url' => 'http://evolution.test',
            'api_key' => '',
            'instance' => 'restaurant',
            'webhook_secret' => '',
        ])->assertRedirect();

        AppSettings::loadIntoConfig();

        $this->assertSame('test-api-key', config('evolution.api_key'));
        $this->assertSame('whsec', config('evolution.webhook_secret'));
    }

    public function test_settings_page_shows_evolution_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('settings.index', ['tab' => 'whatsapp_agent']))
            ->assertOk()
            ->assertSee('Evolution API / WhatsApp', false)
            ->assertSee('Gerar QR Code', false);
    }

    public function test_connection_status_endpoint(): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/restaurant' => Http::response([
                'instance' => ['state' => 'open'],
            ], 200),
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('settings.evolution.status'))
            ->assertOk()
            ->assertJsonPath('data.state', 'open')
            ->assertJsonPath('data.configured', true);
    }

    public function test_connect_returns_qr_base64(): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/restaurant' => Http::response([
                'instance' => ['state' => 'close'],
            ], 200),
            'evolution.test/instance/connect/restaurant' => Http::response([
                'base64' => 'data:image/png;base64,AAA',
                'pairingCode' => 'ABCD1234',
                'instance' => ['state' => 'connecting'],
            ], 200),
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('settings.evolution.connect'))
            ->assertOk()
            ->assertJsonPath('data.qrcode', 'data:image/png;base64,AAA')
            ->assertJsonPath('data.pairing_code', 'ABCD1234');
    }

    public function test_connect_creates_missing_instance(): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/restaurant' => Http::sequence()
                ->push(['status' => 404, 'error' => 'Not Found'], 404)
                ->push(['instance' => ['state' => 'close']], 200),
            'evolution.test/instance/create' => Http::response([
                'instance' => ['instanceName' => 'restaurant'],
            ], 201),
            'evolution.test/instance/connect/restaurant' => Http::response([
                'qrcode' => ['base64' => 'data:image/png;base64,BBB'],
            ], 200),
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('settings.evolution.connect'))
            ->assertOk()
            ->assertJsonPath('data.qrcode', 'data:image/png;base64,BBB');

        Http::assertSent(fn ($request) => $request->url() === 'http://evolution.test/instance/create');
    }

    public function test_logout_and_webhook(): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/restaurant' => Http::response([
                'instance' => ['state' => 'open'],
            ], 200),
            'evolution.test/instance/logout/restaurant' => Http::response(['status' => 'SUCCESS'], 200),
            'evolution.test/webhook/set/restaurant' => Http::response(['webhook' => ['enabled' => true]], 200),
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('settings.evolution.logout'))
            ->assertOk()
            ->assertJsonPath('data.state', 'close');

        $this->actingAs($admin)
            ->postJson(route('settings.evolution.webhook'))
            ->assertOk()
            ->assertJsonPath('data.ok', true);
    }

    public function test_waiter_cannot_manage_evolution(): void
    {
        $waiter = User::factory()->waiter()->create();

        $this->actingAs($waiter)
            ->get(route('settings.evolution.status'))
            ->assertRedirect();

        $this->actingAs($waiter)
            ->post(route('settings.evolution.connect'))
            ->assertRedirect();
    }
}
