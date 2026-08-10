<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EvolutionWebhookController extends Controller
{
    public function __invoke(Request $request, WhatsAppService $whatsAppService): JsonResponse
    {
        $secret = config('evolution.webhook_secret');

        if ($secret && $request->header('x-webhook-secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $event = $this->resolveEvent($request);
        $payload = $request->all();

        if ($event !== 'messages.upsert') {
            return response()->json(['message' => 'Event ignored', 'event' => $event]);
        }

        Log::info('Evolution webhook: message received', [
            'event' => $event,
            'instance' => $request->input('instance'),
            'path' => $request->path(),
        ]);

        foreach ($this->extractMessages($payload) as $message) {
            if (data_get($message, 'key.fromMe') === true) {
                continue;
            }

            $text = $this->extractText($message);

            if ($text === null || trim($text) === '') {
                if (! $this->hasMedia($message)) {
                    continue;
                }

                $text = '[imagem]';
            }

            $remoteJid = data_get($message, 'key.remoteJidAlt')
                ?? data_get($message, 'key.remoteJid');

            if (! $remoteJid || str_contains($remoteJid, '@g.us') || str_contains($remoteJid, '@broadcast')) {
                continue;
            }

            $phone = explode('@', $remoteJid)[0];
            $pushName = data_get($message, 'pushName');

            try {
                $whatsAppService->handleInboundMessage($phone, trim($text), $message, $pushName);
            } catch (\Throwable $exception) {
                Log::error('Evolution webhook message handling failed', [
                    'phone' => $phone,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json(['message' => 'Webhook processed']);
    }

    /** @return array<int, array<string, mixed>> */
    private function extractMessages(array $payload): array
    {
        $data = $payload['data'] ?? [];

        if (isset($data['messages']) && is_array($data['messages'])) {
            return $data['messages'];
        }

        if (isset($data['key']) && is_array($data['key'])) {
            return [$data];
        }

        if (is_array($data) && array_is_list($data)) {
            return $data;
        }

        return [];
    }

    private function extractText(array $message): ?string
    {
        return data_get($message, 'message.conversation')
            ?? data_get($message, 'message.extendedTextMessage.text')
            ?? data_get($message, 'message.imageMessage.caption')
            ?? data_get($message, 'message.videoMessage.caption');
    }

    private function hasMedia(array $message): bool
    {
        return data_get($message, 'message.imageMessage') !== null
            || data_get($message, 'message.documentMessage') !== null;
    }

    private function resolveEvent(Request $request): string
    {
        $routeEvent = $request->route('event');

        if (is_string($routeEvent) && $routeEvent !== '') {
            return strtolower(str_replace(['_', '-'], '.', $routeEvent));
        }

        return strtolower(str_replace('_', '.', (string) $request->input('event', '')));
    }
}
