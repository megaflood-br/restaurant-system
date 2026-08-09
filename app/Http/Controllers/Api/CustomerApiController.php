<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\WhatsAppMessage;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerApiController extends Controller
{
    use FormatsApiResponses;

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->boolean('active', true), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->limit(min($request->integer('limit', 50), 100))
            ->get();

        return response()->json([
            'data' => $customers->map(fn (Customer $customer) => $this->customerPayload($customer)),
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->loadCount('orders');

        return response()->json(['data' => $this->customerPayload($customer, true)]);
    }

    public function byPhone(string $phone): JsonResponse
    {
        $normalized = PhoneNumber::normalize($phone);

        if (! $normalized) {
            return $this->apiError('Telefone inválido.');
        }

        $customer = WhatsAppMessage::findCustomerByPhone($normalized);

        if (! $customer) {
            return response()->json(['message' => 'Cliente não encontrado.', 'phone' => $normalized], 404);
        }

        return response()->json(['data' => $this->customerPayload($customer, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (! PhoneNumber::isValid((string) $value)) {
                        $fail('Telefone inválido. Envie o número real, não o nome do campo do n8n.');
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $customer = Customer::create([
            ...$validated,
            'phone' => isset($validated['phone']) ? PhoneNumber::formatForStorage($validated['phone']) : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'message' => 'Cliente criado.',
            'data' => $this->customerPayload($customer),
        ], 201);
    }

    private function customerPayload(Customer $customer, bool $detailed = false): array
    {
        $payload = [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'phone_normalized' => $customer->phone ? PhoneNumber::normalize($customer->phone) : null,
            'email' => $customer->email,
            'is_active' => (bool) $customer->is_active,
        ];

        if ($detailed) {
            $payload['notes'] = $customer->notes;
            $payload['orders_count'] = $customer->orders_count ?? $customer->ordersCount();
            $payload['total_spent'] = $customer->totalSpent();
            $payload['last_order'] = $customer->lastOrder()?->only(['id', 'order_number', 'status', 'total', 'created_at']);
        }

        return $payload;
    }
}
