<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nWebhookService
{
    public function isConfigured(): bool
    {
        return filled(config('integration.n8n_webhook_url'));
    }

    public function forwardInbound(array $payload): void
    {
        if (! config('integration.forward_inbound_to_n8n') || ! $this->isConfigured()) {
            return;
        }

        try {
            Http::timeout(10)
                ->acceptJson()
                ->post(config('integration.n8n_webhook_url'), $payload);
        } catch (\Throwable $exception) {
            Log::warning('Failed to forward inbound message to n8n', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
