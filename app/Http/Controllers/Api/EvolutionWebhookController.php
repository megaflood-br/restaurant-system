<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use App\Support\WhatsAppBotPause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
                $this->handleHumanOutbound($message);

                continue;
            }

            $text = $this->extractText($message);

            if ($text === null || trim($text) === '') {
                if (! $this->hasMedia($message)) {
                    continue;
                }

                $text = '[imagem]';
            }

            $remoteJid = $this->resolveRemoteJid($message);

            if (! $remoteJid || str_contains($remoteJid, '@g.us') || str_contains($remoteJid, '@broadcast')) {
                continue;
            }

            $phone = explode('@', $remoteJid)[0];
            $pushName = data_get($message, 'pushName');
            $evolutionMessageId = data_get($message, 'key.id');

            if (is_string($evolutionMessageId) && $evolutionMessageId !== '') {
                $dedupeKey = 'wa:evolution-msg:'.$evolutionMessageId;

                if (! Cache::add($dedupeKey, 1, now()->addMinutes(10))) {
                    Log::info('Evolution webhook duplicate message skipped', [
                        'evolution_message_id' => $evolutionMessageId,
                        'phone' => $phone,
                    ]);

                    continue;
                }
            }

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

    private function handleHumanOutbound(array $message): void
    {
        $remoteJid = $this->resolveRemoteJid($message);

        if (! $remoteJid || str_contains($remoteJid, '@g.us') || str_contains($remoteJid, '@broadcast')) {
            return;
        }

        $phone = explode('@', $remoteJid)[0];
        $messageId = data_get($message, 'key.id');

        if (WhatsAppBotPause::wasSentByBot($phone, is_string($messageId) ? $messageId : null)) {
            return;
        }

        WhatsAppBotPause::pause($phone, 'human_whatsapp');

        Log::info('WhatsApp bot paused for human takeover', [
            'phone' => $phone,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Prefere JID com número real (@s.whatsapp.net / @c.us) em vez de @lid,
     * para a sessão do carrinho não mudar no meio do pedido.
     */
    private function resolveRemoteJid(array $message): ?string
    {
        $candidates = [
            data_get($message, 'key.remoteJidAlt'),
            data_get($message, 'key.remoteJid'),
            data_get($message, 'key.participantAlt'),
            data_get($message, 'key.participant'),
        ];

        $fallback = null;

        foreach ($candidates as $jid) {
            if (! is_string($jid) || $jid === '') {
                continue;
            }

            if (str_contains($jid, '@g.us') || str_contains($jid, '@broadcast')) {
                continue;
            }

            if (str_contains($jid, '@s.whatsapp.net') || str_contains($jid, '@c.us')) {
                return $jid;
            }

            $fallback ??= $jid;
        }

        return $fallback;
    }
}
