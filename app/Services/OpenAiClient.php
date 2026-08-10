<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiClient
{
    public function isConfigured(): bool
    {
        return (bool) config('openai.enabled') && filled(config('openai.api_key'));
    }

    /** @param  array<int, array<string, mixed>>  $messages */
    /** @param  array<int, array<string, mixed>>  $tools */
    public function chat(array $messages, array $tools = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OpenAI não configurada.');
        }

        $payload = [
            'model' => config('openai.model', 'gpt-4o-mini'),
            'messages' => $messages,
            'temperature' => 0.4,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::baseUrl(config('openai.base_url'))
            ->withToken((string) config('openai.api_key'))
            ->acceptJson()
            ->timeout((int) config('openai.timeout', 45))
            ->post('/chat/completions', $payload);

        if (! $response->successful()) {
            Log::error('OpenAI chat failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException($response->json('error.message') ?? 'Falha na API OpenAI.');
        }

        return $response->json();
    }
}
