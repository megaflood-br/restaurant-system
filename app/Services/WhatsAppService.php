<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\Order;
use App\Models\WhatsAppMessage;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppService
{
    public function __construct(
        private readonly EvolutionApiService $evolutionApi,
        private readonly N8nWebhookService $n8nWebhook,
    ) {}

    public function sendToPhone(
        string $phone,
        string $message,
        ?Customer $customer = null,
        ?Order $order = null,
        ?int $userId = null,
        bool $logInteraction = true,
    ): WhatsAppMessage {
        $normalizedPhone = PhoneNumber::normalize($phone);

        if ($normalizedPhone === null) {
            throw new RuntimeException('Telefone inválido para envio via WhatsApp.');
        }

        $record = WhatsAppMessage::create([
            'customer_id' => $customer?->id,
            'order_id' => $order?->id,
            'direction' => 'outbound',
            'phone' => $normalizedPhone,
            'message' => $message,
            'status' => 'pending',
            'user_id' => $userId,
        ]);

        try {
            $response = $this->evolutionApi->sendText($normalizedPhone, $message);

            $record->update([
                'status' => 'sent',
                'evolution_message_id' => data_get($response, 'key.id')
                    ?? data_get($response, 'messageId')
                    ?? data_get($response, 'id'),
                'metadata' => $response,
            ]);

            if ($customer && $logInteraction) {
                $customer->interactions()->create([
                    'type' => 'note',
                    'content' => '[WhatsApp enviado] '.$message,
                    'user_id' => $userId,
                ]);
            }
        } catch (\Throwable $exception) {
            $record->update([
                'status' => 'failed',
                'metadata' => ['error' => $exception->getMessage()],
            ]);

            Log::error('WhatsApp send failed', [
                'phone' => $normalizedPhone,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $record->fresh();
    }

    public function sendToCustomer(Customer $customer, string $message, ?int $userId = null): WhatsAppMessage
    {
        if (! $customer->phone) {
            throw new RuntimeException('Cliente não possui telefone cadastrado.');
        }

        return $this->sendToPhone($customer->phone, $message, $customer, null, $userId);
    }

    public function logInboundMessage(
        string $phone,
        string $message,
        array $payload = [],
        ?string $pushName = null,
    ): WhatsAppMessage {
        $normalizedPhone = PhoneNumber::normalize($phone) ?? $phone;
        $customer = WhatsAppMessage::findCustomerByPhone($normalizedPhone);

        $record = WhatsAppMessage::create([
            'customer_id' => $customer?->id,
            'direction' => 'inbound',
            'phone' => $normalizedPhone,
            'message' => $message,
            'status' => 'received',
            'evolution_message_id' => data_get($payload, 'key.id'),
            'metadata' => $payload ?: null,
        ]);

        if ($customer) {
            CustomerInteraction::create([
                'customer_id' => $customer->id,
                'type' => 'note',
                'content' => '[WhatsApp recebido] '.$message,
                'user_id' => null,
            ]);
        }

        return $record;
    }

    public function sendImageToPhone(
        string $phone,
        string $imageUrl,
        ?string $caption = null,
        ?Customer $customer = null,
        ?Order $order = null,
        ?int $userId = null,
        bool $logInteraction = true,
    ): WhatsAppMessage {
        $normalizedPhone = PhoneNumber::normalize($phone);

        if ($normalizedPhone === null) {
            throw new RuntimeException('Telefone inválido para envio via WhatsApp.');
        }

        $record = WhatsAppMessage::create([
            'customer_id' => $customer?->id,
            'order_id' => $order?->id,
            'direction' => 'outbound',
            'phone' => $normalizedPhone,
            'message' => $caption ?? '[Imagem]',
            'status' => 'pending',
            'user_id' => $userId,
        ]);

        try {
            $response = $this->evolutionApi->sendMedia($normalizedPhone, $imageUrl, 'image', $caption);

            $record->update([
                'status' => 'sent',
                'evolution_message_id' => data_get($response, 'key.id')
                    ?? data_get($response, 'messageId')
                    ?? data_get($response, 'id'),
                'metadata' => $response,
            ]);

            if ($customer && $logInteraction) {
                $customer->interactions()->create([
                    'type' => 'note',
                    'content' => '[WhatsApp imagem enviada] '.($caption ?? $imageUrl),
                    'user_id' => $userId,
                ]);
            }
        } catch (\Throwable $exception) {
            $record->update([
                'status' => 'failed',
                'metadata' => ['error' => $exception->getMessage()],
            ]);

            Log::error('WhatsApp image send failed', [
                'phone' => $normalizedPhone,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $record->fresh();
    }

    public function handleInboundMessage(string $phone, string $message, array $payload = [], ?string $pushName = null): WhatsAppMessage
    {
        $record = $this->logInboundMessage($phone, $message, $payload, $pushName);
        $normalizedPhone = PhoneNumber::normalize($phone) ?? $phone;
        $customer = WhatsAppMessage::findCustomerByPhone($normalizedPhone);

        if (config('whatsapp_agent.use_builtin_bot') && config('whatsapp_agent.enabled')) {
            try {
                app(ConversationalWhatsAppBotService::class)->process($phone, $message, $pushName, $payload);
            } catch (\Throwable $exception) {
                Log::error('Conversational WhatsApp bot failed', [
                    'phone' => $phone,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if (config('whatsapp_agent.forward_to_n8n')) {
            $this->n8nWebhook->forwardInbound([
                'event' => 'whatsapp.inbound',
                'phone' => $normalizedPhone,
                'message' => $message,
                'push_name' => $pushName,
                'customer_id' => $customer?->id,
                'message_id' => $record->id,
                'received_at' => now()->toIso8601String(),
                'payload' => $payload,
            ]);
        }

        return $record;
    }
}
