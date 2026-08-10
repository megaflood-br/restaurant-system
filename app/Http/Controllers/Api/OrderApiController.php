<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Product;
use App\Models\WhatsAppMessage;
use App\Services\EvolutionApiService;
use App\Services\InventoryService;
use App\Services\OrderPrinterService;
use App\Services\WhatsAppService;
use App\Support\PaymentMethod;
use App\Support\PhoneNumber;
use App\Support\ProductSellable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderApiController extends Controller
{
    use FormatsApiResponses;

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['items.product', 'customer'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('comanda'), fn ($q) => $q->where('comanda_number', $request->integer('comanda')))
            ->when($request->filled('phone'), function ($q) use ($request) {
                $phone = PhoneNumber::normalize($request->string('phone'));
                if ($phone) {
                    $q->where(function ($query) use ($phone) {
                        $query->where('customer_phone', 'like', "%{$phone}%")
                            ->orWhereHas('customer', fn ($c) => $c->where('phone', 'like', "%{$phone}%"));
                    });
                }
            })
            ->when($request->boolean('today'), fn ($q) => $q->whereDate('created_at', today()))
            ->when($request->boolean('open'), fn ($q) => $q->whereIn('status', ['pending', 'preparing', 'ready', 'served']))
            ->latest()
            ->limit(min($request->integer('limit', 50), 100))
            ->get();

        return response()->json([
            'data' => $orders->map(fn (Order $order) => $this->orderPayload($order, false)),
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(['data' => $this->orderPayload($order)]);
    }

    public function byPhone(string $phone): JsonResponse
    {
        $normalized = PhoneNumber::normalize($phone);

        if (! $normalized) {
            return $this->apiError('Telefone inválido.');
        }

        $orders = Order::query()
            ->with(['items.product', 'customer'])
            ->where(function ($query) use ($normalized) {
                $query->where('customer_phone', 'like', "%{$normalized}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('phone', 'like', "%{$normalized}%"));
            })
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'phone' => $normalized,
            'data' => $orders->map(fn (Order $order) => $this->orderPayload($order)),
        ]);
    }

    public function store(Request $request, OrderPrinterService $printer, InventoryService $inventory): JsonResponse
    {
        $this->normalizeOrderInput($request);

        $validated = $request->validate([
            'type' => ['required', 'in:dine_in,delivery,takeaway'],
            'comanda_number' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => [
                Rule::requiredIf(fn () => in_array($request->input('type'), ['delivery', 'takeaway'], true)),
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (! PhoneNumber::isValid((string) $value)) {
                        $fail('Telefone inválido. Envie o número real (ex.: 5511987654321), não o nome do campo do n8n.');
                    }
                },
            ],
            'delivery_area_id' => ['nullable', 'exists:delivery_areas,id'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['nullable', 'in:'.implode(',', PaymentMethod::keys())],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variant_id' => \App\Support\ProductVariants::variantIdRules(),
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'print_kitchen' => ['boolean'],
        ]);

        $deliveryArea = null;
        $deliveryFee = 0;

        if ($validated['type'] === 'delivery') {
            if (! empty($validated['delivery_area_id'])) {
                $deliveryArea = DeliveryArea::active()->find($validated['delivery_area_id']);
            }

            $deliveryFee = isset($validated['delivery_fee'])
                ? (float) $validated['delivery_fee']
                : (float) ($deliveryArea?->fee ?? 0);
        }

        $order = DB::transaction(function () use ($validated, $deliveryArea, $deliveryFee) {
            $customer = isset($validated['customer_id'])
                ? Customer::find($validated['customer_id'])
                : $this->findOrCreateCustomer($validated, $deliveryArea);

            $notes = trim(($validated['notes'] ?? '').' [API/n8n]');

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $customer?->id,
                'type' => $validated['type'],
                'comanda_number' => $validated['comanda_number'] ?? null,
                'delivery_area_id' => $deliveryArea?->id,
                'delivery_fee' => $deliveryFee,
                'delivery_address' => $validated['type'] === 'delivery'
                    ? ($validated['delivery_address'] ?? null)
                    : null,
                'customer_name' => $customer?->name ?? ($validated['customer_name'] ?? null),
                'customer_phone' => $customer?->phone ?? ($validated['customer_phone'] ?? null),
                'payment_method' => $validated['payment_method'] ?? null,
                'notes' => $notes !== '' ? $notes : null,
                'status' => 'pending',
                'user_id' => null,
            ]);

            $itemsTotal = 0;

            foreach ($validated['items'] as $item) {
                $product = \App\Support\ProductVariants::loadProduct((int) $item['product_id']);
                $line = ProductSellable::orderItemAttributes(
                    $product,
                    (int) $item['quantity'],
                    isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                    $item['notes'] ?? null,
                );

                $order->items()->create($line);
                $itemsTotal += $line['subtotal'];
            }

            $order->update(['total' => $itemsTotal + $deliveryFee]);

            return $order;
        });

        $inventory->deductForOrder($order->fresh(['items.product.recipe', 'items.productVariant.recipe']));

        if ($request->boolean('print_kitchen', true)) {
            try {
                if (config('printing.enabled') && config('printing.driver') === 'network') {
                    $printer->printOrder($order->fresh(['items.product', 'deliveryArea']), 'kitchen');
                }
            } catch (\Throwable) {
                //
            }
        }

        return response()->json([
            'message' => 'Pedido criado.',
            'data' => $this->orderPayload($order->fresh()),
        ], 201);
    }

    private function normalizeOrderInput(Request $request): void
    {
        $merge = [];

        if (! $request->filled('customer_phone')) {
            foreach (['phone', 'client_phone_number', 'customer_phone_number', 'whatsapp', 'from', 'remoteJid', 'remote_jid'] as $alias) {
                if ($request->filled($alias)) {
                    $merge['customer_phone'] = $request->input($alias);
                    break;
                }
            }
        }

        if (! $request->filled('customer_name')) {
            foreach (['name', 'client_name', 'customer_name', 'pushName', 'push_name'] as $alias) {
                if ($request->filled($alias)) {
                    $merge['customer_name'] = $request->input($alias);
                    break;
                }
            }
        }

        if (! $request->filled('delivery_address') && $request->filled('address')) {
            $merge['delivery_address'] = $request->input('address');
        }

        if (! $request->filled('payment_method') && $request->filled('payment')) {
            $merge['payment_method'] = $request->input('payment');
        }

        $paymentRaw = $request->input('payment_method') ?? $request->input('payment');
        if (filled($paymentRaw)) {
            $normalized = PaymentMethod::normalize((string) $paymentRaw);
            $merge['payment_method'] = $normalized ?? $paymentRaw;
        }

        if (! $request->filled('type')) {
            $merge['type'] = $request->filled('delivery_address') || $request->filled('address')
                ? 'delivery'
                : 'takeaway';
        }

        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    /** @param  array<string, mixed>  $validated */
    private function findOrCreateCustomer(array $validated, ?DeliveryArea $deliveryArea): ?Customer
    {
        $phone = $validated['customer_phone'] ?? null;

        if (! filled($phone)) {
            return null;
        }

        $normalized = PhoneNumber::normalize($phone);

        $customer = Customer::query()
            ->whereNotNull('phone')
            ->get()
            ->first(fn (Customer $candidate) => PhoneNumber::normalize($candidate->phone) === $normalized);

        if ($customer) {
            if (! empty($validated['delivery_address'])) {
                $customer->update([
                    'address' => $validated['delivery_address'],
                    'neighborhood' => $deliveryArea?->name ?? $customer->neighborhood,
                ]);
            }

            return $customer;
        }

        return Customer::create([
            'name' => $validated['customer_name'] ?? 'Cliente WhatsApp',
            'phone' => PhoneNumber::formatForStorage($phone),
            'address' => $validated['delivery_address'] ?? null,
            'neighborhood' => $deliveryArea?->name,
            'is_active' => true,
            'notes' => 'Cadastrado via API/n8n',
        ]);
    }

    public function updateStatus(Request $request, Order $order, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,preparing,ready,served,delivered,cancelled'],
        ]);

        $previousStatus = $order->status;
        $order->update(['status' => $validated['status']]);

        if ($validated['status'] === 'cancelled' && $previousStatus !== 'cancelled') {
            $inventory->restoreForOrder($order->fresh(['items.product.recipe', 'items.productVariant.recipe']));
        }

        return response()->json([
            'message' => 'Status atualizado.',
            'previous_status' => $previousStatus,
            'data' => $this->orderPayload($order->fresh()),
        ]);
    }
}
