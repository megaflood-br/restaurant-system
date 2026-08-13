<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EvolutionApiService
{
    public function isConfigured(): bool
    {
        return (bool) config('evolution.enabled')
            && filled(config('evolution.base_url'))
            && filled(config('evolution.api_key'))
            && filled(config('evolution.instance'));
    }

    public function configurationIssues(): array
    {
        $issues = [];

        if (! config('evolution.enabled')) {
            $issues[] = 'Evolution API desativada nas configurações';
        }

        if (! filled(config('evolution.base_url'))) {
            $issues[] = 'URL da Evolution API não definida';
        }

        if (! filled(config('evolution.api_key'))) {
            $issues[] = 'API Key da Evolution não definida';
        }

        if (! filled(config('evolution.instance'))) {
            $issues[] = 'Nome da instância não definido';
        }

        return $issues;
    }

    public function connectionState(): array
    {
        if (! $this->isConfigured()) {
            return [
                'configured' => false,
                'state' => 'not_configured',
                'message' => 'Evolution API não configurada',
                'issues' => $this->configurationIssues(),
            ];
        }

        try {
            $response = $this->client()->get(
                '/instance/connectionState/'.$this->instanceName()
            );

            if ($response->successful()) {
                $state = data_get($response->json(), 'instance.state')
                    ?? data_get($response->json(), 'state')
                    ?? 'unknown';

                return [
                    'configured' => true,
                    'state' => $state,
                    'message' => $this->translateState((string) $state),
                    'instance' => $this->instanceName(),
                    'raw' => $response->json(),
                ];
            }

            if ($response->status() === 404) {
                return [
                    'configured' => true,
                    'state' => 'missing',
                    'message' => 'Instância não existe na Evolution — use "Gerar QR Code" para criar',
                    'instance' => $this->instanceName(),
                    'raw' => $response->json(),
                ];
            }

            return [
                'configured' => true,
                'state' => 'error',
                'message' => $response->json('message') ?? 'Erro ao consultar instância',
                'instance' => $this->instanceName(),
                'raw' => $response->json(),
            ];
        } catch (\Throwable $exception) {
            Log::error('Evolution API connection check failed', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'configured' => true,
                'state' => 'unreachable',
                'message' => 'Não foi possível conectar à Evolution API',
                'instance' => $this->instanceName(),
            ];
        }
    }

    /**
     * Garante que a instância exista, inicia conexão e devolve QR (base64) quando disponível.
     *
     * @return array{state: string, message: string, qrcode: ?string, pairing_code: ?string, raw: mixed}
     */
    public function connectWithQr(): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Evolution API não está configurada.');
        }

        $this->ensureInstanceExists();

        $response = $this->client()->get('/instance/connect/'.$this->instanceName());

        if (! $response->successful()) {
            throw new RuntimeException($this->responseErrorMessage($response, 'Falha ao obter QR Code'));
        }

        $json = $response->json() ?? [];
        $qrcode = $this->extractQrBase64($json);
        $pairingCode = data_get($json, 'pairingCode')
            ?? data_get($json, 'pairing_code')
            ?? data_get($json, 'qrcode.pairingCode');

        $state = data_get($json, 'instance.state')
            ?? data_get($json, 'instance.status')
            ?? ($qrcode ? 'connecting' : 'unknown');

        if ($state === 'open' || strtolower((string) $state) === 'open') {
            return [
                'state' => 'open',
                'message' => $this->translateState('open'),
                'qrcode' => null,
                'pairing_code' => null,
                'raw' => $json,
            ];
        }

        return [
            'state' => (string) $state,
            'message' => $qrcode
                ? 'Escaneie o QR Code no WhatsApp (Aparelhos conectados).'
                : ($pairingCode
                    ? 'Use o código de pareamento no WhatsApp.'
                    : 'Aguardando QR Code — tente novamente em alguns segundos.'),
            'qrcode' => $qrcode,
            'pairing_code' => $pairingCode ? (string) $pairingCode : null,
            'raw' => $json,
        ];
    }

    public function logout(): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Evolution API não está configurada.');
        }

        $response = $this->client()->delete('/instance/logout/'.$this->instanceName());

        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException($this->responseErrorMessage($response, 'Falha ao desconectar WhatsApp'));
        }

        return [
            'state' => 'close',
            'message' => 'WhatsApp desconectado. Gere um novo QR Code para vincular novamente.',
            'raw' => $response->json(),
        ];
    }

    /**
     * Configura o webhook da instância apontando para este sistema.
     *
     * @return array{ok: bool, message: string, raw: mixed}
     */
    public function setWebhook(?string $url = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Evolution API não está configurada.');
        }

        $this->ensureInstanceExists();

        $webhookUrl = $url ?: url('/api/webhooks/evolution');
        $secret = (string) config('evolution.webhook_secret', '');

        $payload = [
            'enabled' => true,
            'url' => $webhookUrl,
            'webhookByEvents' => false,
            'webhookBase64' => false,
            'events' => [
                'MESSAGES_UPSERT',
                'CONNECTION_UPDATE',
                'QRCODE_UPDATED',
            ],
        ];

        if (filled($secret)) {
            $payload['headers'] = [
                'x-webhook-secret' => $secret,
            ];
        }

        $response = $this->client()->post('/webhook/set/'.$this->instanceName(), $payload);

        // Algumas versões esperam o objeto aninhado em "webhook".
        if (! $response->successful()) {
            $response = $this->client()->post('/webhook/set/'.$this->instanceName(), [
                'webhook' => $payload,
            ]);
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->responseErrorMessage($response, 'Falha ao configurar webhook'));
        }

        return [
            'ok' => true,
            'message' => 'Webhook configurado: '.$webhookUrl,
            'raw' => $response->json(),
        ];
    }

    public function ensureInstanceExists(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Evolution API não está configurada.');
        }

        $state = $this->connectionState();

        if (($state['state'] ?? null) !== 'missing') {
            return;
        }

        $payload = [
            'instanceName' => $this->instanceName(),
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ];

        $response = $this->client()->post('/instance/create', $payload);

        if ($response->successful()) {
            return;
        }

        // Já existe / conflito — seguir com connect.
        if (in_array($response->status(), [403, 409], true)) {
            return;
        }

        $message = strtolower((string) ($response->json('message') ?? ''));
        if (str_contains($message, 'already') || str_contains($message, 'exist')) {
            return;
        }

        throw new RuntimeException($this->responseErrorMessage($response, 'Falha ao criar instância na Evolution'));
    }

    public function sendText(string $number, string $text): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Evolution API não está configurada.');
        }

        $response = $this->client()->post(
            '/message/sendText/'.$this->instanceName(),
            [
                'number' => $number,
                'text' => $text,
            ]
        );

        if (! $response->successful()) {
            throw new RuntimeException($this->responseErrorMessage($response, 'Falha ao enviar mensagem via WhatsApp'));
        }

        return $response->json() ?? [];
    }

    public function sendMedia(string $number, string $mediaUrl, string $mediatype = 'image', ?string $caption = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Evolution API não está configurada.');
        }

        $payload = [
            'number' => $number,
            'mediatype' => $mediatype,
            'media' => $mediaUrl,
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        $response = $this->client()->post(
            '/message/sendMedia/'.$this->instanceName(),
            $payload
        );

        if (! $response->successful()) {
            throw new RuntimeException($this->responseErrorMessage($response, 'Falha ao enviar mídia via WhatsApp'));
        }

        return $response->json() ?? [];
    }

    public function buildOrderStatusMessage(string $status, array $replacements): string
    {
        $template = config("evolution.status_messages.{$status}")
            ?? 'Atualização do pedido *{order}*: status alterado para *{status}*.';

        $replacements['status'] = $status;

        return str_replace(
            array_map(fn ($key) => '{'.$key.'}', array_keys($replacements)),
            array_values($replacements),
            $template
        );
    }

    private function instanceName(): string
    {
        return (string) config('evolution.instance');
    }

    private function client()
    {
        $headers = [
            'apikey' => config('evolution.api_key'),
            'Content-Type' => 'application/json',
        ];

        if (str_contains((string) config('evolution.base_url'), 'ngrok')) {
            $headers['ngrok-skip-browser-warning'] = 'true';
        }

        return Http::baseUrl((string) config('evolution.base_url'))
            ->withHeaders($headers)
            ->timeout(30)
            ->acceptJson();
    }

    private function extractQrBase64(array $json): ?string
    {
        $candidates = [
            data_get($json, 'base64'),
            data_get($json, 'qrcode.base64'),
            data_get($json, 'qr.base64'),
            data_get($json, 'qrcode'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            // Alguns payloads devolvem o objeto qrcode como string JSON — ignorar.
            if (str_starts_with($candidate, '{')) {
                continue;
            }

            if (str_starts_with($candidate, 'data:image')) {
                return $candidate;
            }

            // Base64 puro da imagem PNG.
            if (preg_match('/^[A-Za-z0-9+\/=]+$/', $candidate) && strlen($candidate) > 100) {
                return 'data:image/png;base64,'.$candidate;
            }
        }

        return null;
    }

    private function translateState(string $state): string
    {
        return match (strtolower($state)) {
            'open' => 'Conectado ao WhatsApp',
            'close', 'closed' => 'Desconectado — escaneie o QR Code',
            'connecting' => 'Conectando… escaneie o QR Code',
            'missing' => 'Instância ainda não criada',
            'not_configured' => 'Não configurado',
            'unreachable' => 'Evolution API inacessível',
            default => 'Status: '.$state,
        };
    }

    private function responseErrorMessage(Response $response, string $fallback): string
    {
        $message = $response->json('message')
            ?? $response->json('error')
            ?? $fallback;

        return is_array($message) ? json_encode($message) : (string) $message;
    }
}
