<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\WhatsAppMessage;
use App\Services\EvolutionApiService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppApiController extends Controller
{
    public function connection(EvolutionApiService $evolutionApi): JsonResponse
    {
        return response()->json(['data' => $evolutionApi->connectionState()]);
    }

    public function messages(Request $request): JsonResponse
    {
        $messages = WhatsAppMessage::query()
            ->with(['customer', 'order'])
            ->when($request->filled('phone'), fn ($q) => $q->where('phone', 'like', '%'.$request->string('phone').'%'))
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->string('direction')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->latest()
            ->limit(min($request->integer('limit', 50), 100))
            ->get();

        return response()->json([
            'data' => $messages->map(fn (WhatsAppMessage $msg) => [
                'id' => $msg->id,
                'direction' => $msg->direction,
                'phone' => $msg->phone,
                'message' => $msg->message,
                'status' => $msg->status,
                'customer_id' => $msg->customer_id,
                'order_id' => $msg->order_id,
                'created_at' => $msg->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function send(Request $request, WhatsAppService $whatsAppService): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        try {
            if ($validated['customer_id'] ?? null) {
                $customer = Customer::findOrFail($validated['customer_id']);
                $record = $whatsAppService->sendToCustomer($customer, $validated['message']);
            } elseif ($validated['order_id'] ?? null) {
                $order = Order::with('customer')->findOrFail($validated['order_id']);
                $phone = $order->customer?->phone ?? $order->customer_phone;

                if (! $phone) {
                    return response()->json(['message' => 'Pedido sem telefone.'], 422);
                }

                $record = $whatsAppService->sendToPhone(
                    $phone,
                    $validated['message'],
                    $order->customer,
                    $order,
                );
            } elseif ($validated['phone'] ?? null) {
                $record = $whatsAppService->sendToPhone(
                    $validated['phone'],
                    $validated['message'],
                );
            } else {
                return response()->json(['message' => 'Informe phone, customer_id ou order_id.'], 422);
            }

            return response()->json([
                'message' => 'Mensagem enviada.',
                'data' => [
                    'id' => $record->id,
                    'status' => $record->status,
                    'phone' => $record->phone,
                ],
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function inbound(Request $request, WhatsAppService $whatsAppService): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:4096'],
            'push_name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $record = $whatsAppService->handleInboundMessage(
            $validated['phone'],
            $validated['message'],
            $validated['metadata'] ?? [],
            $validated['push_name'] ?? null,
        );

        return response()->json([
            'message' => 'Mensagem registrada.',
            'data' => [
                'id' => $record->id,
                'phone' => $record->phone,
                'customer_id' => $record->customer_id,
            ],
        ], 201);
    }
}
