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
            $issues[] = 'EVOLUTION_API_ENABLED está false no .env';
        }

        if (! filled(config('evolution.base_url'))) {
            $issues[] = 'EVOLUTION_API_URL não definida';
        }

        if (! filled(config('evolution.api_key'))) {
            $issues[] = 'EVOLUTION_API_KEY não definida';
        }

        if (! filled(config('evolution.instance'))) {
            $issues[] = 'EVOLUTION_API_INSTANCE não definida';
        }

        return $issues;
    }

    public function connectionState(): array
    {
        if (! $this->isConfigured()) {
            return [
                'configured' => false,
                'state' => 'not_configured',
                'message' => 'Evolution API não configurada no .env',
                'issues' => $this->configurationIssues(),
            ];
        }

        try {
            $response = $this->client()->get(
                '/instance/connectionState/'.config('evolution.instance')
            );

            if ($response->successful()) {
                $state = data_get($response->json(), 'instance.state')
                    ?? data_get($response->json(), 'state')
                    ?? 'unknown';

                return [
                    'configured' => true,
                    'state' => $state,
                    'message' => $this->translateState($state),
                    'raw' => $response->json(),
                ];
            }

            return [
                'configured' => true,
                'state' => 'error',
                'message' => $response->json('message') ?? 'Erro ao consultar instância',
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
            ];
        }
    }

    public function sendText(string $number, string $text): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Evolution API não está configurada.');
        }

        $response = $this->client()->post(
            '/message/sendText/'.config('evolution.instance'),
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
            '/message/sendMedia/'.config('evolution.instance'),
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

    private function client()
    {
        $headers = [
            'apikey' => config('evolution.api_key'),
            'Content-Type' => 'application/json',
        ];

        if (str_contains((string) config('evolution.base_url'), 'ngrok')) {
            $headers['ngrok-skip-browser-warning'] = 'true';
        }

        return Http::baseUrl(config('evolution.base_url'))
            ->withHeaders($headers)
            ->timeout(30)
            ->acceptJson();
    }

    private function translateState(string $state): string
    {
        return match ($state) {
            'open' => 'Conectado ao WhatsApp',
            'close' => 'Desconectado — escaneie o QR Code na Evolution API',
            'connecting' => 'Conectando...',
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
